import { Component } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators, FormGroup } from '@angular/forms';
import { InputTextModule } from 'primeng/inputtext';
// Calendar removed due to version issues; using native input type=date
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { MessageService } from 'primeng/api';
import { ToastModule } from 'primeng/toast';
import { StudentsService } from '../../services/students.service';
import { AcademicYearService } from '../../services/academic-year.service';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../../environments/environment';
import { toLocalYMDIST, toISOStringNoonIST } from '../../../../utils/date-utils';

@Component({
    selector: 'app-student-admission',
    standalone: true,
    imports: [CommonModule, ReactiveFormsModule, InputTextModule, SelectModule, DatePickerModule, ButtonModule, CardModule, ToastModule],
    providers: [MessageService],
    templateUrl: './student-admission.html',
    styleUrl: './student-admission.scss'
})
export class StudentAdmission {
    form!: FormGroup;
    loading = false;
    issuedCredentials: { username: string; password: string } | null = null;
    editingId: number | null = null;
    studentUsername: string | null = null; // existing username in edit mode
    // holds the originally loaded student data in edit mode so Reset restores it
    originalData: any = null;

    classOptions: any[] = [];
    sectionOptions: any[] = [];
    filteredSectionOptions: any[] = [];
    academicYearOptions: any[] = [];

    genders = [
        { label: 'Male', value: 'M' },
        { label: 'Female', value: 'F' },
        { label: 'Other', value: 'O' }
    ];

    constructor(
        private fb: FormBuilder,
        private studentsService: StudentsService,
        private msg: MessageService,
        private academicYearService: AcademicYearService,
        private route: ActivatedRoute,
        private router: Router,
        private http: HttpClient
    ) {
        this.form = this.fb.group({
            FirstName: ['', [Validators.required, Validators.minLength(2)]],
            MiddleName: [''],
            LastName: [''],
            ContactNumber: [''],
            EmailID: ['', [Validators.email]],
            Gender: ['', Validators.required],
            DOB: [null, Validators.required],
            AcademicYearID: [null, Validators.required],
            ClassID: [null, Validators.required],
            SectionID: [null, Validators.required],
            FatherName: ['', Validators.required],
            FatherContactNumber: [''],
            MotherName: [''],
            MotherContactNumber: [''],
            AdmissionDate: [new Date()]
        });
        this.loadClasses();
        this.loadAcademicYears();
        this.route.queryParams.subscribe((params) => {
            const id = params['id'];
            if (id) {
                const nid = Number(id);
                if (!isNaN(nid)) this.loadForEdit(nid);
            }
        });
    }

    private loadForEdit(id: number) {
        this.editingId = id;
        this.studentsService.getStudent(id).subscribe({
            next: (s: any) => {
                if (!s) return;
                // Map backend fields to form fields. Be defensive about property names.
                // If backend provides a single StudentName, split into First/Middle/Last
                let first = s.FirstName || s.first_name || '';
                let middle = s.MiddleName || s.middle_name || '';
                let last = s.LastName || s.last_name || '';
                const full = s.StudentName || s.student_name || s.name || '';
                if (full && !(first || middle || last)) {
                    const parts = full.trim().split(/\s+/);
                    if (parts.length === 1) {
                        first = parts[0];
                    } else if (parts.length === 2) {
                        first = parts[0];
                        last = parts[1];
                    } else if (parts.length >= 3) {
                        first = parts[0];
                        last = parts[parts.length - 1];
                        middle = parts.slice(1, parts.length - 1).join(' ');
                    }
                }

                this.form.patchValue({
                    FirstName: first,
                    MiddleName: middle,
                    LastName: last,
                    ContactNumber: s.ContactNumber || s.contact_number || '',
                    EmailID: s.EmailID || s.email || s.email_id || '',
                    Gender: s.Gender || s.gender || '',
                    DOB: s.DOB ? new Date(s.DOB) : s.dob ? new Date(s.dob) : null,
                    AcademicYearID: s.AcademicYearID || s.academic_year_id || null,
                    ClassID: s.ClassID || s.class_id || null,
                    SectionID: s.SectionID || s.section_id || null,
                    FatherName: s.FatherName || s.father_name || '',
                    FatherContactNumber: s.FatherContactNumber || s.father_contact_number || '',
                    MotherName: s.MotherName || s.mother_name || '',
                    MotherContactNumber: s.MotherContactNumber || s.mother_contact_number || '',
                    AdmissionDate: s.AdmissionDate ? new Date(s.AdmissionDate) : s.admission_date ? new Date(s.admission_date) : new Date()
                });
                this.studentUsername = s.Username || s.username || null;
                // keep a copy of loaded values so Reset can restore them
                this.originalData = { ...this.form.value };
                // If class is present, load sections for that class so SectionID select is populated
                const classId = this.form.value.ClassID;
                if (classId) this.onClassChange(classId);
            },
            error: () => this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load student' })
        });
    }

