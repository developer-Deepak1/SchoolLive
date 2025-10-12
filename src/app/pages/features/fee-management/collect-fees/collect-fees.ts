import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TableModule } from 'primeng/table';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ToastModule } from 'primeng/toast';
import { AutoCompleteModule } from 'primeng/autocomplete';
import { TagModule } from 'primeng/tag';
import { MessageService } from 'primeng/api';
import { StudentsService } from '../../services/students.service';
import { Student } from '../../model/student.model';
import { StudentFeesService, StudentFeeLedgerEntry, PaymentRequest } from '../services/student-fees.service';
import { AcademicYearService } from '../../services/academic-year.service';
import { firstValueFrom } from 'rxjs';

@Component({
  selector: 'app-collect-fees',
  standalone: true,
  imports: [CommonModule, FormsModule, TableModule, CardModule, ButtonModule, InputTextModule, InputNumberModule, SelectModule, DatePickerModule, ToastModule, AutoCompleteModule, TagModule],
  providers: [MessageService],
  templateUrl: './collect-fees.html',
  styleUrl: './collect-fees.scss'
})
export class CollectFees implements OnInit {
  private studentsApi = inject(StudentsService);
  private feesApi = inject(StudentFeesService);
  private toast = inject(MessageService);
  private academicYearApi = inject(AcademicYearService);

  // UI State
  loading = false;
  paying = false;
  today = new Date();

  // Data
  students = signal<Student[]>([]);
  // Autocomplete state
  studentInput: Student | null = null;
  studentSuggestions: Student[] = [];
  selectedStudentId: number | null = null;
  dues = signal<StudentFeeLedgerEntry[]>([]); // current month pending dues
  upcomingFees = signal<StudentFeeLedgerEntry[]>([]); // future months fees (unselected by default)
  // Selection is client-only; use string keys so rows without IDs can be toggled
  selection = signal<Set<string>>(new Set());

  // Current month (auto-set, no UI picker)
  currentMonth: Date = new Date();
  ayStart: Date | null = null;
  ayEnd: Date | null = null;

  // Payment form
  payment = {
    mode: 'Cash' as 'Cash'|'Cheque'|'UPI',
    amount: 0,
    reference: '',
    date: new Date(),
    discountDelta: 0
  };

  // Payment mode options for the select dropdown
  paymentModeOptions = [
    { label: 'Cash', value: 'Cash' },
    { label: 'Cheque', value: 'Cheque' },
    { label: 'UPI', value: 'UPI' },
  ];

  // Computed totals
  selectedRows = computed(() => {
    const selected = this.selection();
    const allRows = [...this.dues(), ...this.upcomingFees()];
    return allRows.filter(row => {
      const key = this.rowKey(row);
      return !!(key && selected.has(key));
    });
  });
  totalDue = computed(() => this.selectedRows().reduce((sum, r) => sum + (r.outstanding ?? 0), 0));

  // Selected rows per table (so UI can show per-table totals)
  selectedCurrentRows = computed(() => {
    const selected = this.selection();
    return this.dues().filter(row => {
      const k = this.rowKey(row);
      return !!(k && selected.has(k));
    });
  });
  totalDueCurrent = computed(() => this.selectedCurrentRows().reduce((s, r) => s + (r.outstanding ?? 0), 0));

  selectedUpcomingRows = computed(() => {
    const selected = this.selection();
    return this.upcomingFees().filter(row => {
      const k = this.rowKey(row);
      return !!(k && selected.has(k));
    });
  });
  totalUpcoming = computed(() => this.selectedUpcomingRows().reduce((s, r) => s + (r.outstanding ?? 0), 0));

  // Selection helpers
  private isPayable(r: StudentFeeLedgerEntry): boolean { return (r.outstanding ?? 0) > 0; }
  allSelected = computed(() => {
    const eligible = this.dues().filter(r => this.isPayable(r));
    if (!eligible.length) return false;
    return eligible.every(r => this.isSelected(r));
  });
  private getYearMonth(d: Date): { y: number; m: number } { return { y: d.getFullYear(), m: d.getMonth() + 1 }; }
  private deriveYearMonthForRow(row: StudentFeeLedgerEntry): { y: number; m: number } {
    if (row.dueDate) {
      const due = new Date(row.dueDate);
      if (!Number.isNaN(due.getTime())) {
        return { y: due.getFullYear(), m: due.getMonth() + 1 };
      }
    }
    return this.getYearMonth(this.currentMonth);
  }

