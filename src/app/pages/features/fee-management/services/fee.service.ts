import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import {
  ApiResponse,
  Fee,
  FeeMapper,
  FeeRequestPayload,
  FeeStatusRequest
} from './fee-management.models';

@Injectable({ providedIn: 'root' })
export class FeeService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.baseURL.replace(/\/+$/, '')}/api/fee-management/fees`;

  getFees(): Observable<Fee[]> {
    return this.http
      .get<ApiResponse<unknown[]>>(this.baseUrl)
      .pipe(map(response => this.extractList(response).map(r => FeeMapper.fromApi(r))));
  }

  getFee(feeId: number): Observable<Fee | null> {
    return this.http
      .get<ApiResponse<unknown>>(this.composeUrl(`${feeId}`))
      .pipe(map(response => (response.success ? FeeMapper.fromApi(response.data) : null)));
  }

  createFee(payload: FeeRequestPayload): Observable<Fee> {
    return this.http
      .post<ApiResponse<unknown>>(this.baseUrl, FeeMapper.toRequest(payload))
      .pipe(map(response => this.expectItem(response)));
  }

  updateFee(feeId: number, payload: FeeRequestPayload): Observable<Fee> {
    return this.http
      .put<ApiResponse<unknown>>(this.composeUrl(`${feeId}`), FeeMapper.toRequest(payload))
      .pipe(map(response => this.expectItem(response)));
  }

  toggleFeeStatus(feeId: number, isActive: boolean): Observable<Fee> {
    const body: FeeStatusRequest = { isActive };
    return this.http
      .patch<ApiResponse<unknown>>(this.composeUrl(`${feeId}/status`), body)
      .pipe(map(response => this.expectItem(response)));
  }

  private composeUrl(segment: string): string {
    return `${this.baseUrl}/${segment.replace(/^\/+|\/+$/g, '')}`;
  }

  private extractList<T>(response: ApiResponse<T[]>): T[] {
    if (response.success) {
      return Array.isArray(response.data) ? response.data : [];
    }
    return [];
  }

  private expectItem(response: ApiResponse<unknown>): Fee {
    if (response.success) {
      return FeeMapper.fromApi(response.data);
    }
    throw new Error(response.message ?? 'Fee management API responded with an error');
  }
}