    loadAcademicYears() {
        this.academicYearOptions = [];
        this.academicYearService.getAcademicYears().subscribe((yrs: any[]) => {
            this.academicYearOptions = yrs.map((y: any) => ({ label: y.AcademicYearName || y.name, value: y.AcademicYearID || y.id }));
        });
    }

    loadClasses() {
        this.studentsService.getClasses().subscribe((classes) => {
            this.classOptions = classes.map((c: any) => ({ label: c.ClassName, value: c.ClassID }));
        });
    }

    onClassChange(classId: number) {
        this.sectionOptions = [];
        this.form.patchValue({ SectionID: null });
        if (!classId) return;
        // if we're in edit mode and originalData has a SectionID, remember it
        const preselectedSection = this.editingId && this.originalData ? this.originalData.SectionID || this.originalData.section_id || null : null;
        this.studentsService.getSections(classId).subscribe((sections) => {
            this.sectionOptions = sections.map((s: any) => ({ label: s.SectionName || s.section_name, value: s.SectionID || s.section_id }));
            // restore the previously loaded section when editing
            if (preselectedSection) {
                // Only patch if the option exists in loaded sections
                const found = this.sectionOptions.find((o) => o.value === preselectedSection);
                if (found) this.form.patchValue({ SectionID: preselectedSection });
            }
        });
    }