  // Build a stable client-side key for selection
  private rowKey(r: StudentFeeLedgerEntry): string | null {
    if (!this.isPayable(r)) return null;
    if (r.studentFeeId) return `sf:${r.studentFeeId}`;
    if (!r.feeId) return null;
    const target = this.deriveYearMonthForRow(r);
    const mm = String(target.m).padStart(2, '0');
    return `fee:${r.feeId}:${target.y}-${mm}`;
  }

  statusSeverity(row: StudentFeeLedgerEntry): 'success' | 'info' | 'warning' | 'danger' | undefined {
    const status = (row.status || '').toLowerCase();
    if (status === 'paid') return 'success';
    if (status === 'partial') return 'warning';
    if (status === 'overdue') return 'danger';
    return 'info';
  }

  isSelected(r: StudentFeeLedgerEntry): boolean {
    const k = this.rowKey(r);
    return !!(k && this.selection().has(k));
  }

  ngOnInit(): void {
    this.loadStudents();
    const fallback = this.computeAcademicYearBounds(this.today);
    this.ayStart = fallback.start;
    this.ayEnd = fallback.end;
    this.loadAcademicYearConstraints();
  }

  // Default AY: Apr 1 to Mar 31. Adjust here if your school uses a different cycle.
  private computeAcademicYearBounds(base: Date): { start: Date; end: Date } {
    const y = base.getFullYear();
    const m = base.getMonth() + 1; // 1-12
    if (m >= 4) {
      // AY starts Apr 1 current year, ends Mar 31 next year
      return { start: new Date(y, 3, 1), end: new Date(y + 1, 2, 31) };
    } else {
      // AY starts Apr 1 previous year, ends Mar 31 current year
      return { start: new Date(y - 1, 3, 1), end: new Date(y, 2, 31) };
    }
  }

  private async loadAcademicYearConstraints() {
    try {
      const academicYears = await firstValueFrom(this.academicYearApi.getAcademicYears());
      const currentAY = academicYears.find(ay => (ay.Status || '').toLowerCase() === 'active');
      if (currentAY?.StartDate && currentAY?.EndDate) {
        this.ayStart = new Date(currentAY.StartDate);
        this.ayEnd = new Date(currentAY.EndDate);
        return;
      }
    } catch (error) {
      console.error('Failed to load academic year constraints:', error);
    }
    if (!this.ayStart || !this.ayEnd) {
      const { start, end } = this.computeAcademicYearBounds(this.today);
      this.ayStart = start;
      this.ayEnd = end;
    }
  }

  private loadStudents() {
    // Lazy load in pages for autocomplete; initial load to warm cache
    this.studentsApi.getStudents({ status: 'Active' }).subscribe({
      next: rows => this.students.set(rows || []),
      error: () => this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load students' })
    });
  }

  searchStudents(event: any) {
    const q = String(event?.query || '').toLowerCase();
    const all = this.students();
    const filtered = q
      ? all.filter(s => (s.StudentName || '').toLowerCase().includes(q)
        || (s.ClassName || '').toLowerCase().includes(q)
        || (s.SectionName || '').toLowerCase().includes(q)
        || String(s.StudentID || '').includes(q))
      : all;
    this.studentSuggestions = (filtered || []).slice(0, 50);
  }

  onStudentSelected(ev: any) {
    const s = ev?.value;
    this.selectedStudentId = s?.StudentID || null;
    // Keep the selected object in the model; AutoComplete uses `field` to display the label
    this.studentInput = s || null;
    this.onStudentChange();
  }

  onStudentCleared() {
    this.studentInput = null;
    this.selectedStudentId = null;
    this.selection.set(new Set());
    this.dues.set([]);
    this.upcomingFees.set([]);
  }

  onStudentChange() {
    this.selection.set(new Set());
    this.dues.set([]);
    this.upcomingFees.set([]);
    if (!this.selectedStudentId) return;
    this.loading = true;

    this.feesApi.getPlan(this.selectedStudentId, this.currentMonth).subscribe({
      next: plan => {
        try {
          const reference = new Date(plan.meta.referenceYear, plan.meta.referenceMonth - 1, 1);
          if (!Number.isNaN(reference.getTime())) {
            this.currentMonth = reference;
          }
          if (plan.meta.academicYearStart) {
            const start = new Date(plan.meta.academicYearStart);
            if (!Number.isNaN(start.getTime())) this.ayStart = start;
          }
          if (plan.meta.academicYearEnd) {
            const end = new Date(plan.meta.academicYearEnd);
            if (!Number.isNaN(end.getTime())) this.ayEnd = end;
          }

          const current = (plan.current ?? []).filter(r => (r.outstanding ?? 0) > 0);
          const upcoming = (plan.upcoming ?? []).filter(r => (r.outstanding ?? 0) > 0);

          this.dues.set(current);
          this.upcomingFees.set(upcoming);

          this.autoSelectAll();
          this.recalcDefaultAmount();

          if (!current.length && !upcoming.length) {
            this.toast.add({ severity: 'info', summary: 'No dues', detail: 'No pending or upcoming fees found for selected student' });
          }
        } catch (error) {
          console.error('Error processing student fee plan', error);
          this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to process student fee plan' });
        } finally {
          this.loading = false;
        }
      },
      error: () => {
        this.loading = false;
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load student fee plan' });
      }
    });
  }

