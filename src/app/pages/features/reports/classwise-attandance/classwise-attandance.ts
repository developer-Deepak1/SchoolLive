import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AcademicCalendarService } from '@/pages/features/services/academic-calendar.service';
import { AcademicYearService } from '@/pages/features/services/academic-year.service';
import { StudentsService } from '@/pages/features/services/students.service';
import { AttendanceService } from '@/pages/features/services/attendance.service';
import { firstValueFrom } from 'rxjs';
import { FormsModule } from '@angular/forms';
import { TableModule } from 'primeng/table';
import { SelectModule } from 'primeng/select';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { TagModule } from 'primeng/tag';
import { DialogModule } from 'primeng/dialog';

interface AttendanceRow {
  studentName: string;
  className: string;
  // statuses indexed by day-1, e.g. statuses[0] => day 1
  statuses: string[];
  remarks?: string;
}

@Component({
  selector: 'app-classwise-attandance',
  standalone: true,
  imports: [CommonModule, FormsModule, TableModule, SelectModule, DatePickerModule, ButtonModule, TagModule, DialogModule],
  templateUrl: './classwise-attandance.html',
  styleUrl: './classwise-attandance.scss'
})
export class ClasswiseAttandance implements OnInit {
  classOptions: any[] = [];
  sectionOptions: any[] = [];
  // cache sections per class
  private sectionsCache: Record<number, any[]> = {};
  selectedClass: number | null = null;
  selectedSection: number | null = null;
  // using ISO date string for p-calendar/ngModel
  date = new Date();

  // ...existing code...

  // computed days for current month
  days: number[] = [];

  // holiday map removed - API handles this server-side

  rows: AttendanceRow[] = [];

  // client-side search filter (search by student name)
  filterText: string = '';

  get filteredRows(): AttendanceRow[] {
    const t = (this.filterText || '').trim().toLowerCase();
    if (!t) return this.rows || [];
    return (this.rows || []).filter(r => (r.studentName || '').toLowerCase().includes(t));
  }

  selected: AttendanceRow | null = null;
  detailDialogVisible = false;

  // human-readable selected labels for header
  get selectedClassLabel(): string {
    if (!this.selectedClass) return '—';
    const cls = this.classOptions.find(c => c.ClassID === this.selectedClass || c.class_id === this.selectedClass || c.Class === this.selectedClass);
    return cls ? (cls.ClassName || cls.class_name || String(this.selectedClass)) : String(this.selectedClass);
  }

