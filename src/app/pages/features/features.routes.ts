import { Routes } from '@angular/router';
import { AddEditClasses } from './add-edit-classes/add-edit-classes';
import { ProfileSetting } from './profile-setting/profile-setting';
import { AllEmployees } from './employees/all-employees/all-employees';
import { AddEmployees } from './employees/add-employees/add-employees';
import { EmployeeAttandance } from './employees/employee-attandance/employee-attandance';
import { AllStudents } from './student/all-students/all-students';
import { StudentProfile } from './student/student-profile/student-profile';
import { EmployeeProfile } from './employees/employee-profile/employee-profile';
import { StudentAdmission } from './student/student-admission/student-admission';
import { StudentAttandance } from './student/student-attandance/student-attandance';
import { ClasswiseAttandance } from './reports/classwise-attandance/classwise-attandance';
import { StudentAttandanceReports } from './reports/student-attandance-reports/student-attandance-reports';
import { EmployeeAttandanceReports } from './reports/employee-attandance-reports/employee-attandance-reports';
import { AcademicYears } from './academic-years/academic-years';
import { AuthGuard } from '@/guards/auth.guard';
import { AcademicCalander } from './academic-calander/academic-calander';
import { AddSchool } from './school/add-school/add-school';
import { AddUsers } from './user/add-users/add-users';
import { AllUsers } from './user/all-users/all-users';
import { AllSchool } from './school/all-school/all-school';
import { EmployeeAttendanceDetails } from './employees/employee-attendance-details/employee-attendance-details';

export default [
    { path: 'profile-setting', component: ProfileSetting, canActivate: [AuthGuard] },
    { path: 'academic-years', component: AcademicYears, canActivate: [AuthGuard] },
    { path: 'academic-calendar', component: AcademicCalander, canActivate: [AuthGuard] },
    { path: 'add-edit-classes', component: AddEditClasses, canActivate: [AuthGuard] },
    { path: 'all-employees', component: AllEmployees, canActivate: [AuthGuard] },
    { path: 'add-employees', component: AddEmployees, canActivate: [AuthGuard] },
    { path: 'employee-profile/:id', component: EmployeeProfile, canActivate: [AuthGuard] },
    { path: 'employee-attendance', component: EmployeeAttandance, canActivate: [AuthGuard] },
    { path: 'employee-attendance-details', component: EmployeeAttendanceDetails, canActivate: [AuthGuard] },
    { path: 'student-profile/:id', component: StudentProfile, canActivate: [AuthGuard] },
    { path: 'all-students', component: AllStudents, canActivate: [AuthGuard] },
    { path: 'student-admission', component: StudentAdmission, canActivate: [AuthGuard] },
    { path: 'student-attendance', component: StudentAttandance, canActivate: [AuthGuard] },
    { path: 'classwise-attendance', component: ClasswiseAttandance, canActivate: [AuthGuard] },
    { path: 'employee-attendance-reports', component: EmployeeAttandanceReports, canActivate: [AuthGuard] },
    { path: 'student-attendance-reports', component: StudentAttandanceReports, canActivate: [AuthGuard] },
    { path: 'add-school', component: AddSchool, canActivate: [AuthGuard] },
    { path: 'add-user', component: AddUsers, canActivate: [AuthGuard] },
    { path: 'all-users', component: AllUsers, canActivate: [AuthGuard] },
    { path: 'all-schools', component: AllSchool, canActivate: [AuthGuard] },
    { path: '**', redirectTo: '/notfound' }
] as Routes;
