import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Classes, StreamType } from '../model/classes.model';
import { environment } from '../../../../environments/environment';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { UserService } from '@/services/user.service';

@Injectable({
    providedIn: 'root'
})
export class ClassesService {
    private http = inject(HttpClient);
    private userService = inject(UserService);
    private baseUrl = `${environment.baseURL.replace(/\/+$/, '')}/api/academic`;

    // NOTE: backend endpoints expect snake_case fields. We map front-end model to API shape.
    getClasses(): Observable<Classes[]> {
        // Prefer academic year from logged-in user; default to 1 when not available
        const AcademicYearID = this.userService.getAcademicYearId() || 0;
        const url = `${this.baseUrl}/getClasses?AcademicYearID=${encodeURIComponent(String(AcademicYearID))}`;
        return this.http.get<any>(url).pipe(map((res) => (res && res.data ? res.data : [])));
    }

    createClass(class_: Classes): Observable<Classes | null> {
        const payload: any = {
            ClassName: class_.ClassName,
            MaxStrength: class_.MaxStrength || null,
            ClassCode: class_.ClassCode || null,
            Stream: class_.Stream || StreamType.NONE
        };

        return this.http.post<any>(`${this.baseUrl}/CreateClasses`, payload).pipe(map((res) => (res && res.data && res.data ? res.data : null)));
    }

    updateClass(class_: Classes): Observable<Classes | null> {
        if (!class_.ClassID)
            return new Observable((subscriber) => {
                subscriber.next(null);
                subscriber.complete();
            });

        const payload: any = {
            ClassID: class_.ClassID,
            ClassName: class_.ClassName,
            MaxStrength: class_.MaxStrength || null,
            ClassCode: class_.ClassCode || null,
            Stream: class_.Stream || StreamType.NONE
        };

        return this.http.put<any>(`${this.baseUrl}/classes/${class_.ClassID}`, payload).pipe(map((res) => (res && res.data && res.data ? res.data : null)));
    }

    deleteClass(classId: number): Observable<boolean> {
        return this.http.delete<any>(`${this.baseUrl}/classes/${classId}`).pipe(map((res) => res && res.success === true));
    }
}
