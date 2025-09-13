import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions } from 'chart.js';
import { HttpClientModule } from '@angular/common/http';

// PrimeNG UI Modules
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { TagModule } from 'primeng/tag';
import { TableModule } from 'primeng/table';
import { ProgressBarModule } from 'primeng/progressbar';
import { BadgeModule } from 'primeng/badge';
import { RippleModule } from 'primeng/ripple';

import { StudentDashboardService, StudentDashboardResponse } from '../../../../services/student-dashboard.service';
import { UserService } from '../../../../services/user.service';
import { StudentsService } from '../../services/students.service';

interface StudentQuickStat {
  key: string;
  label: string;
  icon: string;
  color: string; // tailwind text color base e.g. 'blue'
  value: number | string;
  suffix?: string;
  hint?: string;
}

@Component({
  selector: 'app-student-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    BaseChartDirective,
    HttpClientModule,
    CardModule,
    ButtonModule,
    TagModule,
    TableModule,
    ProgressBarModule,
    BadgeModule,
    RippleModule
  ],
  templateUrl: './student-dashboard.html',
  styleUrl: './student-dashboard.scss'
})
export class StudentDashboard implements OnInit {
  loading = false;
  loadError: string | null = null;
  todayRemarks: string | null = null;
  todayStatus: string | null = null;

  // Quick stats pulled from backend student summary
  quickStats: StudentQuickStat[] = [
    { key: 'attendance', label: 'Avg Attendance', icon: 'pi pi-calendar', color: 'purple', value: 0, suffix: '%', hint: 'Academic Year' }
  ];

