import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { EmployeesService } from '../../services/employees.service';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { TagModule } from 'primeng/tag';
import { DividerModule } from 'primeng/divider';
import { AvatarModule } from 'primeng/avatar';
import { SkeletonModule } from 'primeng/skeleton';
import { RippleModule } from 'primeng/ripple';
import { Employee } from '../../model/employee.model';

@Component({
  selector: 'app-employee-profile',
  standalone: true,
  imports: [CommonModule, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule],
  providers: [EmployeesService, MessageService],
  template: `
    <p-toast />
    <div class="flex flex-wrap items-center gap-3 mb-1">
      <button pButton pRipple type="button" label="Back" icon="pi pi-arrow-left" class="p-button-text" (click)="back()"></button>
    </div>
    <h2 class="page-title">Employee Profile</h2>

    <ng-container *ngIf="employee(); else skeletonTpl">
      <div class="profile-wrapper">
        <p-card class="profile-hero">
          <div class="hero-grid">
            <div class="hero-avatar">
              <p-avatar *ngIf="employee()!.EmployeeName; else fallbackIcon" [label]="initials(employee()!.EmployeeName)" size="xlarge" shape="circle" styleClass="surface-0 text-primary font-semibold text-lg shadow-2" ></p-avatar>
              <ng-template #fallbackIcon>
                <p-avatar icon="pi pi-user" size="xlarge" shape="circle" styleClass="shadow-2" />
              </ng-template>
            </div>
            <div class="hero-main">
              <div class="hero-name">{{employee()!.EmployeeName}}</div>
              <div class="hero-sub">ID: {{employee()!.EmployeeID}}</div>
              <div class="hero-meta flex gap-2 mt-2 items-center">
                <p-tag *ngIf="employee()!.Status" [severity]="statusSeverity(employee()!.Status)" [value]="employee()!.Status"></p-tag>
                <p-tag *ngIf="employee()!.RoleName" severity="info" [value]="employee()!.RoleName"></p-tag>
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
              <div class="row"><span>Username</span><strong>{{employee()!.Username || '-'}}</strong></div>
              <div class="row"><span>Name</span><strong>{{employee()!.EmployeeName || '-'}}</strong></div>
              <div class="row"><span>Gender</span><strong>{{employee()!.Gender || '-'}}</strong></div>
              <div class="row"><span>DOB</span><strong>{{employee()!.DOB ? (employee()!.DOB | date:'dd-MMM-yyyy') : '-'}}</strong></div>
            </div>
          </p-card>

          <p-card header="Employment" class="panel">
            <div class="kv">
              <div class="row"><span>Role</span><strong>{{employee()!.RoleName || '-'}}</strong></div>
              <div class="row"><span>Joining</span><strong>{{employee()!.JoiningDate ? (employee()!.JoiningDate | date:'dd-MMM-yyyy') : '-'}}</strong></div>
              <div class="row"><span>Salary</span><strong>{{employee()!.Salary ? (employee()!.Salary | currency) : '-'}}</strong></div>
              <div class="row"><span>Status</span><strong>{{employee()!.Status || '-'}}</strong></div>
            </div>
          </p-card>

          <p-card header="Contact" class="panel">
            <div class="kv">
              <div class="row"><span>Contact</span><strong>{{employee()!.ContactNumber || '-'}}</strong></div>
              <div class="row"><span>Email</span><strong>{{employee()!.EmailID || '-'}}</strong></div>
            </div>
          </p-card>
        </div>
      </div>
    </ng-container>

    <ng-template #skeletonTpl>
      <div class="grid md:grid-cols-3 gap-4">
        <p-card *ngFor="let i of [0,1,2,3]">
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
  styles: [`
    :host { display:block; }
    .profile-wrapper { display:flex; flex-direction:column; gap:1.5rem; background:#ffffff; padding:1rem 1.25rem 1.5rem; border-radius:12px; box-shadow:0 2px 8px -2px rgba(0,0,0,.08),0 4px 16px -4px rgba(0,0,0,.06); }
    .page-title { margin:0 0 1rem; font-size:1.4rem; font-weight:600; }
    .profile-hero { background: linear-gradient(135deg,var(--primary-50),var(--surface-card)); border:1px solid var(--surface-border); }
    .hero-grid { display:grid; grid-template-columns: auto 1fr auto; align-items:center; gap:1.25rem; }
    .hero-avatar :deep(.p-avatar) { box-shadow:0 2px 6px rgba(0,0,0,.15); }
    .hero-name { font-size:1.25rem; font-weight:600; line-height:1.2; }
    .hero-sub { font-size:.75rem; opacity:.7; letter-spacing:.5px; text-transform:uppercase; }
    .hero-actions button { white-space:nowrap; }
    .info-grid { display:grid; gap:1rem; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); }
    .info-grid.three { grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); }
    .panel { position:relative; }
    .panel.wide { grid-column:1 / -1; }
    .kv { display:flex; flex-direction:column; gap:.5rem; }
    .kv.two .row { display:grid; grid-template-columns: 130px 1fr auto; align-items:center; }
    .row { display:grid; grid-template-columns: 110px 1fr; gap:.5rem; font-size:.85rem; }
    .row span { color: var(--text-color-secondary); font-weight:500; }
    .row strong { font-weight:600; }
    .row small { font-size:.65rem; background:var(--surface-200); padding:2px 6px; border-radius:10px; }
    @media (max-width: 640px) {
      .hero-grid { grid-template-columns: 1fr; text-align:center; }
      .hero-actions { justify-self:center; }
    }
  `]
})
export class EmployeeProfile implements OnInit {
  employee = signal<Employee | null>(null);
  constructor(private route: ActivatedRoute, private router: Router, private employees: EmployeesService, private msg: MessageService) {}

  ngOnInit(): void {
    const idParam = this.route.snapshot.queryParamMap.get('id') || this.route.snapshot.paramMap.get('id');
    const id = idParam ? Number(idParam) : NaN;
    if (!id) { this.msg.add({severity:'error', summary:'Error', detail:'Invalid employee id'}); return; }
    this.employees.getEmployee(id).subscribe({ next: (s:any) => this.employee.set(s), error: () => this.msg.add({severity:'error', summary:'Error', detail:'Failed to load'}) });
  }

  back() { this.router.navigate(['/features/all-employees']); }
  edit() { const e = this.employee(); if (e?.EmployeeID) this.router.navigate(['/features/add-employees'], { queryParams: { id: e.EmployeeID }}); }

  statusSeverity(status?: string) {
    switch ((status || '').toLowerCase()) {
      case 'active': return 'success';
      case 'inactive': return 'danger';
      case 'pending': return 'warning';
      default: return 'info';
    }
  }

  initials(name: string) {
    return name.split(/\s+/).filter(Boolean).slice(0,2).map(p=>p[0].toUpperCase()).join('');
  }
}
