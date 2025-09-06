import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { Observable, map, of } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class AcademicCalendarService {
  private http = inject(HttpClient);
  private baseUrl = `${environment.baseURL.replace(/\/+$/,'')}/api/academic`;

  getWeeklyOffs(academicYearId?: any): Observable<any[]> {
    const url = `${this.baseUrl}/getWeeklyOffs` + (academicYearId ? `?academic_year_id=${encodeURIComponent(academicYearId)}` : '');
    return this.http.get<any>(url).pipe(map(res => res && res.success && res.data ? res.data : []));
  }

  setWeeklyOffs(academicYearId: any, days: number[]): Observable<boolean> {
    const payload = { AcademicYearID: academicYearId, Days: days };
    return this.http.post<any>(`${this.baseUrl}/setWeeklyOffs`, payload).pipe(map(res => res && res.success === true));
  }

  getHolidays(academicYearId?: any): Observable<any[]> {
    const url = `${this.baseUrl}/getHolidays` + (academicYearId ? `?academic_year_id=${encodeURIComponent(academicYearId)}` : '');
    return this.http.get<any>(url).pipe(map(res => res && res.success && res.data ? res.data : []));
  }

  createHoliday(payload: any): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/createHoliday`, payload);
  }

  // Create a range of holidays (one row per date between StartDate and EndDate)
  createHolidayRange(payload: any): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/createHolidayRange`, payload);
  }

  deleteHoliday(id: any): Observable<any> {
    return this.http.delete<any>(`${this.baseUrl}/deleteHoliday/${id}`);
  }

  updateHoliday(id: any, payload: any): Observable<any> {
    return this.http.put<any>(`${this.baseUrl}/updateHoliday/${id}`, payload);
  }

  getWeeklyReport(start: string, end: string, academicYearId?: any): Observable<any[]> {
    // Deprecated: frontend no longer uses weekly report endpoint.
    // Keep a safe shim to avoid breaking any stray callers.
    console.warn('AcademicCalendarService.getWeeklyReport is deprecated and returns an empty array.');
    return of([]);
  }

  getMonthlyWorkingDays(academicYearId?: any): Observable<any> {
    const url = `${this.baseUrl}/monthlyWorkingDays` + (academicYearId ? `?academic_year_id=${encodeURIComponent(academicYearId)}` : '');
    return this.http.get<any>(url).pipe(map(res => res && res.success && res.data ? res.data : res));
  }
}
