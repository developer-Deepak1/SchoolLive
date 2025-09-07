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
import { EmployeeProfile } from "../employees/employee-profile/employee-profile";
import { StudentProfile } from "../student/student-profile/student-profile";
import { SchoolAdminProfile } from "../school-admin-profile/school-admin-profile";
@Component({
  selector: 'app-profile-setting',
  standalone: true,
  imports: [CommonModule, PasswordModule, EmployeeProfile, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule, FormsModule, ReactiveFormsModule, InputTextModule, HttpClientModule, StudentProfile, SchoolAdminProfile],
  providers: [MessageService],
  templateUrl: './profile-setting.html',
  styleUrls: ['./profile-setting.scss']
})
export class ProfileSetting implements OnInit {
  userId: any = null; // tx-user
  userRoles = USER_ROLES;
  RoleId:number|null = null;

  // change password fields
  // reactive form for change password
  changePasswordForm!: FormGroup;

  constructor(private userService: UserService, private students: StudentsService, private employees: EmployeesService, private profileService: ProfileService, private msg: MessageService, private http: HttpClient) {}

  ngOnInit(): void {
    this.initForm();
    this.userId = this.userService.getUserId();
    this.RoleId = this.userService.getRoleId();
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

  changePassword() {
    if (!this.changePasswordForm.valid) { this.changePasswordForm.markAllAsTouched(); this.msg.add({ severity: 'warn', summary: 'Validation', detail: 'Please correct the form errors' }); return; }
    const userId = this.userService.getUserId();
    if (!userId) { this.msg.add({ severity: 'error', summary: 'Error', detail: 'User not available' }); return; }
    const vals = this.changePasswordForm.value;
    this.profileService.changePassword(userId, vals.oldPassword, vals.newPassword).subscribe({ next: (res:any) => { this.msg.add({ severity: 'success', summary: 'Success', detail: 'Password changed' }); this.changePasswordForm.reset(); }, error: (err:any) => { const detail = err?.error?.message || err?.message || 'Failed to change password'; this.msg.add({ severity: 'error', summary: 'Error', detail }); } });
  }
}
