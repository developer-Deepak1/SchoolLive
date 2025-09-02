import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { Observable, map } from 'rxjs';

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
    return this.http.post<any>(`${this.baseUrl}/createHoliday`, payload).pipe(map(res => res));
  }

  // Create a range of holidays (one row per date between StartDate and EndDate)
  createHolidayRange(payload: any): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/createHolidayRange`, payload).pipe(map(res => res));
  }

  deleteHoliday(id: any): Observable<any> {
    return this.http.delete<any>(`${this.baseUrl}/deleteHoliday/${id}`).pipe(map(res => res));
  }

  updateHoliday(id: any, payload: any): Observable<any> {
    return this.http.put<any>(`${this.baseUrl}/updateHoliday/${id}`, payload).pipe(map(res => res));
  }

  getWeeklyReport(start: string, end: string, academicYearId?: any): Observable<any[]> {
    const params = new URLSearchParams({ start, end });
    if (academicYearId) params.set('academic_year_id', academicYearId);
    const url = `${this.baseUrl}/getWeeklyReport?${params.toString()}`;
    return this.http.get<any>(url).pipe(map(res => res && res.success && res.data ? res.data : []));
  }
}