  autoSelectAll() {
    const s = new Set<string>();
    for (const r of this.dues()) {
      if (!this.isPayable(r)) continue;
      const k = this.rowKey(r);
      if (k) s.add(k);
    }
    this.selection.set(s);
  }

  async toggleRow(row: StudentFeeLedgerEntry) {
    if (!this.isPayable(row)) return;
    const k = this.rowKey(row);
    if (!k) return;
    const s = new Set(this.selection());
    if (s.has(k)) s.delete(k); else s.add(k);
    this.selection.set(s);
    this.recalcDefaultAmount();
  }

  async toggleAll(ev: any) {
    const checked = ev?.target?.checked === true;
    if (!checked) {
      this.selection.set(new Set());
      this.recalcDefaultAmount();
      return;
    }
    const s = new Set<string>();
    for (const r of this.dues()) {
      if (!this.isPayable(r)) continue;
      const k = this.rowKey(r);
      if (k) s.add(k);
    }
    this.selection.set(s);
    this.recalcDefaultAmount();
  }

  private recalcDefaultAmount() {
    const tot = this.totalDue();
    this.payment.amount = Number(tot.toFixed(2));
  }

  // Computed: Amount - Additional Discount, capped by total selected due
  get netPayable(): number {
    const due = Number(this.totalDue() || 0);
    const amt = Number(this.payment.amount || 0);
    const disc = Number(this.payment.discountDelta || 0);
    const maxAfterDiscount = Math.max(0, Number((due - disc).toFixed(2)));
    const val = Math.min(amt, maxAfterDiscount);
    return Number(Math.max(0, val).toFixed(2));
  }

  onAmountChange(val: number) {
    const amount = Number(val || 0);
    this.payment.amount = Number(amount.toFixed(2));
    // If discount is now more than amount, cap and warn
    if (Number(this.payment.discountDelta || 0) > amount) {
      this.payment.discountDelta = Number(amount.toFixed(2));
      this.toast.add({ severity: 'warn', summary: 'Discount adjusted', detail: 'Additional discount cannot exceed payment amount; it has been reduced.' });
    }
  }

  onDiscountChange(val: number) {
    const disc = Number(val || 0);
    const amount = Number(this.payment.amount || 0);
    const totalDue = Number(this.totalDue() || 0);
    // Prevent discount > amount
    if (disc > amount) {
      this.payment.discountDelta = Number(amount.toFixed(2));
      this.toast.add({ severity: 'warn', summary: 'Invalid discount', detail: 'Additional discount cannot be greater than payment amount; value adjusted.' });
      return;
    }
    // Also prevent discount being more than total selected due
    if (disc > totalDue) {
      this.payment.discountDelta = Number(totalDue.toFixed(2));
      this.toast.add({ severity: 'warn', summary: 'Invalid discount', detail: 'Additional discount cannot exceed total selected due; value adjusted.' });
      return;
    }
    this.payment.discountDelta = Number(disc.toFixed(2));
  }

