import { Component, OnInit, ViewChild, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Table, TableModule } from 'primeng/table';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { StudentsService } from '../../services/students.service';
import { Student } from '../../model/student.model';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { DialogModule } from 'primeng/dialog';
import { ToastModule } from 'primeng/toast';
import { ConfirmationService, MessageService } from 'primeng/api';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { SelectModule } from 'primeng/select';
import { IconFieldModule } from 'primeng/iconfield';
import { InputIconModule } from 'primeng/inputicon';
@Component({
  selector: 'app-all-students',
  standalone: true,
  imports: [CommonModule, TableModule, ButtonModule, InputTextModule, DialogModule, ToastModule, ConfirmDialogModule, FormsModule, ReactiveFormsModule, SelectModule
    ,InputIconModule,
          IconFieldModule],
  providers: [MessageService, ConfirmationService, StudentsService],
  templateUrl: './all-students.html',
  styleUrl: './all-students.scss'
})
export class AllStudents implements OnInit {
  students = signal<Student[]>([]);
  selected: Student[] | null = null;
  studentDialog = false;
  submitted = false;
  form!: FormGroup;
  genders = [
    { label: 'Male', value: 'M' },
    { label: 'Female', value: 'F' },
    { label: 'Other', value: 'O' }
  ];

  @ViewChild('dt') dt!: Table;

  constructor(private fb: FormBuilder, private studentsService: StudentsService, private msg: MessageService, private confirm: ConfirmationService) {
    this.initForm();
  }

  private initForm() {
    this.form = this.fb.group({
      StudentID: [null],
      StudentName: ['', Validators.required],
      Gender: ['M', Validators.required],
      DOB: ['', Validators.required],
      FatherName: [''],
      MotherName: ['']
    });
  }

  ngOnInit(): void {
    this.load();
  }

  load() {
    this.studentsService.getStudents().subscribe({
      next: data => this.students.set(data),
      error: () => this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load students', life: 3000 })
    });
  }

  openNew() {
    this.submitted = false;
    this.form.reset({ Gender: 'M' });
    this.studentDialog = true;
  }

  edit(stu: Student) {
    this.form.patchValue(stu);
    this.studentDialog = true;
  }

  save() {
    this.submitted = true;
    if (this.form.invalid) return;
    const val = this.form.value;
    if (val.StudentID) {
      this.studentsService.updateStudent(val.StudentID, val).subscribe({
        next: res => { if (res) { this.msg.add({severity:'success', summary:'Updated', detail:'Student updated'}); this.load(); }},
        error: () => this.msg.add({severity:'error', summary:'Error', detail:'Update failed'})
      });
    } else {
      this.studentsService.createStudent(val).subscribe({
        next: res => { if (res) { this.msg.add({severity:'success', summary:'Created', detail:'Student created'}); this.load(); }},
        error: () => this.msg.add({severity:'error', summary:'Error', detail:'Create failed'})
      });
    }
    this.studentDialog = false;
  }

  delete(stu: Student) {
    if (!stu.StudentID) return;
    this.confirm.confirm({
      message: `Delete ${stu.StudentName}?`,
      header: 'Confirm',
      icon: 'pi pi-exclamation-triangle',
      accept: () => {
        this.studentsService.deleteStudent(stu.StudentID!).subscribe({
          next: ok => { if (ok) { this.msg.add({severity:'success', summary:'Deleted', detail:'Student deleted'}); this.load(); } },
          error: () => this.msg.add({severity:'error', summary:'Error', detail:'Delete failed'})
        });
      }
    });
  }

  hideDialog() { this.studentDialog = false; this.submitted = false; }

  onGlobalFilter(table: Table, event: Event) { table.filterGlobal((event.target as HTMLInputElement).value, 'contains'); }

  isInvalid(field: string) { const c = this.form.get(field); return !!c && c.invalid && (c.dirty || c.touched || this.submitted); }

}
