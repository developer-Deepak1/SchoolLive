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

  // Attendance Requests API
  createRequest(date: string, requestType: 'Leave' | 'Attendance', reason?: string, employeeId?: number): Observable<any> {
    const url = `${environment.baseURL.replace(/\/+$/,'')}/api/employee/attendance/requests/create`;
    const body: any = { date, request_type: requestType };
    if (reason) body.reason = reason;
    if (employeeId !== undefined && employeeId !== null) body.employee_id = employeeId;
    return this.http.post<any>(url, body);
  }

  listRequests(employeeId?: number, status?: string): Observable<any> {
    const url = `${environment.baseURL.replace(/\/+$/,'')}/api/employee/attendance/requests`;
    const paramsObj: any = {};
    if (employeeId !== undefined && employeeId !== null) paramsObj.employee_id = String(employeeId);
    if (status) paramsObj.status = status;
    const params = new HttpParams({ fromObject: paramsObj });
    return this.http.get<any>(url, { params });
  }
}
