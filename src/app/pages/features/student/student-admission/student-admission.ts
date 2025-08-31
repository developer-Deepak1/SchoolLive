import { Component } from '@angular/core';
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

  classOptions: any[] = [];
  sectionOptions: any[] = [];
  filteredSectionOptions: any[] = [];

  genders = [
    { label: 'Male', value: 'M' },
    { label: 'Female', value: 'F' },
    { label: 'Other', value: 'O' }
  ];

  constructor(private fb: FormBuilder, private studentsService: StudentsService, private msg: MessageService) {
    this.form = this.fb.group({
      FirstName: ['', [Validators.required, Validators.minLength(2)]],
      MiddleName: [''],
      LastName: [''],
      Gender: ['M', Validators.required],
  DOB: [null, Validators.required],
  ClassID: [null, Validators.required],
  SectionID: [null, Validators.required],
      FatherName: [''],
      FatherContactNumber: [''],
      MotherName: [''],
      MotherContactNumber: [''],
      AdmissionDate: [new Date()]
    });
    this.loadClasses();
  }

  loadClasses() {
    this.studentsService.getClasses().subscribe(classes => {
      this.classOptions = classes.map((c: any)=> ({ label: c.ClassName, value: c.ClassID }));
    });
  }

  onClassChange(classId: number) {
    this.sectionOptions = [];
    this.form.patchValue({ SectionID: null });
    if (!classId) return;
    this.studentsService.getSections(classId).subscribe(sections => {
      this.sectionOptions = sections.map((s: any)=> ({ label: s.SectionName || s.section_name, value: s.SectionID || s.section_id }));
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
  const toISO = (d: any) => d instanceof Date ? d.toISOString().substring(0,10) : d;
  payload.DOB = toISO(payload.DOB);
  payload.AdmissionDate = toISO(payload.AdmissionDate);
  // Move selected Class/Section IDs
  if (payload.ClassID) delete payload.ClassID; // backend expects SectionID only
    this.studentsService.admitStudent(payload).subscribe({
      next: res => {
        this.loading = false;
        if (res?.success) {
          this.issuedCredentials = res.data?.credentials || null;
          this.msg.add({severity:'success', summary:'Admitted', detail:'Student admitted successfully'});
          this.form.reset({ FirstName:'', MiddleName:'', LastName:'', Gender:'M', AdmissionDate: new Date() });
        } else {
          this.msg.add({severity:'error', summary:'Error', detail: res?.message || 'Failed'});
        }
      },
      error: err => { this.loading = false; this.msg.add({severity:'error', summary:'Error', detail: err.error?.message || 'Request failed'}); }
    });
  }

  fieldInvalid(name: string) {
    const c = this.form.get(name); return !!c && c.invalid && (c.touched || c.dirty);
  }

  resetForm() {
  this.form.reset({ FirstName:'', MiddleName:'', LastName:'', Gender:'M', AdmissionDate: new Date() });
    this.sectionOptions = [];
    this.issuedCredentials = null;
  }

}
