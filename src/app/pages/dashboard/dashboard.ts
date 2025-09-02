import { Component } from '@angular/core';
import { NotificationsWidget } from './components/notificationswidget';
import { StatsWidget } from './components/statswidget';
import { RecentSalesWidget } from './components/recentsaleswidget';
import { BestSellingWidget } from './components/bestsellingwidget';
import { RevenueStreamWidget } from './components/revenuestreamwidget';
import { USER_ROLES } from '@/pages/common/constant';
import { SchoolAdminDashboard } from "../features/dashboard/school-admin-dashboard/school-admin-dashboard";
import { SuperAdminDashboard } from "../features/dashboard/super-admin-dashboard/super-admin-dashboard";
import { TeacherDashboard } from "../features/dashboard/teacher-dashboard/teacher-dashboard";
import { StudentDashboard } from "../features/dashboard/student-dashboard/student-dashboard";
import { CommonModule } from '@angular/common';
import { inject, OnDestroy } from '@angular/core';
import { Subscription } from 'rxjs';
import { UserService } from '@/services/user.service';
@Component({
    selector: 'app-dashboard',
    imports: [CommonModule, SuperAdminDashboard, SchoolAdminDashboard, TeacherDashboard, StudentDashboard],
    template: `
        @if (userRoles === USER_ROLES.ROLE_SUPERADMIN){
        <app-super-admin-dashboard/>
        }
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
export class Dashboard implements OnDestroy {
    // subscribe to user changes so dashboard updates when user logs out/logs in in same window
    private userService = inject(UserService);
    userRoles: number = 0;
    private userSub: Subscription;

    constructor() {
        // update role id whenever stored user changes
        this.userSub = this.userService.getUser$().subscribe(u => {
            this.userRoles = (u && ((u.role_id as unknown as number) ?? 0)) || 0;
        });
    }

    ngOnDestroy(): void {
        this.userSub?.unsubscribe();
    }
    // ✅ expose constant so template can access it
    USER_ROLES = USER_ROLES;
}