  // Attendance donut (using school attendance for now)
  attendanceChartData: ChartConfiguration<'doughnut'>['data'] = { labels: [], datasets: [] };
  attendanceChartOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: { display: true, text: 'Today\'s Attendance' },
      legend: { position: 'bottom' }
    }
  };

  // Monthly attendance line chart (school-wide until per-student endpoint is added)
  monthlyAttendanceChartData: ChartConfiguration<'line'>['data'] = { labels: [], datasets: [] };
  monthlyAttendanceChartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { title: { display: true, text: 'Monthly Attendance %' }, legend: { display: true } },
    scales: {
      y: { beginAtZero: true, max: 100, title: { display: true, text: '%' } }
    }
  };

  constructor(private studentApi: StudentDashboardService, private userService: UserService, private students: StudentsService) {}

  ngOnInit(): void {
    this.loadFromApi();
  }

  loadFromApi() {
    this.loading = true;
    this.loadError = null;
    // Resolve student id (sync or via API) then call summary once with the resolved id
    const resolveStudentId = (): Promise<number|null> => {
      const s = this.userService.getStudentId();
      if (s) return Promise.resolve(s);
      return new Promise((resolve) => {
        this.students.getStudentId().subscribe({ next: (r:any) => { resolve(r?.data?.student_id ?? r?.student_id ?? null); }, error: () => resolve(null) });
      });
    };

    const callSummary = (sid?: number|null) => {
      this.studentApi.getSummary(sid ?? undefined).subscribe({
      next: (res: StudentDashboardResponse) => {
        if (!res.success) {
          this.loadError = res.message || 'Failed to load summary';
          this.loading = false;
          return;
        }
        // Average stats
        if (res.data?.stats) {
          this.setQuickStat('attendance', (res.data.stats.averageAttendance ?? 0).toFixed(1));
        }
        // Prefer using the summary payload's monthlyAttendance when present to avoid an extra API call.
        const summaryMonthly = res.data?.charts?.monthlyAttendance;
        if (summaryMonthly && (summaryMonthly.labels?.length || 0) > 0) {
          this.monthlyAttendanceChartData = summaryMonthly as any;
        } else if (sid) {
          // Only call the per-student monthly endpoint when the summary doesn't include the chart
          this.students.getStudentMonthlyAttendance(sid).subscribe({ next: (m) => { this.monthlyAttendanceChartData = m as any; }, error: () => { this.monthlyAttendanceChartData = { labels: [], datasets: [] }; } });
        } else {
          this.monthlyAttendanceChartData = { labels: [], datasets: [] };
        }

        // Build today's attendance donut from the API if available
        const today = res.data?.today;
        this.todayRemarks = today?.remarks ?? null;
        this.todayStatus = null;
        if (today && (today.status || today.remarks)) {
          // Normalize status (trim, case-insensitive) and support common shorthand
          const raw = (today.status || '').toString().trim();
          const s = raw.toLowerCase();
          const buckets = { Present: 0, HalfDay: 0, Leave: 0, Absent: 0 } as any;
          if (s === 'present' || s === 'p') { buckets.Present = 1; this.todayStatus = 'Present'; }
          else if (s === 'halfday' || s === 'half day' || s === 'h') { buckets.HalfDay = 1; this.todayStatus = 'HalfDay'; }
          else if (s === 'leave' || s === 'l') { buckets.Leave = 1; this.todayStatus = 'Leave'; }
          else if (s === 'absent' || s === 'a') { buckets.Absent = 1; this.todayStatus = 'Absent'; }
          else {
            // If status is unrecognized, try to infer from remarks (e.g. 'on leave')
            const remarks = (today.remarks || '').toString().toLowerCase();
            if (remarks.includes('leave') || remarks.includes('on leave')) { buckets.Leave = 1; this.todayStatus = 'Leave'; }
            else if (remarks.includes('half')) { buckets.HalfDay = 1; this.todayStatus = 'HalfDay'; }
            else if (remarks.includes('absent') || remarks.includes('sick')) { buckets.Absent = 1; this.todayStatus = 'Absent'; }
            else if (remarks.includes('present') || remarks.includes('in class') || remarks.includes('on time')) { buckets.Present = 1; this.todayStatus = 'Present'; }
            else { /* NotMarked - will fall back below to average */ }
          }

          // If we were able to set a bucket, use the explicit 4-bucket donut
          const total = buckets.Present + buckets.HalfDay + buckets.Leave + buckets.Absent;
          if (total > 0) {
            this.attendanceChartData = {
              labels: ['Present','HalfDay','Leave','Absent'],
              datasets: [{ data: [buckets.Present, buckets.HalfDay, buckets.Leave, buckets.Absent], backgroundColor: ['#10b981','#f59e0b','#f97316','#ef4444'] }]
            } as any;
          } else {
            // Unclear today marker, fall back to average-based donut
            const avg = Number(res.data?.stats?.averageAttendance ?? 0);
            const present = Math.max(0, Math.min(100, Math.round(avg)));
            const absent = 100 - present;
            this.attendanceChartData = {
              labels: ['Present','Absent'],
              datasets: [{ data: [present, absent], backgroundColor: ['#10b981','#ef4444'] }]
            } as any;
          }
        } else {
          // Fallback to average-based donut
          const avg = Number(res.data?.stats?.averageAttendance ?? 0);
          const present = Math.max(0, Math.min(100, Math.round(avg)));
          const absent = 100 - present;
          this.attendanceChartData = {
            labels: ['Present','Absent'],
            datasets: [{ data: [present, absent], backgroundColor: ['#10b981','#ef4444'] }]
          } as any;
        }

        this.loading = false;
      },
      error: (err) => {
        this.loadError = err?.message || 'Network error';
        this.loading = false;
      }
      });
    };

    resolveStudentId().then(id => callSummary(id));
  }

  refresh() {
  this.studentApi.refreshNow();
    this.loadFromApi();
  }

  setQuickStat(key: string, value: any) {
    const stat = this.quickStats.find(s => s.key === key);
    if (stat) stat.value = value;
  }

  getSeverityForActivity(type: string): string {
    switch(type) {
      case 'exam': return 'warn';
      case 'attendance': return 'info';
      case 'assignment': return 'success';
      default: return 'info';
    }
  }
}
