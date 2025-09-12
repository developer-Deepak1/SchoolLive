import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { InputTextModule } from 'primeng/inputtext';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { EmployeeAttendanceService } from '@/pages/features/services/employee-attendance.service';
import { UserService } from '@/services/user.service';
import { AcademicCalendarService } from '@/pages/features/services/academic-calendar.service';
import { AcademicYearService } from '@/pages/features/services/academic-year.service';

@Component({
  selector: 'app-employee-attandance',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonModule, DatePickerModule, SelectModule, InputTextModule, CardModule, TableModule],
  templateUrl: './employee-attandance.html',
  styleUrl: './employee-attandance.scss'
})
export class EmployeeAttandance implements OnInit {
  private attendanceSvc: EmployeeAttendanceService = inject(EmployeeAttendanceService);
  private userSvc: UserService = inject(UserService);
  private calendarSvc: AcademicCalendarService = inject(AcademicCalendarService);
  private yearSvc: AcademicYearService = inject(AcademicYearService);

  // form model
  reqDate: Date | null = new Date();
  reqType: 'Leave' | 'Attendance' = 'Leave';
  reqTypeOptions = [
    { label: 'Leave', value: 'Leave' },
    { label: 'Attendance', value: 'Attendance' }
  ];
  reqReason: string = '';

  // list
  requests: any[] = [];
  loading = false;
  message: string | null = null;
  // holidays / weekly offs
  holidays: any[] = [];
  weeklyOffs: number[] = [];
  selectedDateStatus: string | null = null; // 'Holiday' | 'WorkingDay' | 'WeeklyOff' | 'OutOfAcademicRange' | null
  // academic year bounds
  academicStart: string | null = null; // YYYY-MM-DD
  academicEnd: string | null = null;   // YYYY-MM-DD
  // unified min/max date objects (same style as calendar component)
  minDateObj: Date | null = null;
  maxDateObj: Date | null = null;

  
  // outOfAcademicRange removed: datepicker min/max prevents out-of-range selection

  // Safe getter to provide Date|null for datepicker bindings when reqDate may be string or Date
  get reqDateObj(): Date | null {
    if (!this.reqDate) return null;
    // reuse robust parsing similar to academic calendar
    const pd = this.parseToLocalDate(this.reqDate);
    if (pd) return pd;
    if (this.reqDate instanceof Date) return new Date(this.reqDate.getFullYear(), this.reqDate.getMonth(), this.reqDate.getDate());
    try {
      const parsed = new Date(String(this.reqDate));
      if (isNaN(parsed.getTime())) return null;
      return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    } catch {
      return null;
    }
  }

  // Helper: parse Date, timestamp, or YYYY-MM-DD/ISO string into local Date at midnight
  private parseToLocalDate(v: any): Date | null {
    if (!v && v !== 0) return null;
    if (v instanceof Date) return new Date(v.getFullYear(), v.getMonth(), v.getDate());
    if (typeof v === 'number') {
      const dt = new Date(v);
      return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
    }
    if (typeof v === 'string') {
      const ymd = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (ymd) {
        const y = Number(ymd[1]);
        const m = Number(ymd[2]) - 1;
        const d = Number(ymd[3]);
        return new Date(y, m, d);
      }
      const parsed = new Date(v);
      if (!isNaN(parsed.getTime())) return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    }
    return null;
  }

  ngOnInit(): void {
    this.loadCalendar();
    this.loadRequests();
    // load academic year bounds
    this.yearSvc.getAcademicYears().subscribe({
      next: (yrs: any[]) => {
        // Prefer active year or the first returned that has start/end
        const sel = (yrs || []).find(y => (y.Status || y.status) === 'active') || (yrs && yrs[0]) || null;
        if (sel) {
          this.academicStart = (sel.StartDate || sel.start_date || '').split('T')[0] || null;
          this.academicEnd = (sel.EndDate || sel.end_date || '').split('T')[0] || null;
          // compute date objects used by datepicker
          try {
            this.minDateObj = this.academicStart ? new Date(this.academicStart) : null;
            if (this.minDateObj && isNaN(this.minDateObj.getTime())) this.minDateObj = null;
          } catch { this.minDateObj = null; }
          try {
            this.maxDateObj = this.academicEnd ? new Date(this.academicEnd) : null;
            if (this.maxDateObj && isNaN(this.maxDateObj.getTime())) this.maxDateObj = null;
          } catch { this.maxDateObj = null; }
        }
      }, error: () => { this.academicStart = this.academicEnd = null; this.minDateObj = this.maxDateObj = null; }
    });
  }

