import { Component, Input, input, OnInit, signal } from '@angular/core';
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
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions, Chart, Plugin } from 'chart.js';

@Component({
  selector: 'app-employee-profile',
  standalone: true,
  imports: [CommonModule, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule, BaseChartDirective],
  providers: [EmployeesService, MessageService],
  templateUrl: './employee-profile.html',
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
  .chart-wrapper { height: 320px; width:100%; }
  @media (max-width: 640px) { .chart-wrapper { height: 220px; } }
  `]
})
export class EmployeeProfile implements OnInit {
  employee = signal<Employee | null>(null);
  loading = signal<boolean>(true);
  @Input() profileSetting: boolean = false;

  attendanceLineData: ChartConfiguration<'line'>['data'] = { labels: [], datasets: [] };
  // summary array: { month: string, workingDays: number, present: number, percent: number }
  attendanceSummary = signal<Array<{ month: string; workingDays: number; present: number; percent: number }>>([]);
  attendanceLineOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
  plugins: { legend: { display: true }, title: { display: false } },
    scales: {
      counts: { // left axis for raw counts (hidden labels/ticks)
        type: 'linear',
        position: 'left',
        beginAtZero: true,
        display: false,
        title: { display: false }
      },
      percent: { // right axis for percentage 0-100
        type: 'linear',
        position: 'right',
        beginAtZero: true,
        max: 100,
        grid: { drawOnChartArea: false },
        title: { display: true, text: '%' }
      }
    }
  };
  constructor(private route: ActivatedRoute, private router: Router, private employees: EmployeesService, private msg: MessageService) {}
  ngOnInit(): void {
    const idParam = this.route.snapshot.queryParamMap.get('id') || this.route.snapshot.paramMap.get('id');
    const id = idParam ? Number(idParam) : NaN;
    if (this.profileSetting) {
      this.employees.getEmployeeId().subscribe({
        next: (res: any) => {
          if (!res) { this.msg.add({ severity: 'error', summary: 'Error', detail: 'Employee record not found' }); this.loading.set(false); return; }
          const empId = res?.data?.EmployeeID;
          this.loadingprofile(empId);
        }, error: () => { this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to fetch employee id' }); this.loading.set(false); }
      });
    } else if (id && !isNaN(id) && id > 0) {
      this.loadingprofile(id);
    }
  }
  private loadingprofile(id: number) {
    this.loading.set(true);
    this.employees.getEmployee(id).subscribe({
      next: (s: any) => { const withParents = this.ensureParentFields(s); this.employee.set(withParents); this.loading.set(false); if (withParents) this.loadAttendance(); },
      error: () => { this.loading.set(false); this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load' }); }
    });
  }

  // Helper to ensure dynamic parent/contact fields exist on the employee object
  private ensureParentFields(e: any) {
    if (!e) return e;
    if (e.FatherName === undefined) e.FatherName = '';
    if (e.FatherContact === undefined) e.FatherContact = '';
    if (e.MotherName === undefined) e.MotherName = '';
    if (e.MotherContact === undefined) e.MotherContact = '';
    return e;
  }

  loadAttendance() {
    const emp = this.employee();
  // Prefer employee-specific monthly attendance endpoint
  const empId = emp?.EmployeeID ?? 0;
  const attendance$ = empId ? this.employees.getEmployeeMonthlyAttendance(empId) : this.employees.getEmployeeMonthlyAttendanceFallback();
  attendance$.subscribe({
      next: (chart: any) => {
        if (chart?.labels && chart?.datasets && chart.labels.length) {
          const total = chart.labels.length;
          const months: Date[] = [];
          const end = new Date(); end.setDate(1); end.setHours(0,0,0,0);
          for (let i = total - 1; i >= 0; i--) {
            const d = new Date(end); d.setMonth(end.getMonth() - (total - 1 - i)); months.push(d);
          }
          // If joining exists, remove months before joining month
          const join = emp?.JoiningDate ? new Date(emp.JoiningDate) : null;
          if (join) {
            const joinMon = new Date(join); joinMon.setDate(1); joinMon.setHours(0,0,0,0);
            let firstIdx = months.findIndex(m => m.getTime() >= joinMon.getTime());
            if (firstIdx > 0) {
              chart.labels = chart.labels.slice(firstIdx);
              chart.datasets = chart.datasets.map((ds: any) => ({ ...ds, data: ds.data.slice(firstIdx) }));
            }
          }
          // remove any percentage/attendance datasets so chart shows only absolute counts
          chart.datasets = (chart.datasets || []).filter((d: any) => {
            const lbl = (d.label || '').toString().toLowerCase();
            return !(lbl.includes('%') || lbl.includes('attendance'));
          }).map((d: any) => {
            const label = ((d.label || '') + '').toLowerCase();
            // Friendly palette: workingDays -> slate, present -> teal, percent -> indigo
            let border = d.borderColor || 'var(--primary-color)';
            let bg = d.backgroundColor || 'rgba(99,102,241,0.15)';
            if (label.includes('working')) { border = d.borderColor || '#6b7280'; bg = d.backgroundColor || 'rgba(107,114,128,0.12)'; }
            else if (label.includes('present')) { border = d.borderColor || '#059669'; bg = d.backgroundColor || 'rgba(5,150,105,0.12)'; }
            else if (label.includes('%') || label.includes('attendance')) { border = d.borderColor || '#4f46e5'; bg = d.backgroundColor || 'rgba(79,70,229,0.12)'; }

            // counts render as bars on the left axis
            const yAxisId = 'counts';
            const base: any = {
              ...d,
              borderColor: border,
              backgroundColor: bg,
              yAxisID: yAxisId
            };
            // make counts render as bars
            base.type = 'bar';
            base.barThickness = d.barThickness ?? 'flex';
            base.borderWidth = d.borderWidth ?? 0;
            return base;
          });
          // compute a sensible max for the left counts axis based on available datasets
          try {
            const numericDatasets = (chart.datasets || []).filter((d: any) => { const lbl = (d.label||'').toString().toLowerCase(); return !(lbl.includes('%') || lbl.includes('attendance')); });
            const maxVal = numericDatasets.reduce((acc: number, ds: any) => {
              const localMax = (ds.data || []).reduce((a: number, v: any) => Math.max(a, Number(v || 0)), 0);
              return Math.max(acc, localMax);
            }, 0);
            // set a small buffer above max (e.g., +1 or 10% whichever larger)
            const buffer = Math.max(1, Math.ceil(maxVal * 0.1));
            const suggestedMax = maxVal > 0 ? maxVal + buffer : undefined;
            if (!this.attendanceLineOptions.scales) this.attendanceLineOptions.scales = {} as any;
            // keep only counts axis
            (this.attendanceLineOptions.scales as any).counts = { ...(this.attendanceLineOptions.scales as any).counts, max: suggestedMax };
          } catch (e) { /* ignore */ }
          // remove percent axis from options entirely
          if (this.attendanceLineOptions.scales && (this.attendanceLineOptions.scales as any).percent) delete (this.attendanceLineOptions.scales as any).percent;
          this.attendanceLineData = chart;
          // build a simple summary mapping for the UI
          try {
            const labels: string[] = chart.labels || [];
            const ds = chart.datasets || [];
            // heuristics: find dataset named 'workingDays' and 'present' (or use first dataset as present)
            const workingDs = ds.find((d: any) => (d.label || '').toString().toLowerCase().includes('working'));
            const presentDs = ds.find((d: any) => (d.label || '').toString().toLowerCase().includes('present')) || ds[0];
            const summary = labels.map((lab: any, idx: number) => {
              const working = workingDs ? Number(workingDs.data[idx] ?? 0) : 0;
              const present = presentDs ? Number(presentDs.data[idx] ?? 0) : 0;
              const percent = working > 0 ? Math.round((present / working) * 100) : 0;
              return { month: lab?.toString() || '', workingDays: working, present, percent };
            });
            this.attendanceSummary.set(summary);
          } catch (e) { this.attendanceSummary.set([]); }
        } else {
          this.attendanceLineData = { labels: [], datasets: [] };
        }
      },
  error: () => { this.attendanceLineData = { labels: [], datasets: [] }; }
    });
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

  // compute display name from first/middle/last if present, fallback to EmployeeName
  displayName(): string {
    const e = this.employee();
    if (!e) return '';
    const parts: string[] = [];
    if ((e as any).FirstName) parts.push((e as any).FirstName);
    if ((e as any).MiddleName) parts.push((e as any).MiddleName);
    if ((e as any).LastName) parts.push((e as any).LastName);
    const joined = parts.join(' ').trim();
    return joined || (e.EmployeeName || '');
  }
}
