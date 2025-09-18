import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AcademicCalendarService } from '@/pages/features/services/academic-calendar.service';
import { AcademicYearService } from '@/pages/features/services/academic-year.service';
import { EmployeeAttendanceService } from '@/pages/features/services/employee-attendance.service';
import { firstValueFrom } from 'rxjs';
import { FormsModule } from '@angular/forms';
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { TagModule } from 'primeng/tag';
import { CardModule } from 'primeng/card';

interface AttendanceRow {
  employeeName: string;
  roleName: string;
  // statuses indexed by day-1, e.g. statuses[0] => day 1
  statuses: string[];
  remarks?: string;
}

@Component({
  selector: 'app-employee-attandance-reports',
  standalone: true,
  imports: [CommonModule, FormsModule, TableModule, DatePickerModule, ButtonModule, TagModule,CardModule],
  templateUrl: './employee-attandance-reports.html',
  styleUrl: './employee-attandance-reports.scss'
})
export class EmployeeAttandanceReports implements OnInit {
  // using ISO date string for p-calendar/ngModel - default to current month
  date = new Date();

  // computed days for current month
  days: number[] = [];

  rows: AttendanceRow[] = [];

  // filter text for search functionality
  filterText: string = '';

  ngOnInit() {
    this.loadAcademicYearConstraints();
    this.updateGrid();
  }

  // compute number of days for selected month/year and rebuild sample grid
  updateGrid() {
    const year = this.date.getFullYear();
    const month = this.date.getMonth(); // 0-based
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    this.days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    // load data directly from API - it handles holidays and weekly offs
    this.loadMonthData(daysInMonth);
  }

  // inject services
  private academicSvc = inject(AcademicCalendarService);
  private academicYearSvc = inject(AcademicYearService);
  private employeeAttendanceSvc = inject(EmployeeAttendanceService);

  // Date range constraints for academic year
  minDate: Date | null = null;
  maxDate: Date | null = null;

  // Load academic year constraints to restrict date selection
  async loadAcademicYearConstraints() {
    try {
      const academicYears = await firstValueFrom(this.academicYearSvc.getAcademicYears());
      // Find the current/active academic year
      const currentAY = academicYears.find(ay => ay.Status === 'Active' || ay.Status === 'active');
      
      if (currentAY && currentAY.StartDate && currentAY.EndDate) {
        // Set min/max dates based on academic year start and end
        this.minDate = new Date(currentAY.StartDate);
        this.maxDate = new Date(currentAY.EndDate);
        
        // Ensure the current selected date is within the academic year range
        if (this.date < this.minDate) {
          this.date = new Date(this.minDate);
        } else if (this.date > this.maxDate) {
          this.date = new Date(this.maxDate);
        }
      }
    } catch (error) {
      console.error('Failed to load academic year constraints:', error);
      // If we can't load constraints, allow all dates
      this.minDate = null;
      this.maxDate = null;
    }
  }

  // Load attendance from API for each day of the month and aggregate per employee
  async loadMonthData(daysInMonth: number) {
    const year = this.date.getFullYear();
    const month = this.date.getMonth() + 1; // API expects 1-based month

    try {
      const res: any = await firstValueFrom(this.employeeAttendanceSvc.getMonthly(year, month));
      const records = res && res.data && res.data.records ? res.data.records : [];
      // map API records to AttendanceRow - API now handles holidays and weekly offs
      this.rows = (records || []).map((r: any) => {
        const statusesFromApi = Array.isArray(r.statuses) ? r.statuses : (r.statuses || []);
        // convert statuses to short codes and ensure length - API provides canonical strings
        const statuses = Array.from({ length: daysInMonth }, (_, i) => {
          const v = statusesFromApi[i];
          if (v == null) return '-';
          return this.toShortLower(v);
        });
        return { 
          employeeName: r.EmployeeName || 'Unknown', 
          roleName: r.RoleName || '',
          statuses, 
          remarks: r.Remarks || '' 
        } as AttendanceRow;
      });
    } catch (e) {
      console.error('Failed to load employee attendance:', e);
      // fallback to empty rows on error
      this.rows = [];
    }
  }

