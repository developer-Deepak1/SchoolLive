import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';

export interface FinePolicy {
  FinePolicyID?: number;
  SchoolID?: number;
  AcademicYearID?: number;
  FeeID: number | null; // null means all fees
  ApplyType: 'Fixed' | 'PerDay' | 'Percentage';
  Amount: number;
  GraceDays: number;
  MaxAmount: number | null;
  IsActive: boolean;
  CreatedAt?: string;
  CreatedBy?: string;
  UpdatedAt?: string | null;
  UpdatedBy?: string | null;
}

@Injectable({ providedIn: 'root' })
export class FinePolicyService {
  private http = inject(HttpClient);
  private base = `${environment.baseURL.replace(/\/+$/, '')}/api/fines`;

  list(): Observable<FinePolicy[]> {
    return this.http.get<any>(`${this.base}`).pipe(map(r => (r && r.success && Array.isArray(r.data) ? r.data : [])));
  }

  create(p: FinePolicy): Observable<FinePolicy> {
    return this.http.post<any>(`${this.base}`, p).pipe(map(r => (r && r.success ? (r.data as FinePolicy) : p)));
  }

  update(p: FinePolicy): Observable<FinePolicy> {
    if (!p.FinePolicyID) throw new Error('FinePolicyID required');
    return this.http.put<any>(`${this.base}/${p.FinePolicyID}`, p).pipe(map(r => (r && r.success ? (r.data as FinePolicy) : p)));
  }

  delete(id: number): Observable<boolean> {
    return this.http.delete<any>(`${this.base}/${id}`).pipe(map(r => !!(r && r.success)));
  }

  toggleStatus(id: number, active: boolean): Observable<boolean> {
    return this.http.patch<any>(`${this.base}/${id}/status`, { IsActive: active }).pipe(map(r => !!(r && r.success)));
  }
}
