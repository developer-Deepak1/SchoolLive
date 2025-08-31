import { Component } from '@angular/core';
import { NotificationsWidget } from './components/notificationswidget';
import { StatsWidget } from './components/statswidget';
import { RecentSalesWidget } from './components/recentsaleswidget';
import { BestSellingWidget } from './components/bestsellingwidget';
import { RevenueStreamWidget } from './components/revenuestreamwidget';
import { USER_ROLES } from '@/pages/common/constant';
import { SchoolAdminDashboard } from "../features/dashboard/school-admin-dashboard/school-admin-dashboard";
import { TeacherDashboard } from "../features/dashboard/teacher-dashboard/teacher-dashboard";
import { StudentDashboard } from "../features/dashboard/student-dashboard/student-dashboard";
import { CommonModule } from '@angular/common';
import { inject } from '@angular/core';
import { UserService } from '@/services/user.service';
@Component({
    selector: 'app-dashboard',
    imports: [CommonModule,SchoolAdminDashboard, TeacherDashboard, StudentDashboard],
    template: `
        @if (userRoles === USER_ROLES.ROLE_SCHOOLADMIN){
        <app-school-admin-dashboard/>
        }
        @if (userRoles === USER_ROLES.ROLE_TEACHER){
        <app-teacher-dashboard/>
        }
        @if (userRoles === USER_ROLES.ROLE_STUDENT){
        <app-student-dashboard/>
        }
    `
})
export class Dashboard {
    // initialize from UserService.getRoleId(), fall back to 0 for compatibility
    private userService = inject(UserService);
    userRoles: number = this.userService.getRoleId() ?? 0;
    // ✅ expose constant so template can access it
    USER_ROLES = USER_ROLES;
}
