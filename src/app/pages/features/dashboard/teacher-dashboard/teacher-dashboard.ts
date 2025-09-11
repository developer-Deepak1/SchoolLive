import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions } from 'chart.js';
import { AcademicCalendarService } from '@/pages/features/services/academic-calendar.service';

// PrimeNG Imports
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { TagModule } from 'primeng/tag';
import { PanelModule } from 'primeng/panel';
import { ProgressBarModule } from 'primeng/progressbar';
import { AvatarModule } from 'primeng/avatar';
import { BadgeModule } from 'primeng/badge';
import { ToastModule } from 'primeng/toast';
import { RippleModule } from 'primeng/ripple';
import { HttpClientModule } from '@angular/common/http';
import { DashboardService } from '../../../../services/dashboard.service';
import { UserService } from '@/services/user.service';
import { EmployeeAttendanceService } from '@/pages/features/services/employee-attendance.service';
// Removed assignment feature modules

@Component({
  selector: 'app-teacher-dashboard',
  standalone: true,
  imports: [
    CommonModule,
  BaseChartDirective,
    ButtonModule,
    CardModule,
    TagModule,
    PanelModule,
    ProgressBarModule,
    AvatarModule,
    BadgeModule,
    ToastModule,
    RippleModule,
  HttpClientModule,
  // extra modules for suggestion (c)
  // removed DialogModule, InputTextModule, Forms/Reactive modules after assignment feature removal
  ],
  templateUrl: './teacher-dashboard.html',
  styleUrl: './teacher-dashboard.scss'
})
export class TeacherDashboard implements OnInit {
  loading = false;
  loadError: string | null = null;
  firstName: string = '';
  private userService = inject(UserService);
  private calendarSvc = inject(AcademicCalendarService);
  private attendanceSvc = inject(EmployeeAttendanceService);
  // cached calendar data
  private cachedHolidays: Map<string, any> | null = null;
  private cachedWeeklyOffs: Set<number> | null = null;
  // Attendance / sign in-out state
  isHoliday = false;
  isWeeklyOff = false;
  attendance: any = null; // { SignIn?: string, SignOut?: string, TotalHours?: string }
  signingIn = false;
  signingOut = false;
  canSignIn = true;
  canSignOut = false;
  // today's date for header display
  today: Date = new Date();

  // Aggregated quick stats (derived)
  quickStats = {
    totalClasses: 0,
    totalStudents: 0,
    averageAttendance: 0,
    rating: 0
  };

  // Charts (reuse available global data until API supplies per-teacher granularity)
  attendanceOverviewData: ChartConfiguration<'doughnut'>['data'] = {
    labels: ['Present', 'Absent', 'Late'],
    datasets: [
      {
        data: [0, 0, 0],
        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
        borderWidth: 2,
        borderColor: '#ffffff'
      }
    ]
  };

  attendanceOverviewOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: { display: true, text: 'Daily Attendance Overview' },
      legend: { position: 'bottom' }
    }
  };

  gradeDistributionData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };
  gradeDistributionOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: { display: true, text: 'Recent Grade Distribution (All Classes)' },
      legend: { display: false }
    },
    scales: { y: { beginAtZero: true } }
  };

  classAttendanceStackData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] }; // present vs absent
  classAttendanceStackOptions: ChartOptions<'bar'> = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { title: { display: true, text: "Today's Attendance by Class" } },
    scales: {
      x: { stacked: true, beginAtZero: true, title: { display: true, text: 'Students' } },
      y: { stacked: true }
    }
  };

  upcomingEvents: any[] = [];
  recentActivities: any[] = [];
  // assignments feature removed

  constructor(public dashboardApi: DashboardService) {}

  // Assignment feature removed

  ngOnInit() {
    this.loadFromApi();
    this.firstName = this.userService.getFirstName() || 'Teacher';
    // fetch today's attendance/status for current employee
    this.initCalendarCache();
    // attempt to load today's attendance from backend when employee id is available
    const eid = this.userService.getEmployeeId();
    if (eid) {
      // ask backend for today's persisted attendance
      const today = new Date().toISOString().slice(0,10);
      this.attendanceSvc.getToday(today, eid).subscribe({ next: (res:any) => {
        if (res && res.success && res.data) {
          this.attendance = res.data;
        }
        try { this.loadTodayAttendance(); } catch(e) {}
      }, error: () => { try { this.loadTodayAttendance(); } catch(e) {} }});
    } else {
      // still evaluate holiday/off rules using cached calendar; attendance will be local until login resolves
      try { this.loadTodayAttendance(); } catch(e) {}
    }
  }

  private loadTodayAttendance() {
    const todayYmd = new Date().toISOString().slice(0,10);

    const handleLists = (holidays: any[], offs: any[]) => {
      const holidayMap = new Map<string, any>();
      (holidays || []).forEach(h => { const d = (h.Date || h.date || '').split('T')[0]; if (d) holidayMap.set(d, h); });
      const offSet = new Set((offs||[]).map((o:any)=>o.DayOfWeek || o.day_of_week || o));

      // persist to cache for future checks
      if (!this.cachedHolidays) {
        const m = new Map<string, any>(); holidayMap.forEach((v,k)=>m.set(k,v)); this.cachedHolidays = m;
      }
      if (!this.cachedWeeklyOffs) {
        this.cachedWeeklyOffs = new Set(Array.from(offSet));
      }

      // check holiday
      if (holidayMap.has(todayYmd)) {
        const h = holidayMap.get(todayYmd); const type = (h.Type || h.type || 'Holiday');
        if (String(type).toLowerCase() !== 'workingday') { this.isHoliday = true; this.canSignIn = false; this.canSignOut = false; return; }
      }
      // check weekly off, but allow holiday entries to override as 'workingday'
      try {
        const dt = new Date(todayYmd);
        const dow = dt.getDay();
        const dow1 = dow===0?7:dow;
        // If a holiday exists for today and it's explicitly a working day, do not treat as weekly off
        const h = holidayMap.get(todayYmd);
        const isWorkingOverride = h ? String((h.Type || h.type || '')).toLowerCase() === 'workingday' : false;
        if (!isWorkingOverride && offSet.has(dow1)) { this.isWeeklyOff = true; this.canSignIn = false; this.canSignOut = false; return; }
      } catch(e){}

      // Evaluate button enable/disable based on local attendance object
      if (this.attendance && this.attendance.SignIn) {
        this.canSignIn = false;
        this.canSignOut = !this.attendance.SignOut;
      } else {
        this.canSignIn = true; this.canSignOut = false;
      }
    };

    if (this.cachedHolidays && this.cachedWeeklyOffs) {
      // use cache
      const holidays = Array.from(this.cachedHolidays.keys()).map(k => this.cachedHolidays!.get(k));
      const offs = Array.from(this.cachedWeeklyOffs.values());
      handleLists(holidays as any[], offs as any[]);
    }
  }

  loadFromApi() {
    this.loading = true;
    this.loadError = null;
    this.dashboardApi.getSummary().subscribe({
      next: (res: any) => {
        if (res.success && res.data) {
            this.quickStats = res.data.stats;
            // Reuse global charts as fallback
            if (res.data.charts?.attendanceOverview) {
              this.attendanceOverviewData = res.data.charts.attendanceOverview as any;
            }
            if (res.data.charts?.classAttendance) {
              this.classAttendanceStackData = res.data.charts.classAttendance as any;
            }
        } else {
          this.loadError = res.message || 'Unknown error';
        }
        this.loading = false;
      },
      error: (err: any) => {
        this.loadError = err?.message || 'Failed to load dashboard';
        this.loading = false;
      }
    });
  }

  // Sign in - POST to backend employee attendance endpoint
  signIn() {
    if (!this.canSignIn) return;
    this.signingIn = true;
    try {
      const date = new Date().toISOString().slice(0,10);
  // include current employee id if known so backend can persist against the correct employee
  const eid = this.userService.getEmployeeId() ?? undefined;
  this.attendanceSvc.signIn(date, eid).subscribe({ next: () => {}, error: () => {} });
      // update UI
      this.attendance = this.attendance || {};
      this.attendance.SignIn = new Date().toISOString();
      this.canSignIn = false; this.canSignOut = true;
    } catch (e) {
      // noop
    } finally { this.signingIn = false; }
  }

  // Sign out - POST to backend employee attendance endpoint and compute total hours
  signOut() {
    if (!this.canSignOut) return;
    this.signingOut = true;
    try {
      const date = new Date().toISOString().slice(0,10);
  const eid = this.userService.getEmployeeId() ?? undefined;
  // fire-and-forget sign-out call
  this.attendanceSvc.signOut(date, eid).subscribe({ next: () => {}, error: () => {} });

      this.attendance = this.attendance || {};
      this.attendance.SignOut = new Date().toISOString();
      // compute hours difference if SignIn exists
      if (this.attendance.SignIn) {
        const inDt = new Date(this.attendance.SignIn);
        const outDt = new Date(this.attendance.SignOut);
        const diffMs = Math.max(0, outDt.getTime() - inDt.getTime());
        const hours = Math.floor(diffMs / (1000*60*60));
        const mins = Math.floor((diffMs % (1000*60*60)) / (1000*60));
        this.attendance.TotalHours = `${hours}h ${mins}m`;
      }
      this.canSignOut = false;
      this.canSignIn = false;
    } finally { this.signingOut = false; }
  }

  // Initialize calendar caches (holidays and weekly offs)
  private initCalendarCache() {
    const ayId = null;
    this.calendarSvc.getHolidays(ayId).subscribe({ next: (list:any[]) => {
      const map = new Map<string, any>();
      (list || []).forEach(h => {
        const d = (h.Date || h.date || '').split('T')[0];
        if (d) map.set(d, h);
      });
      this.cachedHolidays = map;
  // re-evaluate today's attendance/holiday state when cache arrives
  try { this.loadTodayAttendance(); } catch (e) { }
    }, error: () => { this.cachedHolidays = new Map(); }});

    this.calendarSvc.getWeeklyOffs().subscribe({ next: (offs:any[]) => {
      const set = new Set<number>();
      (offs || []).forEach(o => {
        const val = o.DayOfWeek ?? o.day_of_week ?? o.day ?? o;
        const n = parseInt(String(val), 10);
        if (!isNaN(n)) set.add(n);
      });
      this.cachedWeeklyOffs = set;
  // re-evaluate today's attendance/holiday state when weekly offs cache arrives
  try { this.loadTodayAttendance(); } catch (e) { }
    }, error: () => { this.cachedWeeklyOffs = new Set(); }});
  }

  refresh() {
    this.dashboardApi.refreshNow();
    this.loadFromApi();
  }

}
