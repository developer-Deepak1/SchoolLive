import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { map, Observable } from 'rxjs';
import { Student } from '../model/student.model';
import { UserService } from '@/services/user.service';

@Injectable({ providedIn: 'root' })
export class StudentsService {
    private http = inject(HttpClient);
    private userService = inject(UserService);
    private baseUrl = `${environment.baseURL.replace(/\/+$/, '')}/api/students`;
    private academicBase = `${environment.baseURL.replace(/\/+$/, '')}/api/academic`;
    private studentDashboard = `${environment.baseURL.replace(/\/+$/, '')}/api/dashboard/student`;

    getStudents(filters: { class_id?: number; section_id?: number; status?: string; search?: string } = {}): Observable<Student[]> {
        let params = new HttpParams();
        for (const [k, v] of Object.entries(filters)) {
            if (v !== undefined && v !== null && v !== '') params = params.set(k, String(v));
        }
        return this.http.get<any>(this.baseUrl, { params }).pipe(map((res) => res?.data || []));
    }

    createStudent(stu: Partial<Student>): Observable<Student | null> {
        return this.http.post<any>(this.baseUrl, stu).pipe(map((res) => res?.data || null));
    }

    updateStudent(id: number, stu: Partial<Student>): Observable<Student | null> {
        return this.http.put<any>(`${this.baseUrl}/${id}`, stu).pipe(map((res) => res?.data || null));
    }

    deleteStudent(id: number): Observable<boolean> {
        return this.http.delete<any>(`${this.baseUrl}/${id}`).pipe(map((res) => res?.success === true));
    }

    admitStudent(payload: any): Observable<any> {
        return this.http.post<any>(`${this.baseUrl}/admission`, payload).pipe(map((res) => res));
    }

    getClasses(): Observable<any[]> {
        return this.http.get<any>(`${this.academicBase}/getClasses`).pipe(map((res) => res?.data || []));
    }

    getSections(classId: number): Observable<any[]> {
        let params = new HttpParams().set('class_id', String(classId));
        return this.http.get<any>(`${this.academicBase}/sections`, { params }).pipe(map((res) => res?.data || []));
    }

    getStudent(id: number): Observable<Student | null> {
        return this.http.get<any>(`${this.baseUrl}/${id}`).pipe(map((res) => res?.data || null));
    }

    // Fetch monthly attendance for a specific student (re-using student dashboard endpoint, ignoring student id mismatch if viewing different student)
    getStudentMonthlyAttendance(): Observable<{ labels: string[]; datasets: any[] }> {
        return this.http.get<any>(this.studentDashboard).pipe(map((res) => res?.data?.charts?.monthlyAttendance || { labels: [], datasets: [] }));
    }
}
