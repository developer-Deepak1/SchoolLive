import { Routes } from '@angular/router';
import { AddEditClasses } from './add-edit-classes/add-edit-classes';
import { ProfileSetting } from './profile-setting/profile-setting';
import { AllEmployees } from './employees/all-employees/all-employees';
import { AddEmployees } from './employees/add-employees/add-employees';
import { EmployeeAttandance } from './employees/employee-attandance/employee-attandance';
import { AllStudents } from './student/all-students/all-students';
import { StudentAdmission } from './student/student-admission/student-admission';
import { StudentAttandance } from './student/student-attandance/student-attandance';
import { ClasswiseAttandance } from './reports/classwise-attandance/classwise-attandance';
import { StudentAttandanceReports } from './reports/student-attandance-reports/student-attandance-reports';
import { EmployeeAttandanceReports } from './reports/employee-attandance-reports/employee-attandance-reports';
import { AcademicYears } from './academic-years/academic-years';

export default [
    { path: 'profile-setting', component: ProfileSetting },
    { path: 'academic-years', component: AcademicYears },
    { path: 'add-edit-classes', component: AddEditClasses },
    { path: 'all-employees', component: AllEmployees },
    { path: 'add-employees', component: AddEmployees },
    { path: 'employee-attendance', component: EmployeeAttandance },
    { path: 'all-students', component: AllStudents },
    { path: 'student-admission', component: StudentAdmission },
    { path: 'student-attendance', component: StudentAttandance },
    { path: 'classwise-attendance', component: ClasswiseAttandance },
    { path: 'employee-attendance-reports', component: EmployeeAttandanceReports },
    { path: 'student-attendance-reports', component: StudentAttandanceReports },
    { path: '**', redirectTo: '/notfound' }
] as Routes;
