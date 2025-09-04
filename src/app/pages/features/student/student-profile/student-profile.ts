import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { StudentsService } from '../../services/students.service';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { TagModule } from 'primeng/tag';
import { DividerModule } from 'primeng/divider';
import { AvatarModule } from 'primeng/avatar';
import { SkeletonModule } from 'primeng/skeleton';
import { RippleModule } from 'primeng/ripple';
import { Student } from '../../model/student.model';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions } from 'chart.js';

@Component({
    selector: 'app-student-profile',
    standalone: true,
    imports: [CommonModule, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule, BaseChartDirective],
    providers: [StudentsService, MessageService],
    template: `
        <p-toast />
        <div class="flex flex-wrap items-center gap-3 mb-1">
            <button pButton pRipple type="button" label="Back" icon="pi pi-arrow-left" class="p-button-text" (click)="back()"></button>
        </div>
        <h2 class="page-title">Student Profile</h2>

        <ng-container *ngIf="student(); else skeletonTpl">
            <div class="profile-wrapper">
                <p-card class="profile-hero">
                    <div class="hero-grid">
                        <div class="hero-avatar">
                            <p-avatar *ngIf="student()!.StudentName; else fallbackIcon" [label]="initials(student()!.StudentName)" size="xlarge" shape="circle" styleClass="surface-0 text-primary font-semibold text-lg shadow-2"></p-avatar>
                            <ng-template #fallbackIcon>
                                <p-avatar icon="pi pi-user" size="xlarge" shape="circle" styleClass="shadow-2" />
                            </ng-template>
                        </div>
                        <div class="hero-main">
                            <div class="hero-name">{{ student()!.StudentName }}</div>
                            <div class="hero-sub">ID: {{ student()!.StudentID }}</div>
                            <div class="hero-meta flex gap-2 mt-2 items-center">
                                <p-tag *ngIf="student()!.Status" [severity]="statusSeverity(student()!.Status)" [value]="student()!.Status"></p-tag>
                                <p-tag *ngIf="student()!.ClassName" severity="info" [value]="student()!.ClassName + (student()!.SectionName ? ' - ' + student()!.SectionName : '')"></p-tag>
                            </div>
                        </div>
                        <div class="hero-actions">
                            <button pButton pRipple icon="pi pi-pencil" label="Edit" (click)="edit()"></button>
                        </div>
                    </div>
                </p-card>

                <div class="grid info-grid three">
                    <p-card header="Identity" class="panel">
                        <div class="kv">
                            <div class="row">
                                <span>Username</span><strong>{{ student()!.Username || '-' }}</strong>
                            </div>
                            <div class="row">
                                <span>First Name</span><strong>{{ student()!.FirstName || '-' }}</strong>
                            </div>
                            <div class="row" *ngIf="student()!.MiddleName">
                                <span>Middle Name</span><strong>{{ student()!.MiddleName }}</strong>
                            </div>
                            <div class="row">
                                <span>Last Name</span><strong>{{ student()!.LastName || '-' }}</strong>
                            </div>
                            <div class="row">
                                <span>Gender</span><strong>{{ student()!.Gender }}</strong>
                            </div>
                            <div class="row">
                                <span>DOB</span><strong>{{ student()!.DOB | date: 'dd-MMM-yyyy' }}</strong>
                            </div>
                        </div>
                    </p-card>

                    <p-card header="Academic" class="panel">
                        <div class="kv">
                            <div class="row">
                                <span>Class</span><strong>{{ student()!.ClassName || '-' }}</strong>
                            </div>
                            <div class="row">
                                <span>Section</span><strong>{{ student()!.SectionName || '-' }}</strong>
                            </div>
                            <div class="row">
                                <span>Admission</span><strong>{{ student()!.AdmissionDate | date: 'dd-MMM-yyyy' }}</strong>
                            </div>
                            <div class="row">
                                <span>Status</span><strong>{{ student()!.Status }}</strong>
                            </div>
                        </div>
                    </p-card>

                    <p-card header="Parent & Contact" class="panel">
                        <div class="kv">
                            <div class="row">
                                <span>Father</span><strong>{{ student()!.FatherName || '-' }} </strong><small *ngIf="student()!.FatherContactNumber">{{ student()!.FatherContactNumber }}</small>
                            </div>
                            <div class="row">
                                <span>Mother</span><strong>{{ student()!.MotherName || '-' }} </strong><small *ngIf="student()!.MotherContactNumber">{{ student()!.MotherContactNumber }}</small>
                            </div>
                            <div class="row">
                                <span>Student Contact</span><strong>{{ student()!.ContactNumber || '-' }} </strong>
                            </div>
                            <div class="row">
                                <span>Email</span><strong>{{ student()!.EmailID || '-' }} </strong>
                            </div>
                        </div>
                    </p-card>
                </div>
                <div class="attendance-row">
                    <p-card header="Monthly Attendance %">
                        <div class="chart-wrapper">
                            <canvas baseChart [data]="attendanceLineData" [options]="attendanceLineOptions" type="line"></canvas>
                        </div>
                    </p-card>
                </div>
            </div>
        </ng-container>

        <ng-template #skeletonTpl>
            <div class="grid md:grid-cols-3 gap-4">
                <p-card *ngFor="let i of [0, 1, 2, 3]">
                    <div class="flex flex-col gap-2">
                        <p-skeleton width="60%" height="1.2rem"></p-skeleton>
                        <p-skeleton width="80%" height="0.9rem"></p-skeleton>
                        <p-skeleton width="50%" height="0.9rem"></p-skeleton>
                        <p-skeleton width="70%" height="0.9rem"></p-skeleton>
                    </div>
                </p-card>
            </div>
        </ng-template>
    `,
    styles: [
        `
            :host {
                display: block;
            }
            .profile-wrapper {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                background: #ffffff;
                padding: 1rem 1.25rem 1.5rem;
                border-radius: 12px;
                box-shadow:
                    0 2px 8px -2px rgba(0, 0, 0, 0.08),
                    0 4px 16px -4px rgba(0, 0, 0, 0.06);
            }
            .page-title {
                margin: 0 0 1rem;
                font-size: 1.4rem;
                font-weight: 600;
            }
            .profile-hero {
                background: linear-gradient(135deg, var(--primary-50), var(--surface-card));
                border: 1px solid var(--surface-border);
            }
            .hero-grid {
                display: grid;
                grid-template-columns: auto 1fr auto;
                align-items: center;
                gap: 1.25rem;
            }
            .hero-avatar :deep(.p-avatar) {
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            }
            .hero-name {
                font-size: 1.25rem;
                font-weight: 600;
                line-height: 1.2;
            }
            .hero-sub {
                font-size: 0.75rem;
                opacity: 0.7;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }
            .hero-actions button {
                white-space: nowrap;
            }
            .info-grid {
                display: grid;
                gap: 1rem;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            }
            .info-grid.three {
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            }
            .panel {
                position: relative;
            }
            .panel.wide {
                grid-column: 1 / -1;
            }
            .kv {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
            .kv.two .row {
                display: grid;
                grid-template-columns: 130px 1fr auto;
                align-items: center;
            }
            .row {
                display: grid;
                grid-template-columns: 110px 1fr;
                gap: 0.5rem;
                font-size: 0.85rem;
            }
            .row span {
                color: var(--text-color-secondary);
                font-weight: 500;
            }
            .row strong {
                font-weight: 600;
            }
            .row small {
                font-size: 0.65rem;
                background: var(--surface-200);
                padding: 2px 6px;
                border-radius: 10px;
            }
            .attendance-row {
                margin-top: 0.5rem;
            }
            .chart-wrapper {
                position: relative;
                width: 100%;
                height: 320px;
            }
            @media (max-width: 640px) {
                .hero-grid {
                    grid-template-columns: 1fr;
                    text-align: center;
                }
                .hero-actions {
                    justify-self: center;
                }
                .kv.two .row {
                    grid-template-columns: 110px 1fr;
                }
                .chart-wrapper {
                    height: 260px;
                }
            }
        `
    ]
})
export class StudentProfile implements OnInit {
    student = signal<(Student & { FirstName?: string; MiddleName?: string; LastName?: string; ContactNumber?: string; EmailID?: string }) | null>(null);
    // Attendance chart state
    attendanceLineData: ChartConfiguration<'line'>['data'] = { labels: [], datasets: [] };
    attendanceLineOptions: ChartOptions<'line'> = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true }, title: { display: false } },
        scales: { y: { beginAtZero: true, max: 100, title: { display: true, text: '%' } } }
    };
    constructor(
        private route: ActivatedRoute,
        private router: Router,
        private students: StudentsService,
        private msg: MessageService
    ) {}
    ngOnInit(): void {
        const idParam = this.route.snapshot.queryParamMap.get('id') || this.route.snapshot.paramMap.get('id');
        const id = idParam ? Number(idParam) : NaN;
        if (!id) {
            this.msg.add({ severity: 'error', summary: 'Error', detail: 'Invalid student id' });
            return;
        }
        this.students.getStudent(id).subscribe({
            next: (s: any) => {
                this.student.set(s);
                this.loadAttendance();
            },
            error: () => this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load' })
        });
    }
    back() {
        this.router.navigate(['/features/all-students']);
    }
    edit() {
        const s = this.student();
        if (s?.StudentID) this.router.navigate(['/features/student-admission'], { queryParams: { id: s.StudentID } });
    }
    statusSeverity(status?: string) {
        switch ((status || '').toLowerCase()) {
            case 'active':
                return 'success';
            case 'inactive':
                return 'danger';
            case 'pending':
                return 'warning';
            default:
                return 'info';
        }
    }
    initials(name: string) {
        return name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((p) => p[0].toUpperCase())
            .join('');
    }
    loadAttendance() {
        const stu = this.student();
        const admission = stu?.AdmissionDate ? new Date(stu.AdmissionDate) : null;
        this.students.getStudentMonthlyAttendance().subscribe({
            next: (chart: any) => {
                if (chart?.labels && chart?.datasets && chart.labels.length) {
                    // Determine real month sequence corresponding to labels based on backend logic (chronological ending current month)
                    const total = chart.labels.length;
                    const months: Date[] = [];
                    const end = new Date();
                    end.setDate(1);
                    end.setHours(0, 0, 0, 0);
                    for (let i = total - 1; i >= 0; i--) {
                        const d = new Date(end);
                        d.setMonth(end.getMonth() - (total - 1 - i));
                        months.push(d);
                    }
                    // If admission exists, remove months before admission month
                    if (admission) {
                        const admMonth = new Date(admission);
                        admMonth.setDate(1);
                        admMonth.setHours(0, 0, 0, 0);
                        let firstIdx = months.findIndex((m) => m.getTime() >= admMonth.getTime());
                        if (firstIdx > 0) {
                            chart.labels = chart.labels.slice(firstIdx);
                            chart.datasets = chart.datasets.map((ds: any) => ({ ...ds, data: ds.data.slice(firstIdx) }));
                        }
                    }
                    chart.datasets = chart.datasets.map((d: any) => ({
                        ...d,
                        borderColor: d.borderColor || 'var(--primary-color)',
                        backgroundColor: d.backgroundColor || 'rgba(99,102,241,0.15)',
                        tension: d.tension ?? 0.35,
                        fill: true,
                        pointRadius: d.pointRadius ?? 3
                    }));
                    this.attendanceLineData = chart;
                } else {
                    this.attendanceLineData = { labels: [], datasets: [] };
                }
            },
            error: () => {
                this.attendanceLineData = { labels: [], datasets: [] };
            }
        });
    }
}
