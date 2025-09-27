import { Component, OnInit, inject, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { TagModule } from 'primeng/tag';
import { DatePickerModule } from 'primeng/datepicker';
import { UserService } from '@/services/user.service';
import { EmployeeAttendanceService } from '../../services/employee-attendance.service';
import { AcademicYearService } from '../../services/academic-year.service';
import { USER_ROLES } from '@/pages/common/constant';

interface AttendanceRecord {
  Date: string;
  EmployeeName?: string;
  SignInTime: string | null;
  SignOutTime: string | null;
  Status: string;
  Remarks: string | null;
  EmployeeID?: number;
}

@Component({
  selector: 'app-employee-attendance-details',
  imports: [
    CommonModule,
    FormsModule,
    DatePickerModule,
    CardModule,
    TableModule,
    ButtonModule,
    InputTextModule,
    TagModule
  ],
  templateUrl: './employee-attendance-details.html',
  styleUrl: './employee-attendance-details.scss'
})
export class EmployeeAttendanceDetailsComponent implements OnInit {
  
  private userService = inject(UserService);
  private attendanceService = inject(EmployeeAttendanceService);
  private academicYearService = inject(AcademicYearService);
  
  // Data properties
  attendanceData: AttendanceRecord[] = [];
  // Keep original fetched data for filtering/reset
  originalData: AttendanceRecord[] = [];
  loading = false;
  date = new Date();
  minDate = new Date();
  maxDate = new Date();
  // Date filter used by the table
  filterDate: Date | null = null;

  @ViewChild('dt') table!: any;
  @ViewChild('globalFilter') globalFilterRef?: ElementRef<HTMLInputElement>;
  
  // Role properties
  userRole: number = 0;
  isSchoolAdmin = false;
  employeeID: number = 0;
  
  constructor() {
    // Set date constraints
    this.minDate.setFullYear(this.minDate.getFullYear() - 2);
    this.maxDate = new Date();
    
    // Initialize role
    this.userRole = this.userService.getRoleId() ?? 0;
    this.employeeID = this.userService.getEmployeeId() ?? 0;
    this.isSchoolAdmin = this.userRole === USER_ROLES.ROLE_SCHOOLADMIN;
  }
  
  ngOnInit(): void {
    // Load academic year bounds first so month picker is constrained
    this.initAcademicYearBounds();
  }

  private initAcademicYearBounds(): void {
    const ayId = this.userService.getAcademicYearId();
    // Attempt to fetch academic years and pick the user's year or the active one
    this.academicYearService.getAcademicYears().subscribe({
      next: (years: any[]) => {
        try {
          let found = null;
          if (ayId) {
            found = (years || []).find((y: any) => Number(y.AcademicYearID) === Number(ayId) || Number(y.AcademicYearID) === Number((ayId)));
          }
          if (!found) {
            found = (years || []).find((y: any) => (String(y.Status || y.status || '').toLowerCase() === 'active')) || (years || [])[0] || null;
          }
          if (found && found.StartDate && found.EndDate) {
            const s = new Date(found.StartDate);
            const e = new Date(found.EndDate);
            // For month selection, constrain to the start of StartDate month and end of EndDate month
            this.minDate = new Date(s.getFullYear(), s.getMonth(), 1);
            //this.maxDate = new Date(e.getFullYear(), e.getMonth(), e.getDate() || 31);
            // Clamp currently selected month within bounds
            if (this.date < this.minDate) this.date = new Date(this.minDate);
            if (this.date > this.maxDate) this.date = new Date(this.maxDate);
          }
        } catch (e) {
          // ignore and fall back to defaults set in constructor
        }
        // Now load grid using the resolved date and bounds
        this.updateGrid();
      },
      error: () => {
        // on error, continue with defaults
        this.updateGrid();
      }
    });
  }
  
  updateGrid(): void {
    this.loading = true;
    const year = this.date.getFullYear();
    const month = this.date.getMonth() + 1; // getMonth() returns 0-11
    
    if (this.isSchoolAdmin) {
      // School admin sees all employees
      this.loadAllEmployeeAttendance(year, month);
    } else {
      // Teacher sees only their own attendance
      this.loadUserAttendance(year, month,this.employeeID);
    }
  }

  onDateFilter(): void {
    if (!this.filterDate) {
      // Reset to original data
      this.attendanceData = [...this.originalData];
      return;
    }

    const sel = new Date(this.filterDate);
    const selY = sel.getFullYear();
    const selM = sel.getMonth();
    const selD = sel.getDate();

    this.attendanceData = this.originalData.filter(a => {
      if (!a?.Date) return false;
      const dt = new Date(a.Date);
      if (isNaN(dt.getTime())) return false;
      return dt.getFullYear() === selY && dt.getMonth() === selM && dt.getDate() === selD;
    });
  }

  clearFilters(globalFilter?: HTMLInputElement | ElementRef<HTMLInputElement>): void {
    // Clear date filter
    this.filterDate = null;
    // Reset data
    this.attendanceData = [...this.originalData];

    // Clear global search input if present in the template
    try {
      const el = this.globalFilterRef?.nativeElement;
      if (el && typeof el.value !== 'undefined') el.value = '';
    } catch (e) { /* ignore */ }

    // Ensure filter logic is applied (reset global search)
    this.filterTable('');
  }
  
  private loadAllEmployeeAttendance(year: number, month: number): void {
    this.attendanceService.getEmployeeAttendanceByMonth(year, month).subscribe({
      next: (response: any) => {
  // Handle both direct array response and wrapped response with data property
  this.attendanceData = response.data || response || [];
  this.originalData = [...this.attendanceData];
        this.loading = false;
      },
      error: (error: any) => {
        console.error('Failed to load employee attendance:', error);
        this.attendanceData = [];
        this.loading = false;
      }
    });
  }

  private loadUserAttendance(year: number, month: number, employeeId: number): void {
    this.attendanceService.getUserAttendanceByMonth(year, month, employeeId).subscribe({
      next: (response: any) => {
  // Handle both direct array response and wrapped response with data property
  this.attendanceData = response.data || response || [];
  this.originalData = [...this.attendanceData];
        this.loading = false;
      },
      error: (error: any) => {
        console.error('Failed to load user attendance:', error);
        this.attendanceData = [];
        this.loading = false;
      }
    });
  }
  
  filterTable(event: any): void {
    const query = (typeof event === 'string') ? event : (event?.target?.value ?? '');
    const q = String(query).trim().toLowerCase();

    // Start from original data and apply date filter first (if any)
    let results = [...this.originalData];

    if (this.filterDate) {
      const sel = new Date(this.filterDate);
      const selY = sel.getFullYear();
      const selM = sel.getMonth();
      const selD = sel.getDate();
      results = results.filter(a => {
        const dt = new Date(a.Date);
        return dt.getFullYear() === selY && dt.getMonth() === selM && dt.getDate() === selD;
      });
    }

    if (!q) {
      this.attendanceData = results;
      return;
    }

    const fields = this.getFilterFields();
    this.attendanceData = results.filter(rec => {
      return fields.some(field => {
        const val = (rec as any)[field];
        if (val == null) return false;
        // For Date field, compare formatted date string
        if (field === 'Date') {
          if (!rec?.Date) return false;
          const dt = new Date(rec.Date);
          if (isNaN(dt.getTime())) return false;
          const dd = String(dt.getDate()).padStart(2, '0');
          const mm = String(dt.getMonth() + 1).padStart(2, '0');
          const yyyy = dt.getFullYear();
          const formatted = `${dd}/${mm}/${yyyy}`;
          return formatted.toLowerCase().includes(q);
        }
        return String(val).toLowerCase().includes(q);
      });
    });
  }
  
  getFilterFields(): string[] {
    const baseFields = ['Date', 'SignInTime', 'SignOutTime', 'Status', 'Remarks'];
    return this.isSchoolAdmin ? ['EmployeeName', ...baseFields] : baseFields;
  }
  
  getTimeStatusClass(time: string | null, type: 'signin' | 'signout'): string {
    if (!time) {
      return 'text-red-500 font-medium';
    }
    
    const timeObj = new Date(`1970-01-01T${time}`);
    const hours = timeObj.getHours();
    const minutes = timeObj.getMinutes();
    
    if (type === 'signin') {
      // Consider on-time if before 10:30 AM
      if (hours < 10 || (hours === 10 && minutes <= 30)) {
        return 'text-green-600 font-medium';
      } else {
        return 'text-orange-500 font-medium';
      }
    } else {
      // Sign out - consider normal if after 5:00 PM
      if (hours >= 13) {
        return 'text-green-600 font-medium';
      } else {
        return 'text-orange-500 font-medium';
      }
    }
  }

  formatTime(time: string | null): string {
    if (!time) return '';
    try {
      // Ensure we have a parsable ISO time; append date if needed
      const iso = time.includes('T') ? time : `1970-01-01T${time}`;
      const dt = new Date(iso);
      if (isNaN(dt.getTime())) return String(time);
      // Use locale formatting to produce '9:30 am' style output
      return dt.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();
    } catch (e) {
      return String(time);
    }
  }
  
  getStatusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' | undefined {
    switch (status?.toLowerCase()) {
      case 'present':
        return 'success';
      case 'absent':
        return 'danger';
      case 'leave':
        return 'warn';
      case 'holiday':
        return 'info';
      case 'weekly-off':
        return 'secondary';
      default:
        return 'contrast';
    }
  }
}

// Backwards-compatible export: some files import the component as `EmployeeAttendanceDetails`
export { EmployeeAttendanceDetailsComponent as EmployeeAttendanceDetails };
