import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
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
    // Auto-refresh removed: callers should request updates manually via getSummary() or UI-triggered actions.

    constructor(private http: HttpClient) {}

    private load(): Observable<DashboardSummaryResponse> {
        return this.http.get<DashboardSummaryResponse>(`${environment.baseURL}/api/dashboard/summary`);
    }

    /**
     * Returns cached summary with auto-refresh every REFRESH_INTERVAL_MS.
     */
    /**
     * Returns a fresh summary observable. Auto-refresh disabled.
     * Callers may subscribe each time they need a fresh payload.
     */
    getSummary(): Observable<DashboardSummaryResponse> {
        return this.load();
    }

    /** Manually force reload immediately (e.g., user pressed Refresh). */
    /**
     * Kept for compatibility with UI code. With auto-refresh removed this is a no-op.
     */
    refreshNow() {
        // no-op: components should call getSummary() / loadFromApi() to explicitly refresh
    }
}
