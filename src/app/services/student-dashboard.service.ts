import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
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
    today?: { status: string; remarks?: string | null };
    recentActivities: any[];
    upcomingEvents: any[];
  };
}

@Injectable({ providedIn: 'root' })
export class StudentDashboardService {
  // Auto-refresh removed. Components should explicitly call getSummary() when they need fresh data.
  constructor(private http: HttpClient) {}
  private load(params?: {[k:string]: any}): Observable<StudentDashboardResponse> {
    let url = `${environment.baseURL}/api/dashboard/student`;
    if (params && Object.keys(params).length) {
      const qp = new URLSearchParams();
      for (const k of Object.keys(params)) { if (params[k] !== undefined && params[k] !== null) qp.set(k, String(params[k])); }
      url = url + '?' + qp.toString();
    }
    return this.http.get<StudentDashboardResponse>(url);
  }
  getSummary(studentId?: number): Observable<StudentDashboardResponse> {
    return this.load(studentId ? { student_id: studentId } : undefined);
  }

  /**
   * Kept for compatibility. No automatic invalidation is performed.
   */
  refreshNow() { /* no-op */ }
}