import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { map, Observable } from 'rxjs';
import { Employee } from '@/pages/features/model/employee.model';
import { UserService } from '@/services/user.service';

@Injectable({ providedIn: 'root' })
export class EmployeesService {
  private http = inject(HttpClient);
  private userService = inject(UserService);
  private baseUrl = `${environment.baseURL.replace(/\/+$/,'')}/api/employees`;
  private dashboardSummary = `${environment.baseURL.replace(/\/+$/, '')}/api/dashboard/summary`;

  getEmployees(filters: { role_id?: number; status?: string; search?: string; is_active?: number } = {}): Observable<Employee[]> {
    let params = new HttpParams();
    for (const [k,v] of Object.entries(filters)) {
      if (v !== undefined && v !== null && v !== '') params = params.set(k, String(v));
    }
    return this.http.get<any>(this.baseUrl, { params }).pipe(map(res => res?.data || []));
  }

  createEmployee(emp: Partial<Employee>): Observable<Employee|null> {
    return this.http.post<any>(this.baseUrl, emp).pipe(map(res => res?.data || null));
  }

  updateEmployee(id: number, emp: Partial<Employee>): Observable<Employee|null> {
    return this.http.put<any>(`${this.baseUrl}/${id}`, emp).pipe(map(res => res?.data || null));
  }

  deleteEmployee(id: number): Observable<boolean> {
    return this.http.delete<any>(`${this.baseUrl}/${id}`).pipe(map(res => res?.success === true));
  }

  getEmployee(id: number): Observable<Employee|null> {
    return this.http.get<any>(`${this.baseUrl}/${id}`).pipe(map(res => res?.data || null));
  }

  // Fallback: use dashboard summary monthlyAttendance chart for employee profile when a specific API is not available
  getEmployeeMonthlyAttendance(): Observable<{ labels: string[]; datasets: any[] }> {
    return this.http.get<any>(this.dashboardSummary).pipe(map(res => res?.data?.charts?.monthlyAttendance || { labels: [], datasets: [] }));
  }
}
