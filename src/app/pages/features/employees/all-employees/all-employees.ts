import { Component, OnInit, ViewChild, signal } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { Table, TableModule } from 'primeng/table';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { EmployeesService } from '@/pages/features/services/employees.service';
import { HttpClient } from '@angular/common/http';
import { Employee } from '@/pages/features/model/employee.model';
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
  selector: 'app-all-employees',
  standalone: true,
  imports: [CommonModule, TableModule, ButtonModule, InputTextModule, DialogModule, ToastModule, ConfirmDialogModule, FormsModule, ReactiveFormsModule, SelectModule
    ,InputIconModule,
          IconFieldModule],
  providers: [MessageService, ConfirmationService, EmployeesService],
  templateUrl: './all-employees.html',
  styleUrl: './all-employees.scss'
})
export class AllEmployees implements OnInit {
  employees = signal<Employee[]>([]);
  // fields to use for global filtering in the table
  tableGlobalFilterFields: string[] = ['Username', 'EmployeeName', 'RoleName', 'Gender', 'JoiningDate'];
  selected: Employee[] | null = null;
  employeeDialog = false;
  submitted = false;
  form!: FormGroup;
  genders = [
    { label: 'Male', value: 'M' },
    { label: 'Female', value: 'F' },
    { label: 'Other', value: 'O' }
  ];

  @ViewChild('dt') dt!: Table;
  roleOptions: any[] = [];

  constructor(private fb: FormBuilder, private employeesService: EmployeesService, private msg: MessageService, private confirm: ConfirmationService, private router: Router, private http: HttpClient) {
    this.initForm();
  }

  private initForm() {
    this.form = this.fb.group({
      EmployeeID: [null],
      EmployeeName: ['', Validators.required],
      Gender: ['M', Validators.required],
      DOB: ['', Validators.required],
  RoleID: [null],
      JoiningDate: ['']
    });
  }

  ngOnInit(): void {
    this.load();
    // load roles for dialog select
    const base = (window as any).__env?.baseURL || '';
    const url = base.replace(/\/+$/,'') + '/api/roles';
    try {
      this.http.get<any>(url).subscribe({
        next: res => {
          const rows = Array.isArray(res) ? res : (res?.data || []);
          const filtered = rows.filter((r: any) => {
            const rn = (r.RoleName || '').toString().toLowerCase();
            const rd = (r.RoleDisplayName || '').toString().toLowerCase();
            return rn === 'teacher' || rd === 'teacher';
          });
          this.roleOptions = filtered.map((r: any) => ({ label: r.RoleDisplayName || r.RoleName, value: r.RoleID || r.id }));
        },
        error: () => {}
      });
    } catch (e) {}
  }

  load() {
    this.employeesService.getEmployees().subscribe({
      next: (data: Employee[]) => this.employees.set(data),
      error: () => this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load employees', life: 3000 })
    });
  }

  openNew() {
    this.submitted = false;
    this.form.reset({ Gender: 'M' });
    this.employeeDialog = true;
  }

  edit(emp: Employee) {
    if (emp && emp.EmployeeID) {
      this.router.navigate(['/features/add-employees'], { queryParams: { id: emp.EmployeeID } });
    } else {
  // normalize RoleID if RoleID or role_id present
  const patch = { ...emp } as any;
  if (emp && (emp as any).RoleID) patch.RoleID = (emp as any).RoleID;
  this.form.patchValue(patch || {});
      this.employeeDialog = true;
    }
  }

  viewProfile(emp: Employee) {
    if (emp && emp.EmployeeID) this.router.navigate(['/features/employee-profile', emp.EmployeeID]);
  }

  save() {
    this.submitted = true;
    if (this.form.invalid) return;
    const val = this.form.value;
    if (val.EmployeeID) {
      this.employeesService.updateEmployee(val.EmployeeID, val).subscribe({
        next: (res: Employee|null) => { if (res) { this.msg.add({severity:'success', summary:'Updated', detail:'Employee updated'}); this.load(); }},
        error: () => this.msg.add({severity:'error', summary:'Error', detail:'Update failed'})
      });
    } else {
      this.employeesService.createEmployee(val).subscribe({
        next: (res: Employee|null) => { if (res) { this.msg.add({severity:'success', summary:'Created', detail:'Employee created'}); this.load(); }},
        error: () => this.msg.add({severity:'error', summary:'Error', detail:'Create failed'})
      });
    }
    this.employeeDialog = false;
  }

  delete(emp: Employee) {
    if (!emp.EmployeeID) return;
    this.confirm.confirm({
      message: `Are you sure you want to delete ${emp.EmployeeName}?`,
      header: 'Confirm',
      icon: 'pi pi-exclamation-triangle',
      accept: () => {
        this.employeesService.deleteEmployee(emp.EmployeeID!).subscribe({
          next: (ok: boolean) => { if (ok) { this.msg.add({severity:'success', summary:'Deleted', detail:'Employee deleted'}); this.load(); } },
          error: () => this.msg.add({severity:'error', summary:'Error', detail:'Delete failed'})
        });
      }
    });
  }

  hideDialog() { this.employeeDialog = false; this.submitted = false; }

  onGlobalFilter(table: Table, event: Event) { table.filterGlobal((event.target as HTMLInputElement).value, 'contains'); }

  isInvalid(field: string) { const c = this.form.get(field); return !!c && c.invalid && (c.dirty || c.touched || this.submitted); }

}
