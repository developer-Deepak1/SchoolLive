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
    imports: [CommonModule, BaseChartDirective, HttpClientModule, CardModule, ButtonModule, TagModule, TableModule, ProgressBarModule, BadgeModule, RippleModule],
    templateUrl: './student-dashboard.html',
    styleUrl: './student-dashboard.scss'
})
export class StudentDashboard implements OnInit {
    loading = false;
    loadError: string | null = null;

    // Quick stats pulled from backend student summary
    quickStats: StudentQuickStat[] = [
        { key: 'attendance', label: 'Avg Attendance', icon: 'pi pi-calendar', color: 'purple', value: 0, suffix: '%', hint: 'Academic Year' },
        { key: 'grades', label: 'Avg Grade', icon: 'pi pi-chart-line', color: 'green', value: 0, suffix: '%', hint: 'Average Marks' },
        { key: 'events', label: 'Upcoming Events', icon: 'pi pi-calendar-clock', color: 'blue', value: 0 },
        { key: 'activities', label: 'Recent Activities', icon: 'pi pi-bolt', color: 'orange', value: 0 }
    ];

    // Attendance donut (using school attendance for now)
    attendanceChartData: ChartConfiguration<'doughnut'>['data'] = { labels: [], datasets: [] };
    attendanceChartOptions: ChartOptions<'doughnut'> = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: { display: true, text: "Today's Attendance" },
            legend: { position: 'bottom' }
        }
    };

    // Grade distribution (personal – simulated until backend provides per-student distribution)
    gradeChartData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };
    gradeChartOptions: ChartOptions<'bar'> = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: { display: true, text: 'Grade Distribution (My Subjects)' },
            legend: { display: false }
        },
        scales: { y: { beginAtZero: true } }
    };

    // Enrollment / Progress trend (placeholder based on enrollment trend labels)
    progressChartData: ChartConfiguration<'line'>['data'] = { labels: [], datasets: [] };
    progressChartOptions: ChartOptions<'line'> = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: { display: true, text: 'Monthly Progress (Placeholder)' }
        },
        scales: { y: { beginAtZero: true } }
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

    recentActivities: any[] = [];
    upcomingEvents: any[] = [];

    constructor(private studentApi: StudentDashboardService) {}

    ngOnInit(): void {
        this.loadFromApi();
    }

    loadFromApi() {
        this.loading = true;
        this.loadError = null;
        this.studentApi.getSummary().subscribe({
            next: (res: StudentDashboardResponse) => {
                if (!res.success) {
                    this.loadError = res.message || 'Failed to load summary';
                    this.loading = false;
                    return;
                }
                // Average stats
                if (res.data?.stats) {
                    this.setQuickStat('attendance', (res.data.stats.averageAttendance ?? 0).toFixed(1));
                    this.setQuickStat('grades', (res.data.stats.averageGrade ?? 0).toFixed(1));
                }
                if (res.data?.charts?.monthlyAttendance) {
                    this.monthlyAttendanceChartData = res.data.charts.monthlyAttendance as any;
                }
                if (res.data?.charts?.gradeDistribution) {
                    this.gradeChartData = res.data.charts.gradeDistribution as any;
                }
                if (res.data?.charts?.gradeProgress) {
                    this.progressChartData = res.data.charts.gradeProgress as any; // reuse progress chart area
                    this.progressChartOptions.plugins!.title!.text = 'Average Marks Trend';
                }

                this.recentActivities = res.data?.recentActivities?.slice(0, 6) || [];
                this.upcomingEvents = res.data?.upcomingEvents?.slice(0, 5) || [];
                this.setQuickStat('events', this.upcomingEvents.length);
                this.setQuickStat('activities', this.recentActivities.length);

                this.loading = false;
            },
            error: (err) => {
                this.loadError = err?.message || 'Network error';
                this.loading = false;
            }
        });
    }

    refresh() {
        this.studentApi.refreshNow();
        this.loadFromApi();
    }

    setQuickStat(key: string, value: any) {
        const stat = this.quickStats.find((s) => s.key === key);
        if (stat) stat.value = value;
    }

    getSeverityForActivity(type: string): string {
        switch (type) {
            case 'exam':
                return 'warn';
            case 'attendance':
                return 'info';
            case 'assignment':
                return 'success';
            default:
                return 'info';
        }
    }
}
