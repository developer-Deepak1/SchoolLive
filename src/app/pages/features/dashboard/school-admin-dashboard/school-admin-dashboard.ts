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

  // Attendance Chart
  attendanceChartData: ChartConfiguration<'doughnut'>['data'] = {
    labels: ['Present', 'Absent', 'Late'],
    datasets: [
      {
        data: [94.5, 4.2, 1.3],
        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
        borderWidth: 2,
        borderColor: '#ffffff'
      }
    ]
  };

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
  classAttendanceChartData: ChartConfiguration<'bar'>['data'] = {
    labels: [
      'Grade 12-A',
      'Grade 12-B',
      'Grade 11-A',
      'Grade 11-B',
      'Grade 10-A',
      'Grade 10-B',
      'Grade 9-A',
      'Grade 9-B'
    ],
    datasets: [
      {
        label: 'Present',
        data: [31, 30, 29, 28, 27, 26, 28, 27], // dummy present counts
        backgroundColor: '#3b82f6',
        borderRadius: 6,
        maxBarThickness: 26,
        stack: 'attendance'
      },
      {
        label: 'Absent',
        data: [1, 2, 3, 4, 5, 6, 2, 3], // dummy absent counts
        backgroundColor: '#ef4444',
        borderRadius: 6,
        maxBarThickness: 26,
        stack: 'attendance'
      }
    ]
  };

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

  // Recent Activities
  recentActivities = [
    {
      id: 1,
      type: 'enrollment',
      message: 'New student John Doe enrolled in Grade 10-A',
      timestamp: '2 hours ago',
      icon: 'pi pi-user-plus',
      severity: 'success'
    },
    {
      id: 2,
      type: 'fee',
      message: 'Fee payment received from Sarah Johnson - $2,500',
      timestamp: '4 hours ago',
      icon: 'pi pi-credit-card',
      severity: 'info'
    },
    {
      id: 3,
      type: 'attendance',
      message: 'Low attendance alert for Grade 8-B (78%)',
      timestamp: '6 hours ago',
      icon: 'pi pi-exclamation-triangle',
      severity: 'warning'
    },
    {
      id: 4,
      type: 'exam',
      message: 'Mid-term exam results published for Grade 12',
      timestamp: '8 hours ago',
      icon: 'pi pi-file-edit',
      severity: 'success'
    },
    {
      id: 5,
      type: 'event',
      message: 'Annual Sports Day scheduled for next Friday',
      timestamp: '1 day ago',
      icon: 'pi pi-calendar',
      severity: 'info'
    }
  ];

  // Top Performing Classes
  topClasses: Array<any> = [];

  // Upcoming Events
  upcomingEvents = [
    {
      id: 1,
      title: 'Parent-Teacher Conference',
      date: 'September 5, 2025',
      time: '9:00 AM - 5:00 PM',
      location: 'Main Auditorium',
      type: 'Academic',
      priority: 'high'
    },
    {
      id: 2,
      title: 'Annual Sports Day',
      date: 'September 8, 2025',
      time: '8:00 AM - 4:00 PM',
      location: 'School Grounds',
      type: 'Sports',
      priority: 'medium'
    },
    {
      id: 3,
      title: 'Science Fair',
      date: 'September 12, 2025',
      time: '10:00 AM - 3:00 PM',
      location: 'Science Block',
      type: 'Academic',
      priority: 'medium'
    },
    {
      id: 4,
      title: 'Mid-term Examinations Begin',
      date: 'September 15, 2025',
      time: 'All Day',
      location: 'Examination Halls',
      type: 'Examination',
      priority: 'high'
    }
  ];

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