  get selectedSectionLabel(): string {
    if (!this.selectedSection) return '';
    const sec = this.sectionOptions.find(s => s.SectionID === this.selectedSection || s.section_id === this.selectedSection || s.Section === this.selectedSection);
    return sec ? (sec.SectionName || sec.section_name || String(this.selectedSection)) : String(this.selectedSection);
  }

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
    // Only load attendance data when both class and section are selected
    if (this.selectedClass && this.selectedSection) {
      // load data directly from API - it handles holidays and weekly offs
      this.loadMonthData(daysInMonth);
    } else {
      // clear any existing data if filters not selected
      this.rows = [];
    }
  }

  // inject AcademicCalendarService to fetch holidays
  private academicSvc = inject(AcademicCalendarService);
  // inject AcademicYearService to fetch academic year details
  private academicYearSvc = inject(AcademicYearService);
  // inject AttendanceService to fetch daily attendance
  private attendanceSvc = inject(AttendanceService);
  // inject StudentsService for classes/sections
  private studentsSvc = inject(StudentsService);

  // Date range constraints for academic year
  minDate: Date | null = null;
  maxDate: Date | null = null;

  constructor() {
    this.loadClasses();
  }

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

  loadClasses() {
    this.studentsSvc.getClasses().subscribe({ next: (c:any[]) => {
      this.classOptions = c || [];
      // fetch all sections in one call and group by ClassID
      this.studentsSvc.getAllSections().subscribe({ next: (allSections:any[]) => {
        (allSections || []).forEach((s:any) => {
          const cid = s.ClassID || s.class_id || s.Class || null;
          if (!cid) return;
          if (!this.sectionsCache[cid]) this.sectionsCache[cid] = [];
          this.sectionsCache[cid].push(s);
        });
      }, error: () => {} });
    }, error: () => {} });
  }

  onClassChange() {
    this.selectedSection = null;
    this.rows = [];
    if (!this.selectedClass) { this.sectionOptions = []; return; }
    const cached = this.sectionsCache[this.selectedClass as number];
    if (cached) { this.sectionOptions = cached; return; }
    this.studentsSvc.getSections(this.selectedClass).subscribe({ next: (s:any[]) => { this.sectionOptions = s || []; this.sectionsCache[this.selectedClass as number] = s || []; }, error: () => { this.sectionOptions = []; } });
  }

  onSectionChange() {
    // when both class and section are selected, load the grid
    if (this.selectedClass && this.selectedSection) {
      this.updateGrid();
    } else {
      this.rows = [];
    }
  }

  // Load attendance from API for each day of the month and aggregate per student
  async loadMonthData(daysInMonth: number) {
    const year = this.date.getFullYear();
    const month = this.date.getMonth() + 1; // API expects 1-based month
    // derive class and section ids if available
    const classId = typeof this.selectedClass === 'number' ? this.selectedClass : undefined;
    let sectionId: number | undefined;
    const sec: any = this.selectedSection;
    if (sec == null) sectionId = undefined;
    else if (typeof sec === 'number') sectionId = sec;
    else if (typeof sec === 'string' && /^\d+$/.test(sec)) sectionId = parseInt(sec, 10);
    else if (typeof sec === 'object') sectionId = sec.SectionID || sec.SectionId || sec.id || undefined;

    try {
      const res: any = await firstValueFrom(this.attendanceSvc.getMonthly(year, month, classId ?? null, sectionId ?? null));
      const records = res && res.records ? res.records : [];
      // map API records to AttendanceRow - API now handles holidays and weekly offs
      this.rows = (records || []).map((r: any) => {
        const statusesFromApi = Array.isArray(r.statuses) ? r.statuses : (r.statuses || []);
        // convert statuses to short codes and ensure length - API provides canonical strings
        const statuses = Array.from({ length: daysInMonth }, (_, i) => {
          const v = statusesFromApi[i];
          if (v == null) return '-';
          return this.toShortLower(v);
        });
        return { studentName: r.StudentName || r.StudentName || 'Unknown', className: r.ClassName || String(this.selectedClass || '—'), statuses, remarks: r.Remarks || '' } as AttendanceRow;
      });
    } catch (e) {
      // fallback to empty rows on error
      this.rows = [];
    }
  }

  refresh() {
  // require class & section before refreshing
  if (!this.selectedClass || !this.selectedSection) return;
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

  // mobile view removed

  openDetails(r: AttendanceRow) { this.selected = r; this.detailDialogVisible = true; }

  // Export current table (filteredRows) to a CSV file compatible with Excel
  exportToExcel() {
    try {
      const rows = this.filteredRows || [];
      if (!rows || rows.length === 0) return;

      // Header: #, Student, days..., Total Present, Total Absent
      const header = ['#', 'Student', ...this.days.map(d => String(d)), 'Total Present', 'Total Absent', 'Total Leave', 'Total Half Day', 'Total Holiday', 'Total No Status'];
      const csvLines: string[] = [];
      // add BOM for Excel to recognize UTF-8
      // we will prepend BOM when creating blob
      csvLines.push(header.join(','));

      rows.forEach((r, idx) => {
        const present = this.getPresentCount(r);
        const absent = this.getAbsentCount(r);
          const leave = this.getLeaveCount(r);
          const half = this.getHalfDayCount(r);
          const holiday = this.getHolidayCount(r);
          const nostatus = this.getNoStatusCount(r);
        const statusCells = (r.statuses || []).map(s => (s || '').toUpperCase());
          const line = [String(idx + 1), `"${(r.studentName||'').replace(/"/g,'""')}"`, ...statusCells, String(present), String(absent), String(leave), String(half), String(holiday), String(nostatus)];
        csvLines.push(line.join(','));
      });

      const csvContent = '\uFEFF' + csvLines.join('\n'); // BOM + CSV
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      const monthLabel = this.date ? (this.date.toLocaleString('default', { month: 'short', year: 'numeric' })) : 'month';
      const clsLabel = this.selectedClassLabel || 'class';
      const filename = `attendance_${clsLabel.replace(/\s+/g,'_')}_${this.selectedSection || 'all'}_${monthLabel.replace(/\s+/g,'_')}.csv`;
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
