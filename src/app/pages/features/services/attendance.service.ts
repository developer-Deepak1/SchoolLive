import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface AttendanceRecord {
  StudentID: number;
  Status: string;
  Remarks?: string | null;
}

@Injectable({ providedIn: 'root' })
export class AttendanceService {
  private base = '/api/attendance';
  constructor(private http: HttpClient) {}

  // getDaily returns an observable of { records: AttendanceRecord[] }
  getDaily(date: string, sectionId?: number): Observable<any> {
    const params: any = { date };
    if (sectionId) params.section = sectionId;
    return this.http.get<any>(this.base, { params });
  }

  // save upserts multiple attendance entries for a date
  save(date: string, entries: { StudentID: number; Status: string }[]): Observable<any> {
    return this.http.post<any>(this.base, { date, entries });
  }
}
