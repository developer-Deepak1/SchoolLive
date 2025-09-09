import { Component, HostListener, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AcademicCalendarService } from '@/pages/features/services/academic-calendar.service';
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

  // holiday map for current month keyed by 'YYYY-MM-DD'
  holidayMap: Map<string, any> = new Map();

  // cached calendar data to avoid repeated network calls
  private cachedHolidays: Map<string, any> | null = null;
  private cachedWeeklyOffs: Set<number> | null = null;

  rows: AttendanceRow[] = [];

  // client-side search filter (search by student name)
  filterText: string = '';

  get filteredRows(): AttendanceRow[] {
    const t = (this.filterText || '').trim().toLowerCase();
    if (!t) return this.rows || [];
    return (this.rows || []).filter(r => (r.studentName || '').toLowerCase().includes(t));
  }

  isMobile = false;
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
    this.initCalendarCache();
    this.updateGrid();
    this.checkMobile();
  }

  // initialize and cache holidays and weekly-offs once per session
  private initCalendarCache() {
    const ayId = null;
    // fetch holidays
    this.academicSvc.getHolidays(ayId).subscribe({ next: (list:any[]) => {
      const map = new Map<string, any>();
      (list || []).forEach(h => {
        const d = (h.Date || h.date || '').split('T')[0];
        if (d) map.set(d, h);
      });
      this.cachedHolidays = map;
    }, error: () => { this.cachedHolidays = new Map(); }});

    // fetch weekly offs
    this.academicSvc.getWeeklyOffs().subscribe({ next: (offs:any[]) => {
      const set = new Set<number>();
      (offs || []).forEach(o => {
        const val = o.DayOfWeek ?? o.day_of_week ?? o.day ?? o;
        const n = parseInt(String(val), 10);
        if (!isNaN(n)) set.add(n);
      });
      this.cachedWeeklyOffs = set;
    }, error: () => { this.cachedWeeklyOffs = new Set(); }});
  }

  // compute number of days for selected month/year and rebuild sample grid
  updateGrid() {
    const year = this.date.getFullYear();
    const month = this.date.getMonth(); // 0-based
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    this.days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
    // Only load holiday and attendance data when both class and section are selected
    if (this.selectedClass && this.selectedSection) {
      // load holidays for month and then build rows from API
      this.loadHolidaysForMonth(year, month).then(() => this.loadMonthData(daysInMonth));
    } else {
      // clear any existing data if filters not selected
      this.holidayMap.clear();
      this.rows = [];
    }
  }

  // inject AcademicCalendarService to fetch holidays
  private academicSvc = inject(AcademicCalendarService);
  // inject AttendanceService to fetch daily attendance
  private attendanceSvc = inject(AttendanceService);
  // inject StudentsService for classes/sections
  private studentsSvc = inject(StudentsService);

  constructor() {
    this.loadClasses();
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

  async loadHolidaysForMonth(year: number, monthZeroBased: number) {
    this.holidayMap.clear();
    try {
      const first = new Date(year, monthZeroBased, 1).toISOString().split('T')[0];
      const last = new Date(year, monthZeroBased + 1, 0).toISOString().split('T')[0];
      // use cached holidays if available, otherwise fetch and cache once
      if (!this.cachedHolidays) {
        try {
          const res: any = await firstValueFrom(this.academicSvc.getHolidays(null));
          const list = Array.isArray(res) ? res : (res && res.data ? res.data : res);
          const map = new Map<string, any>();
          (list || []).forEach((h: any) => {
            const d = (h.Date || h.date || '').split('T')[0];
            if (d) map.set(d, h);
          });
          this.cachedHolidays = map;
        } catch (e) {
          this.cachedHolidays = new Map();
        }
      }

      // populate holidayMap from cachedHolidays for the requested month
      (this.cachedHolidays || new Map()).forEach((h: any, k: string) => {
        if (k >= first && k <= last) this.holidayMap.set(k, h);
      });

      // use cached weekly offs if available, otherwise fetch and cache once
      if (!this.cachedWeeklyOffs) {
        try {
          const offs: any = await firstValueFrom(this.academicSvc.getWeeklyOffs(null));
          const offSet = new Set<number>();
          (offs || []).forEach((o: any) => {
            const val = o.DayOfWeek ?? o.day_of_week ?? o.day ?? o;
            const n = parseInt(String(val), 10);
            if (!isNaN(n)) offSet.add(n);
          });
          this.cachedWeeklyOffs = offSet;
        } catch (e) {
          this.cachedWeeklyOffs = new Set();
        }
      }

      // mark weekly-offs for the month based on cachedWeeklyOffs
      if (this.cachedWeeklyOffs && this.cachedWeeklyOffs.size > 0) {
        const endDay = new Date(year, monthZeroBased + 1, 0).getDate();
        for (let d = 1; d <= endDay; d++) {
          const dt = new Date(year, monthZeroBased, d);
          const dow = dt.getDay();
          const dow1 = dow === 0 ? 7 : dow;
          if (this.cachedWeeklyOffs.has(dow1)) {
            const ymd = dt.toISOString().split('T')[0];
            if (!this.holidayMap.has(ymd)) this.holidayMap.set(ymd, { Type: 'WeeklyOff', Title: 'Weekly Off' });
          }
        }
      }
    } catch (e) {
      // ignore and leave map empty
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
      // map API records to AttendanceRow
      this.rows = (records || []).map((r: any) => {
        const statusesFromApi = Array.isArray(r.statuses) ? r.statuses : (r.statuses || []);
        // convert statuses to short codes and ensure length
        const statuses = Array.from({ length: daysInMonth }, (_, i) => {
          const v = statusesFromApi[i];
          if (v == null) return this.holidayMap.has(new Date(this.date.getFullYear(), this.date.getMonth(), i+1).toISOString().split('T')[0]) ? '*' : '-';
          return this.toShortLower(v);
        });
        return { studentName: r.StudentName || r.StudentName || 'Unknown', className: r.ClassName || String(this.selectedClass || '—'), statuses, remarks: r.Remarks || '' } as AttendanceRow;
      });
    } catch (e) {
      // fallback to empty rows on error
      this.rows = [];
    }
  }

  randomStatus(): string {
    // return 'p' (present), 'l' (late), 'h' (half-day), or 'a' (absent)
  const pool = ['p', 'p', 'p', 'l', 'h', 'a', '-'];
    return pool[Math.floor(Math.random() * pool.length)];
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

  getSeverity(status: string) {
    switch ((status || '').toLowerCase()) {
      case 'p': return 'success';
      case 'l': return 'warning';
      case 'h': return 'info';
      case 'a': return 'danger';
  case '*': return 'info';
  case '-': return null;
      default: return null;
    }
  }

  // convert verbose status to short single-letter lower-case code used in template
  toShortLower(status: string): string {
    if (!status) return '-';
    const s = String(status).toLowerCase();
    if (s === 'present' || s === 'p') return 'p';
    if (s === 'leave' || s === 'l') return 'l';
    if (s === 'halfday' || s === 'half-day' || s === 'h' || s === 'half day') return 'h';
    if (s === 'absent' || s === 'a') return 'a';
    if (s === 'holiday' || s === '*') return '*';
    return '-';
  }

  @HostListener('window:resize')
  checkMobile() {
    this.isMobile = window.innerWidth <= 768; // tweak breakpoint
  }

  openDetails(r: AttendanceRow) { this.selected = r; this.detailDialogVisible = true; }
}
