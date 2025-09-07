import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { TagModule } from 'primeng/tag';
import { DividerModule } from 'primeng/divider';
import { AvatarModule } from 'primeng/avatar';
import { SkeletonModule } from 'primeng/skeleton';
import { RippleModule } from 'primeng/ripple';
import { BaseChartDirective } from 'ng2-charts';
import { ChartConfiguration, ChartOptions, Chart, Plugin } from 'chart.js';
import { UserService } from '@/services/user.service';
import { StudentsService } from '../services/students.service';
import { EmployeesService } from '../services/employees.service';
import { ProfileService } from './profile.service';
import { USER_ROLES } from '../../common/constant';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { InputTextModule } from 'primeng/inputtext';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { PasswordModule } from 'primeng/password';
@Component({
  selector: 'app-profile-setting',
  standalone: true,
  imports: [CommonModule, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule, BaseChartDirective, FormsModule, ReactiveFormsModule, InputTextModule, HttpClientModule, PasswordModule],
  providers: [MessageService],
  templateUrl: './profile-setting.html',
  styleUrls: ['./profile-setting.scss']
})
export class ProfileSetting implements OnInit {
  loading = signal(true);
  user: any = null; // tx-user
  student: any = null; // tx-student
  employee: any = null; // tx-employee

  attendanceLineData: ChartConfiguration<'bar'>['data'] = { labels: [], datasets: [] };
  attendanceLineOptions: ChartOptions<'bar'> = { responsive: true, maintainAspectRatio: false };
  attendanceSummary = signal<Array<{ month: string; workingDays: number; present: number; percent: number }>>([]);

  private static _dataLabelPluginRegistered = false;

  // change password fields
  // reactive form for change password
  changePasswordForm!: FormGroup;

  constructor(private userService: UserService, private students: StudentsService, private employees: EmployeesService, private profileService: ProfileService, private msg: MessageService, private http: HttpClient) {}

  ngOnInit(): void {
    this.registerDataLabelPlugin();
    this.loadProfile();
    this.initForm();
  }

  private initForm() {
    this.changePasswordForm = new FormBuilder().group({
      oldPassword: ['', Validators.required],
      newPassword: ['', [Validators.required, Validators.minLength(6)]],
      confirmPassword: ['', Validators.required]
    }, { validators: this.passwordsMatchValidator });
  }

  private passwordsMatchValidator(group: AbstractControl): ValidationErrors | null {
    const np = group.get('newPassword')?.value;
    const cp = group.get('confirmPassword')?.value;
    if (np && cp && np !== cp) return { passwordMismatch: true };
    return null;
  }

  private registerDataLabelPlugin() {
    if ((ProfileSetting as any)._dataLabelPluginRegistered) return;
    const plugin: Plugin = {
      id: 'valueLabels',
      afterDatasetsDraw: chart => {
        const ctx = (chart as any).ctx;
        chart.data.datasets.forEach((ds, dsIndex) => {
          const meta = (chart as any).getDatasetMeta(dsIndex);
          if (!meta || meta.type !== 'bar') return;
          meta.data.forEach((elem: any, idx: number) => {
            const val = (ds.data || [])[idx];
            if (val == null) return;
            ctx.save(); ctx.fillStyle = '#374151'; ctx.font = '12px system-ui'; ctx.textAlign = 'center'; ctx.fillText(String(val), elem.x, elem.y - 6); ctx.restore();
          });
        });
      }
    };
    try { Chart.register(plugin); (ProfileSetting as any)._dataLabelPluginRegistered = true; } catch (e) { /* ignore */ }
  }

