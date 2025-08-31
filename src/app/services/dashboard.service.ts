import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, BehaviorSubject, timer, switchMap, shareReplay } from 'rxjs';
import { environment } from '../../environments/environment';

export interface DashboardSummaryResponse {
  success: boolean;
  message?: string;
  data: {
    stats: any;
    charts: {
      attendanceOverview: { labels: string[]; datasets: any[] };
      enrollmentTrend: any;
      gradeDistribution: any;
      revenue: any;
      classAttendance: { labels: string[]; datasets: any[] };
      classGender?: { labels: string[]; datasets: any[] };
      monthlyAttendance?: { labels: string[]; datasets: any[] };
    };
    recentActivities: any[];
    topClasses: any[];
    upcomingEvents: any[];
    teacherPerformance: any[];
  };
}

@Injectable({ providedIn: 'root' })
export class DashboardService {
  private cache$?: Observable<DashboardSummaryResponse>;
  private invalidate$ = new BehaviorSubject<void>(undefined);
  private REFRESH_INTERVAL_MS = 60_000; // 1 minute

  constructor(private http: HttpClient) {}

  private load(): Observable<DashboardSummaryResponse> {
    return this.http.get<DashboardSummaryResponse>(`${environment.baseURL}/api/dashboard/summary`);
  }

  /**
   * Returns cached summary with auto-refresh every REFRESH_INTERVAL_MS.
   */
  getSummary(): Observable<DashboardSummaryResponse> {
    if (!this.cache$) {
      this.cache$ = this.invalidate$.pipe(
        switchMap(() => timer(0, this.REFRESH_INTERVAL_MS)),
        switchMap(() => this.load()),
        shareReplay({ bufferSize: 1, refCount: true })
      );
    }
    return this.cache$;
  }

  /** Manually force reload immediately (e.g., user pressed Refresh). */
  refreshNow() {
    this.invalidate$.next();
  }
}