  pay() {
    if (!this.selectedStudentId) { this.toast.add({ severity: 'warn', summary: 'Select student', detail: 'Please select a student' }); return; }
    const rows = this.selectedRows();
    if (!rows.length) { this.toast.add({ severity: 'warn', summary: 'Select dues', detail: 'Please select at least one due' }); return; }
    const toPay = Number(this.payment.amount || 0);
    if (toPay <= 0) { this.toast.add({ severity: 'warn', summary: 'Enter amount', detail: 'Payment amount must be greater than 0' }); return; }

    // Strategy: ensure studentFeeId exists for each selected row, then distribute payment oldest to newest with at most one call per row
    const dateStr = this.formatDate(this.payment.date);
    const ref = this.payment.reference?.trim() || undefined;
    const mode = this.payment.mode;
    const discountDelta = this.payment.discountDelta || undefined;

    const ensureMissing = async (): Promise<Map<number, StudentFeeLedgerEntry>> => {
      const map = new Map<number, StudentFeeLedgerEntry>();
      const missing = rows
        .filter(r => (!r.studentFeeId || r.studentFeeId <= 0) && !!r.feeId && !!this.selectedStudentId)
        .map(r => {
          const target = this.deriveYearMonthForRow(r);
          return { row: r, payload: { feeId: r.feeId!, year: target.y, month: target.m } };
        });

      if (missing.length) {
        try {
          const resp = await this.feesApi
            .ensureMonthlyBatch(this.selectedStudentId!, missing.map(m => m.payload))
            .toPromise();
          // Map created rows by feeId:year-month so multiple months for same fee are distinct
          const created = new Map<string, number>(); // 'feeId:YYYY-MM' -> studentFeeId
          for (const it of resp?.items ?? []) {
            if (it.studentFeeId > 0) {
              const key = `${it.feeId}:${it.year}-${String(it.month).padStart(2, '0')}`;
              created.set(key, it.studentFeeId);
            }
          }
          for (const m of missing) {
            const payloadKey = `${m.payload.feeId}:${m.payload.year}-${String(m.payload.month).padStart(2, '0')}`;
            const sid = created.get(payloadKey);
            if (sid && sid > 0) {
              // Preserve UI selection: replace fee:<YM> key with sf:<studentFeeId>
              try {
                const oldKey = `fee:${payloadKey}`;
                const s = new Set(this.selection());
                if (s.has(oldKey)) {
                  s.delete(oldKey);
                  s.add(`sf:${sid}`);
                  this.selection.set(s);
                }
              } catch (e) {
                // ignore selection update failures
              }
              m.row.studentFeeId = sid;
              map.set(sid, m.row);
            }
          }
        } catch {
          // Ignore ensure errors; fallback to rows that already have IDs
        }
      }

      for (const r of rows) {
        if (r.studentFeeId && r.studentFeeId > 0) {
          map.set(r.studentFeeId, r);
        }
      }

      return map;
    };

    const buildAndPay = async (): Promise<boolean[]> => {
      const idMap = await ensureMissing();
      // Sort by due date, oldest first
      const sorted = [...idMap.values()].sort((a, b) => {
        const aDate = a.dueDate ? new Date(a.dueDate) : this.today;
        const bDate = b.dueDate ? new Date(b.dueDate) : this.today;
        return aDate.getTime() - bDate.getTime();
      });
      let remaining = toPay;
      let remainingDiscount = Number((discountDelta || 0).toFixed(2));
      const totalSelectedDue = sorted.reduce((s, r) => s + Number(r.outstanding ?? 0), 0);
      const batch: PaymentRequest[] = [];
      for (let i = 0; i < sorted.length; i++) {
        const r = sorted[i];
        if (!r.studentFeeId || r.studentFeeId <= 0) continue;
        if (remaining <= 0 && remainingDiscount <= 0) break;
        const need = Number(r.outstanding ?? 0);
        if (need <= 0) continue;
        // Discount share
        let share = 0;
        if (remainingDiscount > 0 && totalSelectedDue > 0) {
          if (i < sorted.length - 1) {
            share = Number(((need / totalSelectedDue) * (discountDelta || 0)).toFixed(2));
            share = Math.min(share, remainingDiscount, need);
          } else {
            share = Math.min(remainingDiscount, need);
          }
          remainingDiscount = Number((remainingDiscount - share).toFixed(2));
        }
        const effectiveNeed = Math.max(0, Number((need - share).toFixed(2)));
        const payNow = Math.min(effectiveNeed, remaining);
        remaining = Number((remaining - payNow).toFixed(2));
        if (payNow > 0 || share > 0) {
          batch.push({
            studentFeeId: r.studentFeeId,
            paidAmount: payNow,
            mode,
            transactionRef: ref,
            paymentDate: dateStr,
            discountDelta: share || undefined
          });
        }
      }
      if (!batch.length) return [];
      const res = await this.feesApi.addPaymentsBatch(batch).toPromise();
      return (res?.items || []).map(it => !!it.ok);
    };

    const tasksPromise = buildAndPay();

    this.paying = true;
    tasksPromise.then((oks) => {
      const okCount = (oks || []).filter(Boolean).length;
      if (okCount === 0) {
        this.toast.add({ severity: 'info', summary: 'Nothing due', detail: 'Selected items are already cleared' });
      } else {
        this.toast.add({ severity: 'success', summary: 'Payment recorded', detail: `Applied to ${okCount} item(s)` });
      }
      this.onStudentChange();
    }).catch(() => {
      this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to record payment' });
    }).finally(() => this.paying = false);
  }

  private formatDate(d: Date | string): string {
    const dt = typeof d === 'string' ? new Date(d) : d;
    const y = dt.getFullYear();
    const m = (dt.getMonth() + 1).toString().padStart(2, '0');
    const da = dt.getDate().toString().padStart(2, '0');
    return `${y}-${m}-${da}`;
  }
}
