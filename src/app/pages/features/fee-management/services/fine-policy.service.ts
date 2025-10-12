import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import {
  ApiResponse,
  FinePolicy,
  FinePolicyMapper,
  FinePolicyRequest
} from './fee-management.models';

@Injectable({ providedIn: 'root' })
export class FinePolicyService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.baseURL.replace(/\/+$/, '')}/api/fee-management/fines`;

  list(): Observable<FinePolicy[]> {
    return this.http
      .get<ApiResponse<unknown[]>>(this.baseUrl)
      .pipe(map(response => this.extractList(response).map(r => FinePolicyMapper.fromApi(r))));
  }

  create(payload: FinePolicyRequest): Observable<FinePolicy> {
    return this.http
      .post<ApiResponse<unknown>>(this.baseUrl, FinePolicyMapper.toRequest(payload))
      .pipe(map(response => this.expectPolicy(response)));
  }

  update(policyId: number, payload: FinePolicyRequest): Observable<FinePolicy> {
    return this.http
      .put<ApiResponse<unknown>>(this.composeUrl(`${policyId}`), FinePolicyMapper.toRequest(payload))
      .pipe(map(response => this.expectPolicy(response)));
  }

  delete(policyId: number): Observable<boolean> {
    return this.http
      .delete<ApiResponse<unknown>>(this.composeUrl(`${policyId}`))
      .pipe(map(response => response.success));
  }

  toggleStatus(policy: FinePolicy, isActive: boolean): Observable<FinePolicy> {
    const request: FinePolicyRequest = {
      feeId: policy.feeId,
      applyType: policy.applyType,
      amount: policy.amount,
      graceDays: policy.graceDays,
      maxAmount: policy.maxAmount,
      isActive
    };
    return this.update(policy.finePolicyId, request);
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

  private expectPolicy(response: ApiResponse<unknown>): FinePolicy {
    if (response.success) {
      return FinePolicyMapper.fromApi(response.data);
    }
    throw new Error(response.message ?? 'Fine policy operation failed');
  }
}
