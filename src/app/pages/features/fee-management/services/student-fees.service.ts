import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import {
  ApiResponse,
  EnsureMonthlyRequestItem,
  EnsureMonthlyResponse,
  EnsureMonthlyResponseItem,
  LedgerMapper,
  PaymentBatchResponse,
  PaymentMapper,
  PaymentRequest,
  StudentFeeLedgerEntry
} from './fee-management.models';
import { StudentFeePlan, StudentFeePlanMapper } from './fee-management.models';

export type {
  StudentFeeLedgerEntry,
  PaymentRequest,
  PaymentBatchResponse,
  EnsureMonthlyRequestItem,
  EnsureMonthlyResponse,
  EnsureMonthlyResponseItem,
  StudentFeePlan
} from './fee-management.models';

@Injectable({ providedIn: 'root' })
export class StudentFeesService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.baseURL.replace(/\/+$/, '')}/api/fee-management`;

  getLedger(
    studentId: number,
    opts: { onlyDue?: boolean; includePaid?: boolean } = {}
  ): Observable<StudentFeeLedgerEntry[]> {
    const searchParams: string[] = [];
    if (opts.onlyDue !== undefined) searchParams.push(`only_due=${opts.onlyDue ? 1 : 0}`);
    if (opts.includePaid !== undefined) searchParams.push(`include_paid=${opts.includePaid ? 1 : 0}`);
    const query = searchParams.length ? `?${searchParams.join('&')}` : '';

    return this.http
      .get<ApiResponse<unknown[]>>(`${this.baseUrl}/students/${studentId}/ledger${query}`)
      .pipe(map(response => this.extractList(response).map(r => LedgerMapper.fromApi(r))));
  }

  getDues(studentId: number): Observable<StudentFeeLedgerEntry[]> {
    return this.http
      .get<ApiResponse<unknown[]>>(`${this.baseUrl}/students/${studentId}/dues`)
      .pipe(map(response => this.extractList(response).map(r => LedgerMapper.fromApi(r))));
  }

  addPayment(payload: PaymentRequest): Observable<boolean> {
    return this.http
      .post<ApiResponse<unknown>>(`${this.baseUrl}/payments`, PaymentMapper.toRequest(payload))
      .pipe(map(response => this.expectPaymentBatch(response).items.some(item => item.ok)));
  }

  addPaymentsBatch(payloads: PaymentRequest[]): Observable<PaymentBatchResponse> {
    return this.http
      .post<ApiResponse<unknown>>(
        `${this.baseUrl}/payments`,
        payloads.map(item => PaymentMapper.toRequest(item))
      )
      .pipe(map(response => this.expectPaymentBatch(response)));
  }

  getMonthly(studentId: number, monthDate: Date): Observable<StudentFeeLedgerEntry[]> {
    const year = monthDate.getFullYear();
    const month = monthDate.getMonth() + 1;
    return this.http
      .get<ApiResponse<unknown[]>>(`${this.baseUrl}/students/${studentId}/monthly?year=${year}&month=${month}`)
      .pipe(map(response => this.extractList(response).map(r => LedgerMapper.fromApi(r))));
  }

  getPlan(studentId: number, referenceDate: Date = new Date()): Observable<StudentFeePlan> {
    const year = referenceDate.getFullYear();
    const month = referenceDate.getMonth() + 1;
    const query = `year=${year}&month=${month}`;
    return this.http
      .get<ApiResponse<unknown>>(`${this.baseUrl}/students/${studentId}/plan?${query}`)
      .pipe(map(response => this.expectPlan(response)));
  }

  ensureMonthly(studentId: number, item: EnsureMonthlyRequestItem): Observable<EnsureMonthlyResponseItem | null> {
    return this.http
      .post<ApiResponse<unknown>>(
        `${this.baseUrl}/students/${studentId}/monthly/ensure`,
        this.normaliseEnsureItem(item)
      )
      .pipe(map(response => this.expectEnsureSingle(response)));
  }

  ensureMonthlyBatch(studentId: number, items: EnsureMonthlyRequestItem[]): Observable<EnsureMonthlyResponse> {
    return this.http
      .post<ApiResponse<unknown>>(
        `${this.baseUrl}/students/${studentId}/monthly/ensure`,
        items.map(entry => this.normaliseEnsureItem(entry))
      )
      .pipe(map(response => this.expectEnsureBatch(response)));
  }

  private extractList<T>(response: ApiResponse<T[]>): T[] {
    if (response.success) {
      return Array.isArray(response.data) ? response.data : [];
    }
    return [];
  }

  private expectPaymentBatch(response: ApiResponse<unknown>): PaymentBatchResponse {
    if (response.success) {
      return PaymentMapper.fromBatchResponse(response.data);
    }
    throw new Error(response.message ?? 'Failed to record payments');
  }

  private expectEnsureBatch(response: ApiResponse<unknown>): EnsureMonthlyResponse {
    if (response.success) {
      const rawItems = (response.data && typeof response.data === 'object') ? (response.data as Record<string, unknown>)['items'] : null;
      const items = Array.isArray(rawItems) ? rawItems.map(this.mapEnsureItem) : [];
      return { items } satisfies EnsureMonthlyResponse;
    }
    throw new Error(response.message ?? 'Failed to ensure monthly rows');
  }

  private expectEnsureSingle(response: ApiResponse<unknown>): EnsureMonthlyResponseItem | null {
    if (!response.success) {
      throw new Error(response.message ?? 'Failed to ensure monthly row');
    }
    const batch = this.expectEnsureBatch({ success: true, message: response.message, data: response.data as any });
    return batch.items[0] ?? null;
  }

  private expectPlan(response: ApiResponse<unknown>): StudentFeePlan {
    if (!response.success) {
      throw new Error(response.message ?? 'Failed to fetch student fee plan');
    }
    return StudentFeePlanMapper.fromApi(response.data);
  }

  private normaliseEnsureItem(item: EnsureMonthlyRequestItem): Record<string, number> {
    return {
      feeId: item.feeId,
      year: item.year,
      month: item.month
    };
  }

  private mapEnsureItem = (raw: unknown): EnsureMonthlyResponseItem => {
    if (!raw || typeof raw !== 'object') {
      return { feeId: 0, year: 0, month: 0, studentFeeId: 0 } satisfies EnsureMonthlyResponseItem;
    }
    const record = raw as Record<string, unknown>;
    return {
      feeId: Number(record['feeId'] ?? record['FeeID'] ?? 0),
      year: Number(record['year'] ?? record['Year'] ?? 0),
      month: Number(record['month'] ?? record['Month'] ?? 0),
      studentFeeId: Number(record['studentFeeId'] ?? record['StudentFeeID'] ?? 0)
    } satisfies EnsureMonthlyResponseItem;
  };
}
