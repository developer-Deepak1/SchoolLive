import { Component, Input, OnInit, signal, TemplateRef, ViewChild } from '@angular/core';
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
import { ChartConfiguration, ChartOptions, Chart, Plugin } from 'chart.js';

@Component({
  selector: 'app-student-profile',
  standalone: true,
  imports: [CommonModule, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule, BaseChartDirective],
  providers: [StudentsService, MessageService],
  templateUrl: './student-profile.component.html',
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
    .attendance-row { margin-top:.5rem; }
    .chart-wrapper { position:relative; width:100%; height:320px; }
    @media (max-width: 640px) {
      .hero-grid { grid-template-columns: 1fr; text-align:center; }
      .hero-actions { justify-self:center; }
      .kv.two .row { grid-template-columns: 110px 1fr; }
      .chart-wrapper { height:260px; }
    }
  `]
})
export class StudentProfile implements OnInit {
  student = signal<Student & { FirstName?: string; MiddleName?: string; LastName?: string; ContactNumber?: string; EmailID?: string } | null>(null);
  loading = signal<boolean>(true);
  @Input() profileSetting: boolean = false;
  // Attendance chart state
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
  constructor(private route: ActivatedRoute, private router: Router, private students: StudentsService, private msg: MessageService) {}
  // tiny plugin registration like EmployeeProfile to render value labels on bars
  private static _dataLabelPluginRegistered = false;

  private ensureDataLabelPlugin() {
    if ((StudentProfile as any)._dataLabelPluginRegistered) return;
    const dataLabelPlugin: Plugin<'bar'|'line'> = {
      id: 'barValueLabels',
      afterDatasetsDraw: (chart) => {
        const ctx = chart.ctx;
        chart.data.datasets.forEach((dataset, dsIndex) => {
          const meta = chart.getDatasetMeta(dsIndex);
          if (!meta || meta.type !== 'bar') return;
          meta.data.forEach((elem: any, index: number) => {
            const v = dataset.data[index];
            if (v === null || v === undefined) return;
            const x = elem.x;
            const y = elem.y;
            ctx.save();
            ctx.fillStyle = '#374151';
            ctx.font = '12px system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            ctx.fillText(String(v), x, y - 4);
            ctx.restore();
          });
        });
      }
    };
    try { Chart.register(dataLabelPlugin); (StudentProfile as any)._dataLabelPluginRegistered = true; } catch (e) { /* ignore */ }
  }
  @ViewChild('skeletonTpl', { static: true }) skeletonTpl!: TemplateRef<any>;
  @ViewChild('notFoundTpl', { static: true }) notFoundTpl!: TemplateRef<any>;
  ngOnInit(): void {
  this.ensureDataLabelPlugin();
  const idParam = this.route.snapshot.queryParamMap.get('id') || this.route.snapshot.paramMap.get('id');
  const id = idParam ? Number(idParam) : NaN;
    if(!this.profileSetting){
      this.loadProfile(id);
    }
    else {
      this.students.getStudentId().subscribe({
        next: (res: any) => {
          const id = res?.data?.student_id ? Number(res.data.student_id) : NaN;
          this.loadProfile(id);
        }
      });
    }
  }
  loadProfile(id: number) {
    this.loading.set(true);
    this.students.getStudent(id).subscribe({
      next: (s: any) => { this.student.set(s); this.loading.set(false); if (s) this.loadAttendance(); },
      error: () => { this.loading.set(false); this.msg.add({severity:'error', summary:'Error', detail:'Failed to load'}); }
    });
  }
  back() { this.router.navigate(['/features/all-students']); }
  edit() { const s = this.student(); if (s?.StudentID) this.router.navigate(['/features/student-admission'], { queryParams: { id: s.StudentID }}); }
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

  loadAttendance() {
    const stu = this.student();
  const admissionRaw = stu?.AdmissionDate ?? null;
  const admission = admissionRaw ? new Date(String(admissionRaw)) : null;
    const studentId = stu?.StudentID ? Number(stu.StudentID) : NaN;
    const attendance$ = !isNaN(studentId) ? this.students.getStudentMonthlyAttendance(studentId) : this.students.getStudentMonthlyAttendanceFallback();
    attendance$.subscribe({ next: (chart: any) => this.applyAttendanceChart(chart, admission), error: () => { this.attendanceLineData = { labels: [], datasets: [] }; this.attendanceSummary.set([]); } });
  }

  // Public helper to fetch monthly attendance (lightweight) for a given student id
  getMonthlyAttendance(studentId?: number) {
  const admissionRaw = this.student()?.AdmissionDate ?? null;
  const admission = admissionRaw ? new Date(String(admissionRaw)) : null;
    const sid = studentId ?? (this.student()?.StudentID ? Number(this.student()!.StudentID) : NaN);
    const attendance$ = !isNaN(sid) ? this.students.getStudentMonthlyAttendance(sid) : this.students.getStudentMonthlyAttendanceFallback();
    attendance$.subscribe({ next: (chart: any) => this.applyAttendanceChart(chart, admission), error: () => { this.attendanceLineData = { labels: [], datasets: [] }; this.attendanceSummary.set([]); } });
  }

  private applyAttendanceChart(chart: any, admission: Date | null) {
    if (chart?.labels && chart?.datasets && chart.labels.length) {
      const total = chart.labels.length;
      const months: Date[] = [];
      const end = new Date(); end.setDate(1); end.setHours(0,0,0,0);
      for (let i = total - 1; i >= 0; i--) { const d = new Date(end); d.setMonth(end.getMonth() - (total - 1 - i)); months.push(d); }

      // If admission exists, remove months before admission month
      if (admission) {
        const admMonth = new Date(admission); admMonth.setDate(1); admMonth.setHours(0,0,0,0);
        let firstIdx = months.findIndex(m => m.getTime() >= admMonth.getTime());
        if (firstIdx > 0) { chart.labels = chart.labels.slice(firstIdx); chart.datasets = chart.datasets.map((ds: any) => ({ ...ds, data: ds.data.slice(firstIdx) })); }
      }

      // transform datasets: render counts as bars on left axis and remove percent-only datasets
      chart.datasets = (chart.datasets || []).filter((d: any) => { const lbl = (d.label || '').toString().toLowerCase(); return !(lbl.includes('%') || lbl.includes('attendance')); }).map((d: any) => {
        const label = ((d.label || '') + '').toLowerCase();
        let border = d.borderColor || 'var(--primary-color)';
        let bg = d.backgroundColor || 'rgba(99,102,241,0.15)';
        if (label.includes('working')) { border = d.borderColor || '#6b7280'; bg = d.backgroundColor || 'rgba(107,114,128,0.12)'; }
        else if (label.includes('present')) { border = d.borderColor || '#059669'; bg = d.backgroundColor || 'rgba(5,150,105,0.12)'; }
        const yAxisId = 'counts';
        const base: any = { ...d, borderColor: border, backgroundColor: bg, yAxisID: yAxisId };
        base.type = 'bar'; base.barThickness = d.barThickness ?? 'flex'; base.borderWidth = d.borderWidth ?? 0; return base;
      });

      // compute sensible max for left axis
      try {
        const numericDatasets = (chart.datasets || []);
        const maxVal = numericDatasets.reduce((acc: number, ds: any) => { const localMax = (ds.data || []).reduce((a: number, v: any) => Math.max(a, Number(v || 0)), 0); return Math.max(acc, localMax); }, 0);
        const buffer = Math.max(1, Math.ceil(maxVal * 0.1));
        const suggestedMax = maxVal > 0 ? maxVal + buffer : undefined;
        if (!this.attendanceLineOptions.scales) this.attendanceLineOptions.scales = {} as any;
        (this.attendanceLineOptions.scales as any).counts = { ...(this.attendanceLineOptions.scales as any).counts, max: suggestedMax };
      } catch (e) { /* ignore */ }
      if (this.attendanceLineOptions.scales && (this.attendanceLineOptions.scales as any).percent) delete (this.attendanceLineOptions.scales as any).percent;

      this.attendanceLineData = chart;

      // build summary
      try {
        const labels: string[] = chart.labels || [];
        const ds = chart.datasets || [];
        const workingDs = ds.find((d: any) => (d.label || '').toString().toLowerCase().includes('working'));
        const presentDs = ds.find((d: any) => (d.label || '').toString().toLowerCase().includes('present')) || ds[0];
        const summary = labels.map((lab: any, idx: number) => { const working = workingDs ? Number(workingDs.data[idx] ?? 0) : 0; const present = presentDs ? Number(presentDs.data[idx] ?? 0) : 0; const percent = working > 0 ? Math.round((present / working) * 100) : 0; return { month: lab?.toString() || '', workingDays: working, present, percent }; });
        this.attendanceSummary.set(summary);
      } catch (e) { this.attendanceSummary.set([]); }
    } else {
      this.attendanceLineData = { labels: [], datasets: [] };
    }
  }

  // compute display name from first/middle/last if present, fallback to StudentName
  get displayName(): string {
    const s = this.student();
    if (!s) return '';
    const parts: string[] = [];
    if ((s as any).FirstName) parts.push((s as any).FirstName);
    if ((s as any).MiddleName) parts.push((s as any).MiddleName);
    if ((s as any).LastName) parts.push((s as any).LastName);
    const joined = parts.join(' ').trim();
    return joined || (s.StudentName || '');
  }
}