  refresh() {
    this.updateGrid();
  }

  getPresentCount(row: AttendanceRow) {
    return row.statuses.filter(s => s === 'p' || s === 'h').length;
  }

  getAbsentCount(row: AttendanceRow) {
    return row.statuses.filter(s => s === 'a').length;
  }

  // New counts requested by user
  getLeaveCount(row: AttendanceRow) {
    return row.statuses.filter(s => s === 'l').length;
  }

  getHalfDayCount(row: AttendanceRow) {
    return row.statuses.filter(s => s === 'h').length;
  }

  getHolidayCount(row: AttendanceRow) {
    return row.statuses.filter(s => s === '*').length;
  }

  getNoStatusCount(row: AttendanceRow) {
    return row.statuses.filter(s => s === '-' || s === null || s === undefined).length;
  }

  getSeverity(status: string) {
    switch ((status || '').toLowerCase()) {
      case 'p': return 'success';
      case 'l': return 'warn';
      case 'h': return 'info';
      case 'a': return 'danger';
      case '*': return 'secondary';
      case '-': return 'contrast';
      default: return null;
    }
  }

  getClassForStatus(status: string) {
    switch ((status || '').toLowerCase()) {
      case 'p': return 'tag-present';
      case 'l': return 'tag-late';
      case 'h': return 'tag-halfday';
      case 'a': return 'tag-absent';
      case '*': return 'tag-holiday';
      case '-': return 'tag-nostatus';
      default: return null;
    }
  }

  // convert verbose status to short single-letter lower-case code used in template
  toShortLower(status: string): string {
    if (!status) return '-';
    const s = String(status).toLowerCase();
    // handle canonical status strings from API
    if (s === 'present' || s === 'p') return 'p';
    if (s === 'leave' || s === 'l') return 'l';
    if (s === 'halfday' || s === 'half-day' || s === 'h' || s === 'half day') return 'h';
    if (s === 'absent' || s === 'a') return 'a';
    if (s === 'holiday' || s === 'weekly-off' || s === '*') return '*';
    if (s === 'no status' || s === 'nostatus') return '-';
    return '-';
  }

  // Export current table (rows) to a CSV file compatible with Excel
  exportToExcel() {
    try {
      const rows = this.rows || [];
      if (!rows || rows.length === 0) return;

      // Header: #, Employee, Role, days..., Total Present, Total Absent, etc.
      const header = ['#', 'Employee', 'Role', ...this.days.map(d => String(d)), 'Total Present', 'Total Absent', 'Total Leave', 'Total Half Day', 'Total Holiday', 'Total No Status'];
      const csvLines: string[] = [];
      csvLines.push(header.join(','));

      rows.forEach((r: AttendanceRow, idx: number) => {
        const present = this.getPresentCount(r);
        const absent = this.getAbsentCount(r);
        const leave = this.getLeaveCount(r);
        const half = this.getHalfDayCount(r);
        const holiday = this.getHolidayCount(r);
        const nostatus = this.getNoStatusCount(r);
        const statusCells = (r.statuses || []).map((s: string) => (s || '').toUpperCase());
        const line = [
          String(idx + 1), 
          `"${(r.employeeName||'').replace(/"/g,'""')}"`,
          `"${(r.roleName||'').replace(/"/g,'""')}"`,
          ...statusCells, 
          String(present), 
          String(absent), 
          String(leave), 
          String(half), 
          String(holiday), 
          String(nostatus)
        ];
        csvLines.push(line.join(','));
      });

      const csvContent = '\uFEFF' + csvLines.join('\n'); // BOM + CSV
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      const monthLabel = this.date ? (this.date.toLocaleString('default', { month: 'short', year: 'numeric' })) : 'month';
      const filename = `employee_attendance_${monthLabel.replace(/\s+/g,'_')}.csv`;
      a.href = url;
      a.setAttribute('download', filename);
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch (e) {
      // fail silently for now; could show toast
      console.error('Export failed', e);
    }
  }
}