  private loadProfile() {
    this.loading.set(true);
    this.user = this.userService.getUser();
    if (!this.user) { this.msg.add({ severity: 'error', summary: 'Error', detail: 'User not found' }); this.loading.set(false); return; }

    const roleId = this.userService.getRoleId();
    // student
    if (roleId === USER_ROLES.ROLE_STUDENT) {
      const sid = Number(this.user.StudentID || this.user.student_id || this.user.id || NaN);
      if (!isNaN(sid) && sid) {
        this.students.getStudent(sid).subscribe({ next: s => { this.student = s; this.loading.set(false); this.loadStudentAttendance(s); }, error: () => { this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load student' }); this.loading.set(false); } });
        return;
      }
      // fallback: treat tx-user as tx-student and fetch fallback chart
      this.student = this.user;
      this.students.getStudentMonthlyAttendanceFallback().subscribe({ next: chart => { this.applyAttendance(chart, this.student?.AdmissionDate ? new Date(String(this.student.AdmissionDate)) : null); this.loading.set(false); }, error: () => this.loading.set(false) });
      return;
    }

    // admin roles: show tx-user only, no attendance — do NOT set employee so template will render tx-user panels
    if (roleId === USER_ROLES.ROLE_SUPERADMIN || roleId === USER_ROLES.ROLE_SCHOOLADMIN) {
      this.loading.set(false);
      return;
    }

    // treat as employee/teacher
    const eid = Number(this.user.EmployeeID || this.user.employee_id || this.user.id || NaN);
    if (!isNaN(eid) && eid) {
      this.employees.getEmployee(eid).subscribe({ next: e => { this.employee = e; this.loading.set(false); this.loadEmployeeAttendance(e); }, error: () => { this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load employee' }); this.loading.set(false); } });
      return;
    }

    // fallback: show tx-user as employee with fallback chart
    this.employee = this.user;
    this.employees.getEmployeeMonthlyAttendanceFallback().subscribe({ next: chart => { this.applyAttendance(chart); this.loading.set(false); }, error: () => this.loading.set(false) });
  }

  private loadStudentAttendance(s: any) {
    const sid = Number(s?.StudentID || s?.id || NaN);
    if (!sid) return;
    this.students.getStudentMonthlyAttendance(sid).subscribe({ next: chart => this.applyAttendance(chart, s?.AdmissionDate ? new Date(String(s.AdmissionDate)) : null), error: () => { this.attendanceLineData = { labels: [], datasets: [] }; this.attendanceSummary.set([]); } });
  }

  private loadEmployeeAttendance(e: any) {
    const eid = Number(e?.EmployeeID || e?.id || NaN);
    if (!eid) return;
    this.employees.getEmployeeMonthlyAttendance(eid).subscribe({ next: chart => this.applyAttendance(chart, e?.JoiningDate ? new Date(String(e.JoiningDate)) : null), error: () => { this.attendanceLineData = { labels: [], datasets: [] }; this.attendanceSummary.set([]); } });
  }

  private applyAttendance(chart: any, joinOrAdmission: Date | null = null) {
    if (!chart || !chart.labels) { this.attendanceLineData = { labels: [], datasets: [] }; this.attendanceSummary.set([]); return; }
    // reuse simple transform: keep numeric datasets as bars
    const datasets = (chart.datasets || []).map((d: any) => ({ ...d, type: 'bar', yAxisID: 'counts', borderColor: d.borderColor || '#4f46e5', backgroundColor: d.backgroundColor || 'rgba(79,70,229,0.12)' }));
    this.attendanceLineData = { labels: chart.labels || [], datasets };
    try {
      const labels = chart.labels || [];
      const dsPresent = datasets.find((d:any) => (d.label||'').toString().toLowerCase().includes('present')) || datasets[0];
      const dsWorking = datasets.find((d:any) => (d.label||'').toString().toLowerCase().includes('working'));
      const summary = labels.map((lab:any, idx:number) => { const working = dsWorking ? Number(dsWorking.data[idx] ?? 0) : 0; const present = dsPresent ? Number(dsPresent.data[idx] ?? 0) : 0; const percent = working ? Math.round((present/working)*100) : 0; return { month: lab, workingDays: working, present, percent }; });
      this.attendanceSummary.set(summary);
    } catch (e) { this.attendanceSummary.set([]); }
  }

  initials(name: string) { if (!name) return ''; return name.split(/\s+/).filter(Boolean).slice(0,2).map(p=>p[0].toUpperCase()).join(''); }
  displayName() { const e = this.student || this.employee || this.user; if (!e) return ''; return (e.FirstName ? ([e.FirstName,e.MiddleName,e.LastName].filter(Boolean).join(' ')) : (e.StudentName || e.EmployeeName || e.username || e.name || '')); }

  statusSeverity(status?: string) { switch ((status || '').toLowerCase()) { case 'active': return 'success'; case 'inactive': return 'danger'; case 'pending': return 'warning'; default: return 'info'; } }
  getEmail() { const e = this.student || this.employee || this.user; return e?.EmailID || e?.email || e?.Email || '-'; }
  getContact() { const e = this.student || this.employee || this.user; return e?.ContactNumber || e?.Contact || e?.phone || '-'; }
  getDob() { const e = this.student || this.employee || this.user; return e?.DOB || e?.dob || e?.DateOfBirth || null; }
  getClassName() { return this.student?.ClassName || this.student?.Class || '-'; }
  getSectionName() { return this.student?.SectionName || this.student?.Section || '-'; }
  getRoleName() { return this.employee?.RoleName || this.employee?.Role || this.employee?.designation || '-'; }

  changePassword() {
    if (!this.changePasswordForm.valid) { this.changePasswordForm.markAllAsTouched(); this.msg.add({ severity: 'warn', summary: 'Validation', detail: 'Please correct the form errors' }); return; }
    const userId = this.userService.getUserId();
    if (!userId) { this.msg.add({ severity: 'error', summary: 'Error', detail: 'User not available' }); return; }
    const vals = this.changePasswordForm.value;
    this.profileService.changePassword(userId, vals.oldPassword, vals.newPassword).subscribe({ next: (res:any) => { this.msg.add({ severity: 'success', summary: 'Success', detail: 'Password changed' }); this.changePasswordForm.reset(); }, error: (err:any) => { const detail = err?.error?.message || err?.message || 'Failed to change password'; this.msg.add({ severity: 'error', summary: 'Error', detail }); } });
  }
}
