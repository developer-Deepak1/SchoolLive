import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class EmployeeAttendanceService {
  private base = `${environment.baseURL.replace(/\/+$|$/,'')}/api/employee/attendance`;
  constructor(private http: HttpClient) {}

  signIn(date: string, employeeId?: number): Observable<any> {
    const url = `${environment.baseURL.replace(/\/+$/,'')}/api/employee/attendance/signin`;
    const body: any = { date };
    if (employeeId !== undefined && employeeId !== null) body.employee_id = employeeId;
    return this.http.post<any>(url, body);
  }

  signOut(date: string, employeeId?: number): Observable<any> {
    const url = `${environment.baseURL.replace(/\/+$/,'')}/api/employee/attendance/signout`;
    const body: any = { date };
    if (employeeId !== undefined && employeeId !== null) body.employee_id = employeeId;
    return this.http.post<any>(url, body);
  }

  getToday(date?: string, employeeId?: number): Observable<any> {
    const url = `${environment.baseURL.replace(/\/+$/,'')}/api/employee/attendance/today`;
    const paramsObj: any = {};
    if (date) paramsObj.date = date;
    if (employeeId !== undefined && employeeId !== null) paramsObj.employee_id = String(employeeId);
    const params = new HttpParams({ fromObject: paramsObj });
    return this.http.get<any>(url, { params });
  }
}