    submit() {
        if (this.form.invalid) {
            this.form.markAllAsTouched();
            return;
        }
        this.loading = true;
        const payload = { ...this.form.value };
        // Format dates to yyyy-MM-dd
        // Format dates as local yyyy-MM-dd to avoid timezone shifts (toISOString() uses UTC and may reduce the day)
        // Use centralized IST helpers to format dates so backend and frontend agree on date semantics
        payload.DOB = toLocalYMDIST(payload.DOB);
        payload.AdmissionDate = toLocalYMDIST(payload.AdmissionDate);
        // Some backend APIs accept snake_case fields; include both variants to be defensive
        payload.dob = payload.DOB;
        payload.admission_date = payload.AdmissionDate;
        // Also include ISO timestamp (set to noon UTC to avoid timezone day-shift issues) and alternative keys
        try {
            const ad = payload.AdmissionDate;
            if (ad) {
                const isoNoon = toISOStringNoonIST(ad);
                if (isoNoon) {
                    payload.AdmissionDateISO = isoNoon;
                    payload.admission_date_iso = isoNoon;
                    payload.Admission_Date = payload.AdmissionDate;
                    payload.admissionDate = payload.AdmissionDate;
                }
            }
        } catch (e) {
            // ignore formatting errors
        }
        // Ensure backend receives a single StudentName (concat first/middle/last) as some APIs expect this
        const parts = [this.form.value.FirstName, this.form.value.MiddleName, this.form.value.LastName].filter((p: any) => p && String(p).trim() !== '');
        if (parts.length) {
            payload.StudentName = parts.join(' ');
        }
        // Move selected Class/Section IDs
        if (payload.ClassID) delete payload.ClassID; // backend expects SectionID only
        const finish = () => {
            this.loading = false;
        };
        if (this.editingId) {
            // In edit mode, call updateStudent. Backend may expect different shape; try sending form values.
            // For update we now keep First/Middle/Last plus ContactNumber, EmailID (backend supports them)
            // debug
            try {
                console.debug('updateStudent - sending payload', { id: this.editingId, payload });
            } catch (e) {}
            this.studentsService.updateStudent(this.editingId, payload).subscribe({
                next: (res) => {
                    finish();
                    try {
                        console.debug('updateStudent - response', res);
                    } catch (e) {}
                    if (res) {
                        this.msg.add({ severity: 'success', summary: 'Updated', detail: 'Student updated successfully' });
                        // Give the toast a moment to render before navigating so user sees the message
                        try {
                            setTimeout(() => this.router.navigate(['/features/all-students']), 200);
                        } catch (e) {
                            /* ignore navigation failures in tests */
                        }
                    } else {
                        this.msg.add({ severity: 'error', summary: 'Error', detail: 'Update failed' });
                    }
                },
                error: (err) => {
                    finish();
                    try {
                        console.debug('updateStudent - error', err);
                    } catch (e) {}
                    this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Update failed' });
                }
            });
        } else {
            try {
                console.debug('admitStudent - sending payload', payload);
            } catch (e) {}
            this.studentsService.admitStudent(payload).subscribe({
                next: (res) => {
                    this.loading = false;
                    try {
                        console.debug('admitStudent - response', res);
                    } catch (e) {}
                    if (res?.success) {
                        this.issuedCredentials = res.data?.credentials || null;
                        this.msg.add({ severity: 'success', summary: 'Admitted', detail: 'Student admitted successfully' });
                        this.form.reset({
                            FirstName: '',
                            MiddleName: '',
                            LastName: '',
                            ContactNumber: '',
                            EmailID: '',
                            Gender: '',
                            DOB: null,
                            AcademicYearID: null,
                            ClassID: null,
                            SectionID: null,
                            FatherName: '',
                            FatherContactNumber: '',
                            MotherName: '',
                            MotherContactNumber: '',
                            AdmissionDate: new Date()
                        });
                    } else {
                        this.msg.add({ severity: 'error', summary: 'Error', detail: res?.message || 'Failed' });
                    }
                },
                error: (err) => {
                    this.loading = false;
                    try {
                        console.debug('admitStudent - error', err);
                    } catch (e) {}
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
        this.form.reset({ FirstName: '', MiddleName: '', LastName: '', Gender: '', AdmissionDate: new Date() });
        // also clear new fields
        this.form.patchValue({ ContactNumber: '', EmailID: '', DOB: null, AcademicYearID: null, ClassID: null, SectionID: null, FatherName: '', FatherContactNumber: '', MotherName: '', MotherContactNumber: '' });
        this.sectionOptions = [];
        this.issuedCredentials = null;
    }

    /**
     * Restore the originally loaded student values when editing.
     * If not in edit mode, behave like resetForm().
     */
    restoreLoaded() {
        if (this.editingId && this.originalData) {
            this.form.patchValue({ ...this.originalData });
            // ensure section options are loaded for the current class
            const classId = this.form.value.ClassID;
            if (classId) this.onClassChange(classId);
            this.issuedCredentials = null;
        } else {
            this.resetForm();
        }
    }

    resetPassword() {
        if (!this.editingId) return;
        const base = environment.baseURL.replace(/\/+$/, '');
        this.loading = true;
        this.http.post<any>(`${base}/api/students/${this.editingId}/reset-password`, {}).subscribe({
            next: (res) => {
                this.loading = false;
                if (res?.success) {
                    const pwd = res.data?.password;
                    const user = res.data?.username || this.studentUsername;
                    this.msg.add({ severity: 'success', summary: 'Password Reset', detail: 'New password generated' });
                    this.issuedCredentials = { username: user, password: pwd };
                } else {
                    this.msg.add({ severity: 'error', summary: 'Error', detail: res?.message || 'Reset failed' });
                }
            },
            error: (err) => {
                this.loading = false;
                this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Reset failed' });
            }
        });
    }
}
