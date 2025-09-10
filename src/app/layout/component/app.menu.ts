import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { MenuItem } from 'primeng/api';
import { AppMenuitem } from './app.menuitem';
import { USER_ROLES } from '@/pages/common/constant';
import { inject } from '@angular/core';
import { UserService } from '@/services/user.service';

@Component({
    selector: 'app-menu',
    standalone: true,
    imports: [CommonModule, AppMenuitem, RouterModule],
    template: `<ul class="layout-menu">
        <ng-container *ngFor="let item of model; let i = index">
            <li app-menuitem *ngIf="!item.separator" [item]="item" [index]="i" [root]="true"></li>
            <li *ngIf="item.separator" class="menu-separator"></li>
        </ng-container>
    </ul> `
})
export class AppMenu {
    model: MenuItem[] = [];
    private userService = inject(UserService);
    // initialize from UserService.getRoleId(), fall back to 0
    userRoles: number = this.userService.getRoleId() ?? 0;
    ngOnInit() {
        this.LoadMenu();
    }
    private LoadMenu() {
        if (this.userRoles === USER_ROLES.ROLE_SUPERADMIN) {
            this.model = [
                {
                    label: 'Home',
                    items: [
                    { label: 'Dashboard', icon: 'pi pi-fw pi-home', routerLink: ['/'] },
                    { label: 'Profile Settings', icon: 'pi pi-fw pi-cog', routerLink: ['/features/profile-setting'] },
                    { label: 'Add School', icon: 'pi pi-fw pi-cog', routerLink: ['/features/add-school'] },
                    { label: 'Add User', icon: 'pi pi-fw pi-cog', routerLink: ['/features/add-user'] },
                    { label: 'All Users', icon: 'pi pi-fw pi-cog', routerLink: ['/features/all-users'] },
                    { label: 'All Schools', icon: 'pi pi-fw pi-cog', routerLink: ['/features/all-schools'] }
                ]
            }
        ];
        }
        if (this.userRoles === USER_ROLES.ROLE_SCHOOLADMIN) {
            this.model = [
                {
                    label: 'Home',
                    items: [
                    { label: 'Dashboard', icon: 'pi pi-fw pi-home', routerLink: ['/'] },
                    { label: 'Profile Settings', icon: 'pi pi-fw pi-cog', routerLink: ['/features/profile-setting'] }
                ]
            },
            {
                label: 'Academic Management',
                items: [
                    { label: 'Academic years', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/academic-years'] },
                    { label: 'Academic Calendar', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/academic-calendar'] },
                    { label: 'Add/View Classes', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/add-edit-classes'] }
                ]
            },
            {
                label: 'Employee Management',
                items: [
                    { label: 'All Employees', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/all-employees'] },
                    { label: 'Add New', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/add-employees'] },
                    { label: 'Request Attendance', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/employee-attendance'] }
                ]
            },
            {
                label: 'Student Management',
                items: [
                    { label: 'All Students', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/all-students'] },
                    { label: 'Admission', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/student-admission'] },
                    { label: 'Take Attendance', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/student-attendance'] }
                ]
            },
            {
                label: 'Reports',
                items: [
                    { label: 'Classwise Attendance', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/classwise-attendance'] },
                ]
            }
        ];}
        if (this.userRoles === USER_ROLES.ROLE_TEACHER) {
            this.model = [
                {
                    label: 'Home',
                    items: [
                    { label: 'Dashboard', icon: 'pi pi-fw pi-home', routerLink: ['/'] },
                    { label: 'Profile Settings', icon: 'pi pi-fw pi-cog', routerLink: ['/features/profile-setting'] }
                ]
            },
            {
                label: 'Student Management',
                items: [
                    { label: 'All Students', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/all-students'] },
                    { label: 'Take Attendance', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/student-attendance'] }
                ]
            },
            {
                label: 'Reports',
                items: [
                    { label: 'Classwise Attendance', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/classwise-attendance'] },
                    { label: 'Studentwise Attendance', icon: 'pi pi-fw pi-sitemap', routerLink: ['/features/student-attendance-reports'] }
                ]
            }
        ];}
        if (this.userRoles === USER_ROLES.ROLE_STUDENT) {
            this.model = [
                {
                    label: 'Home',
                    items: [
                    { label: 'Dashboard', icon: 'pi pi-fw pi-home', routerLink: ['/'] },
                    { label: 'Profile Settings', icon: 'pi pi-fw pi-cog', routerLink: ['/features/profile-setting'] }
                ]
            }
        ];}
    }
}
