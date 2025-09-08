import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

export interface AttendanceRecord {
  StudentID: number;
  Status: string;
  Remarks?: string | null;
}

@Injectable({ providedIn: 'root' })
export class AttendanceService {
  // Use environment.baseURL and ensure no duplicate slashes
  private base = `${environment.baseURL.replace(/\/+$/,'')}/api/attendance`;
  constructor(private http: HttpClient) {}

  // getDaily returns an observable of { records: AttendanceRecord[] }
  getDaily(date: string, sectionId?: number): Observable<any> {
    const params: any = { date };
    if (sectionId) params.section = sectionId;
    return this.http.get<any>(this.base, { params });
  }

  // save upserts multiple attendance entries for a date. Optional classId/sectionId may be provided.
  save(date: string, entries: { StudentID: number; Status: string }[], classId?: number | null, sectionId?: number | null): Observable<any> {
    const body: any = { date, entries };
    if (classId != null) body.class_id = classId;
    if (sectionId != null) body.section_id = sectionId;
    return this.http.post<any>(this.base, body);
  }
}
