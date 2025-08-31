import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, switchMap, timer, shareReplay } from 'rxjs';
import { environment } from '../../environments/environment';

export interface StudentDashboardResponse {
  success: boolean;
  message?: string;
  data: {
    stats: { averageAttendance: number; averageGrade: number; };
    charts: {
      monthlyAttendance?: { labels: string[]; datasets: any[] };
      gradeDistribution?: { labels: string[]; datasets: any[] };
      gradeProgress?: { labels: string[]; datasets: any[] };
    };
    recentActivities: any[];
    upcomingEvents: any[];
  };
}

@Injectable({ providedIn: 'root' })
export class StudentDashboardService {
  private cache$?: Observable<StudentDashboardResponse>;
  private invalidate$ = new BehaviorSubject<void>(undefined);
  private REFRESH_MS = 60_000;
  constructor(private http: HttpClient) {}
  private load(): Observable<StudentDashboardResponse> { return this.http.get<StudentDashboardResponse>(`${environment.baseURL}/api/dashboard/student`); }
  getSummary(): Observable<StudentDashboardResponse> {
    if (!this.cache$) {
      this.cache$ = this.invalidate$.pipe(
        switchMap(() => timer(0, this.REFRESH_MS)),
        switchMap(() => this.load()),
        shareReplay({ bufferSize: 1, refCount: true })
      );
    }
    return this.cache$;
  }
  refreshNow() { this.invalidate$.next(); }
}