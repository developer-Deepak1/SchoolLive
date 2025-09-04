import { inject, Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { map, Observable } from 'rxjs';
import { UserService } from '@/services/user.service';

@Injectable({ providedIn: 'root' })
export class SectionsService {
    private http = inject(HttpClient);
    private userService = inject(UserService);
    private academicBase = `${environment.baseURL.replace(/\/+$/, '')}/api/academic`;

    /**
     * Fetch sections. Optional filters: class_id, academic_year_id, school_id
     */
    getSections(filters: { class_id?: number; academic_year_id?: number; school_id?: number } = {}): Observable<any[]> {
        let params = new HttpParams();
        for (const [k, v] of Object.entries(filters)) {
            if (v !== undefined && v !== null) params = params.set(k, String(v));
        }
        return this.http.get<any>(`${this.academicBase}/sections`, { params }).pipe(map((res) => res?.data || []));
    }

    getSection(id: number): Observable<any | null> {
        return this.http.get<any>(`${this.academicBase}/sections/${id}`).pipe(map((res) => res?.data || null));
    }

    createSection(payload: any): Observable<any | null> {
        // Accept frontend camelCase payload; backend controller normalizes to snake_case
        return this.http.post<any>(`${this.academicBase}/sections`, payload).pipe(map((res) => res?.data || null));
    }

    updateSection(id: number, payload: any): Observable<any | null> {
        return this.http.put<any>(`${this.academicBase}/sections/${id}`, payload).pipe(map((res) => res?.data || null));
    }

    deleteSection(id: number): Observable<boolean> {
        return this.http.delete<any>(`${this.academicBase}/sections/${id}`).pipe(map((res) => res?.success === true));
    }
}
