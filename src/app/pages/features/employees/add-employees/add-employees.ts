import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators, FormGroup } from '@angular/forms';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { MessageService } from 'primeng/api';
import { ToastModule } from 'primeng/toast';
import { EmployeesService } from '../../services/employees.service';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../../environments/environment';
import { toLocalYMDIST, toISOStringNoonIST } from '../../../../utils/date-utils';

@Component({
    selector: 'app-add-employees',
    standalone: true,
    imports: [CommonModule, ReactiveFormsModule, InputTextModule, SelectModule, DatePickerModule, ButtonModule, CardModule, ToastModule],
    providers: [MessageService],
    templateUrl: './add-employees.html',
    styleUrl: './add-employees.scss'
})
export class AddEmployees {
    form!: FormGroup;
    loading = false;
    // guard to prevent multiple concurrent reset-password requests
    resetting = false;
    issuedCredentials: { username: string; password: string } | null = null;
    editingId: number | null = null;
    employeeUsername: string | null = null;
    originalData: any = null;

    genders = [
        { label: 'Male', value: 'M' },
        { label: 'Female', value: 'F' },
        { label: 'Other', value: 'O' }
    ];

    roleOptions: any[] = [];

    constructor(
        private fb: FormBuilder,
        private employeesService: EmployeesService,
        private msg: MessageService,
        private route: ActivatedRoute,
        private router: Router,
        private http: HttpClient
    ) {
        this.form = this.fb.group({
            EmployeeName: ['', [Validators.required, Validators.minLength(2)]],
            ContactNumber: [''],
            EmailID: ['', [Validators.email]],
            Gender: ['', Validators.required],
            DOB: [null, Validators.required],
            RoleID: [null],
            JoiningDate: [null],
            Salary: ['']
        });

        this.route.queryParams.subscribe((params) => {
            const id = params['id'];
            if (id) {
                const nid = Number(id);
                if (!isNaN(nid)) this.loadForEdit(nid);
            }
        });

        this.loadRoles();
    }

    private loadForEdit(id: number) {
        this.editingId = id;
        this.employeesService.getEmployee(id).subscribe({
            next: (e: any) => {
                if (!e) return;
                // Map values defensively
                this.form.patchValue({
                    EmployeeName: e.EmployeeName || e.employee_name || e.name || '',
                    ContactNumber: e.ContactNumber || e.contact_number || '',
                    EmailID: e.EmailID || e.email || e.email_id || '',
                    Gender: e.Gender || e.gender || 'M',
                    DOB: e.DOB ? new Date(e.DOB) : e.dob ? new Date(e.dob) : null,
                    RoleID: e.RoleID || e.role_id || e.RoleID || null,
                    JoiningDate: e.JoiningDate ? new Date(e.JoiningDate) : e.joining_date ? new Date(e.joining_date) : null,
                    Salary: e.Salary || e.salary || ''
                });
                this.employeeUsername = e.Username || e.username || null;
                this.originalData = { ...this.form.value };
            },
            error: () => this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load employee' })
        });
    }

    private loadRoles() {
        const base = environment.baseURL.replace(/\/+$/, '');
        this.http.get<any>(`${base}/api/roles`).subscribe({
            next: (res) => {
                // backend may return envelope or raw array; normalize
                const rows = Array.isArray(res) ? res : res?.data || res?.roles || [];
                // For now show only a single role (Teacher) in the UI to simplify selection
                const filtered = rows.filter((r: any) => {
                    const rn = (r.RoleName || '').toString().toLowerCase();
                    const rd = (r.RoleDisplayName || '').toString().toLowerCase();
                    return rn === 'teacher' || rd === 'teacher';
                });
                this.roleOptions = filtered.map((r: any) => ({ label: r.RoleDisplayName || r.RoleName, value: r.RoleID || r.id }));
            },
            error: () => {
                /* ignore role load failures for now */
            }
        });
    }

    submit() {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        this.loading = true;
        const payload: any = { ...this.form.value };
        // format dates
        payload.DOB = toLocalYMDIST(payload.DOB);
        payload.JoiningDate = toLocalYMDIST(payload.JoiningDate);
        payload.dob = payload.DOB;
        payload.joining_date = payload.JoiningDate;
        // ensure consistent role key for backend
        if (payload.RoleID !== undefined) payload.role_id = payload.RoleID;
        try {
            const jd = payload.JoiningDate;
            if (jd) {
                const isoNoon = toISOStringNoonIST(jd);
                if (isoNoon) payload.JoiningDateISO = isoNoon;
            }
        } catch (e) {}

        const finish = () => {
            this.loading = false;
        };
        if (this.editingId) {
            this.employeesService.updateEmployee(this.editingId, payload).subscribe({
                next: (res) => {
                    finish();
                    if (res) {
                        this.msg.add({ severity: 'success', summary: 'Updated', detail: 'Employee updated successfully' });
                        setTimeout(() => this.router.navigate(['/features/all-employees']), 200);
                    } else {
                        this.msg.add({ severity: 'error', summary: 'Error', detail: 'Update failed' });
                    }
                },
                error: (err) => {
                    finish();
                    this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Update failed' });
                }
            });
        } else {
            this.employeesService.createEmployee(payload).subscribe({
                next: (res) => {
                    finish();
                    if (res) {
                        this.msg.add({ severity: 'success', summary: 'Created', detail: 'Employee created successfully' });
                        this.form.reset({ EmployeeName: '', ContactNumber: '', EmailID: '', Gender: '', DOB: null, RoleID: null, JoiningDate: null, Salary: '' });
                    } else {
                        this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to create employee' });
                    }
                },
                error: (err) => {
                    finish();
                    this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Request failed' });
                }
            });
        }
    }

    fieldInvalid(name: string) {
        const c = this.form.get(name);
        return !!c && c.invalid && (c.touched || c.dirty);
    }
    resetForm() {
        this.form.reset({ EmployeeName: '', ContactNumber: '', EmailID: '', Gender: '', DOB: null, RoleID: null, JoiningDate: null, Salary: '' });
        this.issuedCredentials = null;
    }

    restoreLoaded() {
        if (this.editingId && this.originalData) {
            this.form.patchValue({ ...this.originalData });
            this.issuedCredentials = null;
        } else {
            this.resetForm();
        }
    }

    resetPassword() {
        if (!this.editingId) return;
        if (this.resetting) return;
        this.resetting = true;
        const base = environment.baseURL.replace(/\/+$/, '');
        this.loading = true;
        this.http.post<any>(`${base}/api/employees/${this.editingId}/reset-password`, {}).subscribe({
            next: (res) => {
                this.loading = false;
                this.resetting = false;
                if (res?.success) {
                    const pwd = res.data?.password;
                    const user = res.data?.username || this.employeeUsername;
                    this.msg.add({ severity: 'success', summary: 'Password Reset', detail: 'New password generated' });
                    this.issuedCredentials = { username: user, password: pwd };
                } else {
                    this.msg.add({ severity: 'error', summary: 'Error', detail: res?.message || 'Reset failed' });
                }
            },
            error: (err) => {
                this.loading = false;
                this.resetting = false;
                this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Reset failed' });
            }
        });
    }
    viewProfile() {
        if (this.editingId) this.router.navigate(['/features/employee-profile', this.editingId]);
    }
}