  submitRequest() {
    this.loading = true; this.message = null;
    const eid = this.userSvc.getEmployeeId() ?? undefined;
    const dateStr = this.reqDate ? this.formatDate(this.reqDate) : this.formatDate(new Date());
    this.attendanceSvc.createRequest(dateStr, this.reqType, this.reqReason, eid).subscribe({
      next: (res: any) => {
        this.loading = false;
        if (res && res.success) {
          this.message = 'Request submitted';
          this.reqReason = '';
          this.loadRequests();
        } else {
          this.message = res.message || 'Failed to submit';
        }
      },
      error: (err: any) => { this.loading = false; this.message = err?.message || 'Request failed'; }
    });
  }

  // Format a Date to YYYY-MM-DD (local date)
  private formatDate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  loadRequests() {
    this.loading = true; this.message = null;
    const eid = this.userSvc.getEmployeeId() ?? undefined;
    this.attendanceSvc.listRequests(eid).subscribe({
      next: (res: any) => {
        this.loading = false;
        this.requests = (res && res.success && res.data) ? res.data : [];
      }, error: (err: any) => { this.loading = false; this.message = err?.message || 'Failed to load'; }
    });
  }

  onSubmitRequest(row: any) {
    // Use row values where available; fallback to current form values
    const date = row?.Date || row?.date || row?.requestDate || this.formatDate(this.reqDate || new Date());
    const type = row?.RequestType || row?.request_type || this.reqType;
    const reason = row?.Reason || row?.reason || this.reqReason || '';
    const eid = row?.EmployeeID || row?.employee_id || this.userSvc.getEmployeeId();
    this.loading = true; this.message = null;
    this.attendanceSvc.createRequest(date, type, reason, eid).subscribe({ next: (res:any) => {
      this.loading = false;
      if (res && res.success) {
        this.message = 'Request submitted';
        this.loadRequests();
      } else {
        this.message = res?.message || 'Submit failed';
      }
    }, error: (err:any) => { this.loading = false; this.message = err?.message || 'Submit failed'; }});
  }

  onApproveRequest(row: any) {
    const id = row?.AttendanceRequestID || row?.id || row?.AttendanceRequestId;
    if (!id) { this.message = 'Request id missing'; return; }
    this.loading = true; this.message = null;
    this.attendanceSvc.approveRequest(Number(id)).subscribe({ next: (res:any) => {
      this.loading = false;
      if (res && res.success) { this.message = 'Request approved'; this.loadRequests(); }
      else { this.message = res?.message || 'Approve failed'; }
    }, error: (err:any) => { this.loading = false; this.message = err?.message || 'Approve failed'; }});
  }

  onRejectRequest(row: any) {
    const id = row?.AttendanceRequestID || row?.id || row?.AttendanceRequestId;
    if (!id) { this.message = 'Request id missing'; return; }
    this.loading = true; this.message = null;
    this.attendanceSvc.rejectRequest(Number(id)).subscribe({ next: (res:any) => {
      this.loading = false;
      if (res && res.success) { this.message = 'Request rejected'; this.loadRequests(); }
      else { this.message = res?.message || 'Reject failed'; }
    }, error: (err:any) => { this.loading = false; this.message = err?.message || 'Reject failed'; }});
  }

  onDeleteRequest(row: any) {
    const id = row?.AttendanceRequestID || row?.id || row?.AttendanceRequestId;
    if (!id) { this.message = 'Request id missing'; return; }
    this.loading = true; this.message = null;
    this.attendanceSvc.cancelRequest(Number(id)).subscribe({ next: (res:any) => {
      this.loading = false;
      if (res && res.success) { this.message = 'Request deleted'; this.loadRequests(); }
      else { this.message = res?.message || 'Delete failed'; }
    }, error: (err:any) => { this.loading = false; this.message = err?.message || 'Delete failed'; }});
  }

