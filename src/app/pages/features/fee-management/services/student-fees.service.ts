import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';

export interface StudentFeeLedgerRow {
  StudentFeeID: number;
  StudentID: number;
  FeeID: number;
  FeeName: string;
  MappingID?: number | null;
  Amount: number;
  FineAmount: number;
  DiscountAmount: number;
  AmountPaid: number;
  DueDate?: string | null;
  Status: 'Pending'|'Partial'|'Paid'|'Overdue';
  InvoiceRef?: string | null;
  Remarks?: string | null;
  ComputedFine?: number;
  Outstanding?: number;
}

export interface PaymentPayload {
  StudentFeeID: number;
  PaidAmount: number;
  Mode: 'Cash'|'Online'|'Cheque'|'UPI';
  TransactionRef?: string;
  PaymentDate?: string; // YYYY-MM-DD
  DiscountDelta?: number; // optional additional discount given now
}

@Injectable({ providedIn: 'root' })
export class StudentFeesService {
  private http = inject(HttpClient);
  private base = `${environment.baseURL.replace(/\/+$/, '')}`;

  getLedger(studentId: number, opts: {only_due?: boolean, include_paid?: boolean} = {}): Observable<StudentFeeLedgerRow[]> {
    const qp: string[] = [];
    if (opts.only_due !== undefined) qp.push(`only_due=${opts.only_due ? 1 : 0}`);
    if (opts.include_paid !== undefined) qp.push(`include_paid=${opts.include_paid ? 1 : 0}`);
    const qs = qp.length ? `?${qp.join('&')}` : '';
    return this.http.get<any>(`${this.base}/api/fees/student/${studentId}/ledger${qs}`).pipe(map(r => (r && r.success ? (r.data as StudentFeeLedgerRow[]) : [])));
  }

  getDues(studentId: number): Observable<StudentFeeLedgerRow[]> {
    return this.http.get<any>(`${this.base}/api/fees/student/${studentId}/dues`).pipe(map(r => (r && r.success ? (r.data as StudentFeeLedgerRow[]) : [])));
  }

  addPayment(p: PaymentPayload): Observable<boolean> {
    return this.http.post<any>(`${this.base}/api/fees/payments`, p).pipe(map(r => !!(r && r.success)));
  }

  // Month-based plan without precomputed rows
  getMonthly(studentId: number, monthDate: Date): Observable<StudentFeeLedgerRow[]> {
    const y = monthDate.getFullYear();
    const m = monthDate.getMonth() + 1;
    return this.http.get<any>(`${this.base}/api/fees/student/${studentId}/monthly?year=${y}&month=${m}`).pipe(map(r => (r && r.success ? (r.data as StudentFeeLedgerRow[]) : [])));
  }

  ensureMonthly(studentId: number, feeId: number, year: number, month: number): Observable<{ StudentFeeID: number }> {
    return this.http.post<any>(`${this.base}/api/fees/student/${studentId}/monthly/ensure`, { FeeID: feeId, Year: year, Month: month }).pipe(map(r => (r && r.success ? (r.data as { StudentFeeID: number }) : { StudentFeeID: 0 })));
  }
}
