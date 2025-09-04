import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

export interface StudentDashboardResponse {
    success: boolean;
    message?: string;
    data: {
        stats: { averageAttendance: number; averageGrade: number };
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
    // Auto-refresh removed. Components should explicitly call getSummary() when they need fresh data.
    constructor(private http: HttpClient) {}
    private load(): Observable<StudentDashboardResponse> {
        return this.http.get<StudentDashboardResponse>(`${environment.baseURL}/api/dashboard/student`);
    }
    getSummary(): Observable<StudentDashboardResponse> {
        return this.load();
    }

    /**
     * Kept for compatibility. No automatic invalidation is performed.
     */
    refreshNow() {
        /* no-op */
    }
}
