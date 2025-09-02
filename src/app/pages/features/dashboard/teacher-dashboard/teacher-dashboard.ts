import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions } from 'chart.js';

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

  // Teacher specific snapshot
  teacherStats: any = {
    name: '-',
    subject: '-',
    classes: 0,
    students: 0,
    rating: 0,
    attendance: 0,
    experience: '-'
  };

  // Aggregated quick stats (derived)
  quickStats = {
    totalClasses: 0,
    totalStudents: 0,
    avgAttendance: 0,
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
      title: { display: true, text: 'My Classes Attendance Today' },
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
  }

  loadFromApi() {
    this.loading = true;
    this.loadError = null;
    this.dashboardApi.getSummary().subscribe({
      next: (res: any) => {
        if (res.success && res.data) {
          const teachers: any[] = res.data.teacherPerformance || [];
          // Pick first teacher as the current user placeholder.
          if (teachers.length) {
            this.teacherStats = { ...teachers[0] };
            this.quickStats.totalClasses = teachers[0].classes;
            this.quickStats.totalStudents = teachers[0].students;
            this.quickStats.avgAttendance = teachers[0].attendance;
            this.quickStats.rating = teachers[0].rating;
          }

            // Reuse global charts as fallback
            if (res.data.charts?.attendanceOverview) {
              this.attendanceOverviewData = res.data.charts.attendanceOverview as any;
            }
            if (res.data.charts?.gradeDistribution) {
              this.gradeDistributionData = res.data.charts.gradeDistribution as any;
            }
            if (res.data.charts?.classAttendance) {
              this.classAttendanceStackData = res.data.charts.classAttendance as any;
            }
            this.recentActivities = (res.data.recentActivities || []).slice(0, 5);
            this.upcomingEvents = (res.data.upcomingEvents || []).slice(0, 4);
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

  refresh() {
    this.dashboardApi.refreshNow();
    this.loadFromApi();
  }

  // Export grades (gradeDistributionData) to CSV
  exportGrades() {
    if (!this.gradeDistributionData.labels?.length || !this.gradeDistributionData.datasets?.length) {
      // nothing to export
      return;
    }
    const dataset = this.gradeDistributionData.datasets[0] as any;
    const rows: string[] = ['Grade,Count'];
    this.gradeDistributionData.labels.forEach((label: any, idx: number) => {
      const val = Array.isArray(dataset.data) ? dataset.data[idx] : '';
      rows.push(`${label},${val}`);
    });
    const csv = rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'grades-export.csv';
    a.click();
    URL.revokeObjectURL(url);
  }

  getSeverity(activityType: string): string {
    switch (activityType) {
      case 'exam':
      case 'grade':
        return 'success';
      case 'attendance':
        return 'warning';
      default:
        return 'info';
    }
  }

  trackById(_: number, item: any) { return item.id; }
}
