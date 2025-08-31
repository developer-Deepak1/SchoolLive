import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { Observable, map } from 'rxjs';
import { AcademicYear, AcademicYearResponse } from '../model/academic-year.model';

@Injectable({ providedIn: 'root' })
export class AcademicYearService {
  private http = inject(HttpClient);
  private baseUrl = `${environment.baseURL.replace(/\/+$/,'')}/api/academic`;

  getAcademicYears(): Observable<AcademicYear[]> {
    return this.http.get<AcademicYearResponse>(`${this.baseUrl}/getAcademicYears`).pipe(
      map(res => res && res.success && res.data ? res.data : [])
    );
  }

  createAcademicYear(year: AcademicYear): Observable<boolean> {
    const payload = this.toApiPayload(year);
    return this.http.post<AcademicYearResponse>(`${this.baseUrl}/CreateAcademicYears`, payload).pipe(
      map(res => res && res.success === true)
    );
  }

  updateAcademicYear(year: AcademicYear): Observable<boolean> {
    if (!year.AcademicYearID) return new Observable(sub => { sub.next(false); sub.complete(); });
    const payload = this.toApiPayload(year);
    return this.http.put<AcademicYearResponse>(`${this.baseUrl}/academicYears/${year.AcademicYearID}`, payload).pipe(
      map(res => res && res.success === true)
    );
  }

  deleteAcademicYear(id: number): Observable<AcademicYearResponse> {
    return this.http.delete<AcademicYearResponse>(`${this.baseUrl}/DeleteAcademicYears/${id}`);
  }

  // Helper method to get raw response (useful for error handling)
  getAcademicYearsResponse(): Observable<AcademicYearResponse> {
    return this.http.get<AcademicYearResponse>(`${this.baseUrl}/getAcademicYears`);
  }

  // Helper method to get raw response for create (useful for error handling)
  createAcademicYearResponse(year: AcademicYear): Observable<AcademicYearResponse> {
    const payload = this.toApiPayload(year);
    return this.http.post<AcademicYearResponse>(`${this.baseUrl}/CreateAcademicYears`, payload);
  }

  // Helper method to get raw response for update (useful for error handling)
  updateAcademicYearResponse(year: AcademicYear): Observable<AcademicYearResponse> {
    const payload = this.toApiPayload(year);
    return this.http.put<AcademicYearResponse>(`${this.baseUrl}/UpdateAcademicYears/${year.AcademicYearID}`, payload);
  }

  // Helper method to get raw response for delete (useful for error handling)
  deleteAcademicYearResponse(id: number): Observable<AcademicYearResponse> {
    return this.http.delete<AcademicYearResponse>(`${this.baseUrl}/DeleteAcademicYears/${id}`);
  }

  private toApiPayload(y: AcademicYear) {
    return {
      AcademicYearID: y.AcademicYearID || undefined,
      AcademicYearName: y.AcademicYearName,
      StartDate: y.StartDate,
      EndDate: y.EndDate,
      Status: y.Status || 'inactive'
    };
  }
}