  resetForm() {
    // restore default values
    this.reqDate = new Date();
    this.reqType = 'Leave';
    this.reqReason = '';
    this.message = null;
    this.evaluateSelectedDate();
  }

  private loadCalendar() {
    // load holidays and weekly offs for UI hints
    this.calendarSvc.getHolidays().subscribe({
      next: (h: any[]) => {
        // normalize holidays to a consistent shape: { date: 'YYYY-MM-DD', type: 'Holiday' | 'WorkingDay' }
        this.holidays = (h || []).map(hd => this.normalizeHoliday(hd)).filter(x => x && x.date);
        // re-evaluate selected date after holidays load
        this.evaluateSelectedDate();
      }, error: () => { this.holidays = []; this.evaluateSelectedDate(); }
    });
    this.calendarSvc.getWeeklyOffs().subscribe({ next: (w: any[]) => { this.weeklyOffs = (w || []).map(x => Number(x.DayOfWeek ?? x.day_of_week ?? x)); this.evaluateSelectedDate(); }, error: () => { this.weeklyOffs = []; this.evaluateSelectedDate(); } });
  }

  // Normalize various backend holiday shapes into { date: 'YYYY-MM-DD', type }
  private normalizeHoliday(h: any): { date: string | null, type: string | null } {
    if (!h) return { date: null, type: null };
    const raw = h.Date ?? h.date ?? h.StartDate ?? h.DateString ?? h;
    let dateStr: string | null = null;
    if (typeof raw === 'string') {
      dateStr = raw.split('T')[0].split(' ')[0];
      // if YYYY-MM-DD already, ok; otherwise try parsing and formatting
      const ymd = dateStr.match(/^\d{4}-\d{2}-\d{2}$/);
      if (!ymd) {
        const parsed = this.parseToLocalDate(raw);
        if (parsed) dateStr = this.formatDate(parsed);
        else dateStr = null;
      }
    } else if (raw instanceof Date || typeof raw === 'number') {
      const dt = this.parseToLocalDate(raw);
      dateStr = dt ? this.formatDate(dt) : null;
    }
    const type = (h.Type ?? h.type ?? h.TypeName ?? h.typeName) || 'Holiday';
    return { date: dateStr, type };
  }

  evaluateSelectedDate() {
    if (!this.reqDate) { this.selectedDateStatus = null; return; }
    const dt = this.reqDate instanceof Date ? this.reqDate : new Date(String(this.reqDate));
    const ymd = this.formatDate(dt);
    // check holidays (normalized shape from loadCalendar: { date: 'YYYY-MM-DD', type })
    const h = this.holidays.find(hd => {
      if (!hd) return false;
      // prefer normalized 'date' property
      if (hd.date && hd.date === ymd) return true;
      // fallback to legacy fields
      const raw = (hd.Date || hd.date || '');
      return raw.split('T')[0] === ymd;
    });
    if (h) { this.selectedDateStatus = (h.type || h.Type || 'Holiday'); return; }
    // check weekly off (map day-of-week; JS getDay: 0=Sun..6=Sat; app stores 1=Mon..7=Sun)
    try {
      const dow = dt.getDay();
      const dowApp = dow === 0 ? 7 : dow; // convert
      if (this.weeklyOffs.includes(dowApp)) { this.selectedDateStatus = 'WeeklyOff'; return; }
    } catch (e) { }
    // check academic year bounds if available
    if (this.academicStart || this.academicEnd) {
      try {
        const ds = this.academicStart ? new Date(this.academicStart) : null;
        const de = this.academicEnd ? new Date(this.academicEnd) : null;
        // compare using date-only values
        const dtOnly = new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
        const dsOnly = ds ? new Date(ds.getFullYear(), ds.getMonth(), ds.getDate()) : null;
        const deOnly = de ? new Date(de.getFullYear(), de.getMonth(), de.getDate()) : null;
        if ((dsOnly && dtOnly < dsOnly) || (deOnly && dtOnly > deOnly)) {
          this.selectedDateStatus = 'OutOfAcademicRange';
          return;
        }
      } catch (e) { }
    }
    this.selectedDateStatus = 'WorkingDay';
    // keep only selected status
  }
}
