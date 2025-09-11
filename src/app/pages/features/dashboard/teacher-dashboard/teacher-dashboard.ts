import { Component, inject, OnInit } from '@angular/core';
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
import { UserService } from '@/services/user.service';
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

  refresh() {
    this.dashboardApi.refreshNow();
    this.loadFromApi();
  }

}
