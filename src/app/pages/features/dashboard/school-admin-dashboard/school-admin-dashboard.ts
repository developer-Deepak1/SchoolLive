import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions } from 'chart.js';

// PrimeNG Imports
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { TagModule } from 'primeng/tag';
import { PanelModule } from 'primeng/panel';
import { ProgressBarModule } from 'primeng/progressbar';
import { AvatarModule } from 'primeng/avatar';
import { BadgeModule } from 'primeng/badge';
import { ToastModule } from 'primeng/toast';
import { RippleModule } from 'primeng/ripple';
import { DashboardService } from '../../../../services/dashboard.service';
import { HttpClientModule } from '@angular/common/http';

@Component({
  selector: 'app-school-admin-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    BaseChartDirective,
    ButtonModule,
    CardModule,
    TableModule,
    TagModule,
    PanelModule,
    ProgressBarModule,
    AvatarModule,
    BadgeModule,
    ToastModule,
  RippleModule,
  HttpClientModule
  ],
  templateUrl: './school-admin-dashboard.html',
  styleUrl: './school-admin-dashboard.scss'
})
export class SchoolAdminDashboard implements OnInit {
  loading = false;
  loadError: string | null = null;

  // Dashboard Statistics
  dashboardStats: any = {
    totalStudents: 0,
    totalTeachers: 0,
    totalStaff: 0,
    totalClasses: 0,
    averageAttendance: 0,
    pendingFees: 0,
    upcomingEvents: 0,
    totalRevenue: 0
  };

  // Student Enrollment Chart
  enrollmentChartData: ChartConfiguration<'line'>['data'] = { labels: [], datasets: [] };

  enrollmentChartOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: 'Student Enrollment Trend'
      },
      legend: {
        display: true,
        position: 'top'
      }
    },
    scales: {
      y: {
        beginAtZero: true
      }
    }
  };

  // Attendance Chart (start empty; populated from API)
  attendanceChartData: ChartConfiguration<'doughnut'>['data'] = { labels: [], datasets: [] };

  attendanceChartOptions: ChartOptions<'doughnut'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: 'Daily Attendance Overview'
      },
      legend: {
        position: 'bottom'
      }
    }
  };

  // Grade Distribution Chart
  gradeChartData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };

  gradeChartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: 'Grade Distribution'
      },
      legend: {
        display: false
      }
    },
    scales: {
      y: {
        beginAtZero: true
      }
    }
  };

  // Revenue Chart
  revenueChartData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };

  revenueChartOptions: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: 'Monthly Revenue Breakdown'
      },
      legend: {
        position: 'top'
      }
    },
    scales: {
      x: {
        stacked: true
      },
      y: {
        stacked: true,
        beginAtZero: true
      }
    }
  };

  // Today's Present Students Class-wise (Horizontal Bar)
  // Class attendance data starts empty and is populated by API
  classAttendanceChartData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };

  classAttendanceChartOptions: ChartOptions<'bar'> = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: {
        display: true,
        text: "Today's Attendance (Present vs Absent)"
      },
      legend: { display: true },
      tooltip: {
        mode: 'index',
        intersect: false,
        callbacks: {
          label: (ctx) => `${ctx.dataset.label}: ${ctx.parsed.x}`
        }
      }
    },
    scales: {
      x: {
        stacked: true,
        beginAtZero: true,
        title: { display: true, text: 'Students' },
        ticks: { precision: 0 }
      },
      y: {
        stacked: true,
        title: { display: true, text: 'Class' }
      }
    }
  };

  // Class Gender Distribution Chart (stacked horizontal)
  classGenderChartData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };

  classGenderChartOptions: ChartOptions<'bar'> = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      title: { display: true, text: 'Student Count by Class and Gender' },
      legend: { position: 'top' }
    },
    scales: {
      x: { stacked: true, beginAtZero: true, title: { display: true, text: 'Students' } },
      y: { stacked: true }
    }
  };

  // Recent Activities (empty until API populates)
  recentActivities: Array<any> = [];

  // Top Performing Classes
  topClasses: Array<any> = [];

  // Upcoming Events (empty until API populates)
  upcomingEvents: Array<any> = [];

  // Teacher Performance
  teacherPerformance = [
    {
      name: 'Dr. Smith Johnson',
      subject: 'Mathematics',
      classes: 4,
      students: 128,
      rating: 4.8,
      attendance: 98.5,
      experience: '15 years'
    },
    {
      name: 'Prof. Emily Davis',
      subject: 'English Literature',
      classes: 3,
      students: 95,
      rating: 4.7,
      attendance: 97.2,
      experience: '12 years'
    },
    {
      name: 'Mr. Michael Brown',
      subject: 'Physics',
      classes: 5,
      students: 145,
      rating: 4.6,
      attendance: 96.8,
      experience: '10 years'
    },
    {
      name: 'Ms. Sarah Wilson',
      subject: 'Chemistry',
      classes: 4,
      students: 118,
      rating: 4.5,
      attendance: 95.5,
      experience: '8 years'
    }
  ];

  constructor(public dashboardApi: DashboardService) {}

  ngOnInit() {
    this.loadFromApi();
  }

  loadFromApi() {
    this.loading = true;
    this.loadError = null;
    this.dashboardApi.getSummary().subscribe({
      next: (res: any) => {
        if (res.success && res.data) {
          this.dashboardStats = { ...this.dashboardStats, ...res.data.stats };

            // Attendance doughnut
            if (res.data.charts?.attendanceOverview) {
              this.attendanceChartData = res.data.charts.attendanceOverview as any;
            }

            // Class attendance (horizontal stacked)
            if (res.data.charts?.classAttendance) {
              const ca = res.data.charts.classAttendance;
              this.classAttendanceChartData = { labels: ca.labels, datasets: ca.datasets } as any;
            }

            if (res.data.charts?.classGender) {
              const g = res.data.charts.classGender;
              this.classGenderChartData = { labels: g.labels, datasets: g.datasets } as any;
            }

            // Enrollment trend
            if (res.data.charts?.enrollmentTrend) {
              this.enrollmentChartData = res.data.charts.enrollmentTrend as any;
            }

            if (res.data.charts?.gradeDistribution) {
              this.gradeChartData = res.data.charts.gradeDistribution as any;
            }
            if (res.data.charts?.revenue) {
              this.revenueChartData = res.data.charts.revenue as any;
            }
            // recent activities and top classes
            if (res.data.recentActivities) {
              this.recentActivities = res.data.recentActivities;
            }
            if (res.data.topClasses) {
              // attach rank
              this.topClasses = (res.data.topClasses as any[]).map((c, idx) => ({ ...c, rank: idx + 1 }));
            }
        } else {
          this.loadError = res.message || 'Unknown error';
        }
    this.loading = false;
      },
  error: (err: any) => {
        this.loadError = err?.message || 'Failed to load dashboard';
        // Clear chart and list data so no stale / dummy data is shown
        this.attendanceChartData = { labels: [], datasets: [] } as any;
        this.classAttendanceChartData = { labels: [], datasets: [] } as any;
        this.classGenderChartData = { labels: [], datasets: [] } as any;
        this.recentActivities = [];
        this.upcomingEvents = [];
        this.loading = false;
      }
    });
  }

  getInitials(name: string): string {
    return name.split(' ').map(n => n[0]).join('');
  }

  getSeverity(type: string): string {
    switch (type) {
      case 'enrollment':
      case 'exam':
        return 'success';
      case 'fee':
      case 'event':
        return 'info';
      case 'attendance':
        return 'warning';
      default:
        return 'info';
    }
  }

  getPriorityClass(priority: string): string {
    switch (priority) {
      case 'high':
        return 'bg-red-100 text-red-800';
      case 'medium':
        return 'bg-yellow-100 text-yellow-800';
      case 'low':
        return 'bg-green-100 text-green-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  }

  getRatingStars(rating: number): string {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    let stars = '★'.repeat(fullStars);
    if (hasHalfStar) stars += '☆';
    return stars;
  }
}
