import { Component, OnInit, computed, effect, inject, signal } from '@angular/core';
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
import { MessageService } from 'primeng/api';
import { StudentsService } from '../../services/students.service';
import { Student } from '../../model/student.model';
import { StudentFeesService, StudentFeeLedgerRow } from '../services/student-fees.service';

@Component({
  selector: 'app-collect-fees',
  standalone: true,
  imports: [CommonModule, FormsModule, TableModule, CardModule, ButtonModule, InputTextModule, InputNumberModule, SelectModule, DatePickerModule, ToastModule, AutoCompleteModule],
  providers: [MessageService],
  templateUrl: './collect-fees.html',
  styleUrl: './collect-fees.scss'
})
export class CollectFees implements OnInit {
  private studentsApi = inject(StudentsService);
  private feesApi = inject(StudentFeesService);
  private toast = inject(MessageService);

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
  dues = signal<StudentFeeLedgerRow[]>([]);
  selection = signal<Set<number>>(new Set());

  // Month picker (for search context only)
  selectedMonth: Date = new Date();
  ayStart!: Date;
  ayEnd!: Date;

  // Payment form
  payment = {
    mode: 'Cash' as 'Cash'|'Online'|'Cheque'|'UPI',
    amount: 0,
    reference: '',
    date: new Date(),
    discountDelta: 0
  };

  // Computed totals
  private isSelectableRow(r: StudentFeeLedgerRow): boolean {
    return !!r?.StudentFeeID && (r.Outstanding ?? 0) > 0;
  }
  allSelected = computed(() => {
    const eligible = this.dues().filter(r => this.isSelectableRow(r));
    if (!eligible.length) return false;
    const sel = this.selection();
    return eligible.every(r => sel.has(r.StudentFeeID!));
  });
  selectedRows = computed(() => {
    const ids = this.selection().size ? this.selection() : new Set<number>();
    return this.dues().filter(d => ids.has(d.StudentFeeID));
  });
  totalDue = computed(() => this.selectedRows().reduce((sum, r) => sum + (r.Outstanding ?? 0), 0));

  ngOnInit(): void {
    this.loadStudents();
    const { start, end } = this.computeAcademicYearBounds(this.today);
    this.ayStart = start; this.ayEnd = end;
    // Ensure selected month is within academic year bounds
    if (this.selectedMonth < this.ayStart) this.selectedMonth = new Date(this.ayStart);
    if (this.selectedMonth > this.ayEnd) this.selectedMonth = new Date(this.ayEnd);
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
  }

  onStudentChange() {
    this.selection.set(new Set());
    this.dues.set([]);
    if (!this.selectedStudentId) return;
    this.loading = true;
    // If a month is explicitly selected (user changed month), fetch monthly plan; otherwise fetch dues.
    const isMonthSet = !!this.selectedMonth;
    const load$ = isMonthSet ? this.feesApi.getMonthly(this.selectedStudentId, this.selectedMonth) : this.feesApi.getDues(this.selectedStudentId);
    load$.subscribe({
      next: (rows: StudentFeeLedgerRow[]) => { this.dues.set(rows || []); this.loading = false; this.autoSelectAll(); this.recalcDefaultAmount(); },
      error: () => { this.loading = false; this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load dues' }); }
    });
  }

  onMonthChange() {
    // When the month changes explicitly, fetch the monthly plan for the selected month.
    // Clamp selection inside academic year
    if (this.selectedMonth < this.ayStart) this.selectedMonth = new Date(this.ayStart);
    if (this.selectedMonth > this.ayEnd) this.selectedMonth = new Date(this.ayEnd);
    if (this.selectedStudentId) this.onStudentChange();
  }

  autoSelectAll() {
    const s = new Set<number>();
    for (const r of this.dues()) { if ((r.Outstanding ?? 0) > 0) s.add(r.StudentFeeID); }
    this.selection.set(s);
  }

  toggleRow(row: StudentFeeLedgerRow) {
    const s = new Set(this.selection());
    if (s.has(row.StudentFeeID)) s.delete(row.StudentFeeID); else s.add(row.StudentFeeID);
    this.selection.set(s);
    this.recalcDefaultAmount();
  }

  toggleAll(ev: any) {
    const checked = ev?.target?.checked === true;
    const s = new Set<number>();
    if (checked) for (const r of this.dues()) { if ((r.Outstanding ?? 0) > 0) s.add(r.StudentFeeID); }
    this.selection.set(s);
    this.recalcDefaultAmount();
  }

  private recalcDefaultAmount() {
    const tot = this.totalDue();
    this.payment.amount = Number(tot.toFixed(2));
  }

  pay() {
    if (!this.selectedStudentId) { this.toast.add({ severity: 'warn', summary: 'Select student', detail: 'Please select a student' }); return; }
    const rows = this.selectedRows();
    if (!rows.length) { this.toast.add({ severity: 'warn', summary: 'Select dues', detail: 'Please select at least one due' }); return; }
    const toPay = Number(this.payment.amount || 0);
    if (toPay <= 0) { this.toast.add({ severity: 'warn', summary: 'Enter amount', detail: 'Payment amount must be greater than 0' }); return; }

    // Strategy: distribute amount across selected dues from oldest to newest
    const dateStr = this.formatDate(this.payment.date);
    const ref = this.payment.reference?.trim() || undefined;
    const mode = this.payment.mode;
    const discountDelta = this.payment.discountDelta || undefined;

    // Prepare queue with proportional discount
    const sorted = [...rows].sort((a,b) => (new Date(a.DueDate || this.today).getTime()) - (new Date(b.DueDate || this.today).getTime()));
    let remaining = toPay;
    let remainingDiscount = Number((discountDelta || 0).toFixed(2));
    const totalSelectedDue = sorted.reduce((s, r) => s + Number(r.Outstanding || 0), 0);
    const tasks: Promise<boolean>[] = [];
    for (let i = 0; i < sorted.length; i++) {
      const r = sorted[i];
      if (remaining <= 0 && remainingDiscount <= 0) break;
      const need = Number((r.Outstanding ?? 0));
      if (need <= 0) continue;
      // Discount share
      let share = 0;
      if (remainingDiscount > 0 && totalSelectedDue > 0) {
        if (i < sorted.length - 1) {
          share = Number(((need / totalSelectedDue) * (discountDelta || 0)).toFixed(2));
          share = Math.min(share, remainingDiscount, need);
        } else {
          // last row gets the remainder
          share = Math.min(remainingDiscount, need);
        }
        remainingDiscount = Number((remainingDiscount - share).toFixed(2));
      }
      const effectiveNeed = Math.max(0, Number((need - share).toFixed(2)));
      const payNow = Math.min(effectiveNeed, remaining);
      remaining = Number((remaining - payNow).toFixed(2));
      const doPay = async (): Promise<boolean> => {
        const sfid = r.StudentFeeID;
        if (!sfid) return false;
        const ok$ = this.feesApi.addPayment({ StudentFeeID: sfid, PaidAmount: payNow, Mode: mode, TransactionRef: ref, PaymentDate: dateStr, DiscountDelta: share || undefined });
        const ok = await ok$.toPromise();
        return !!ok;
      };
      const p = doPay();
      tasks.push(p);
    }

    if (!tasks.length) { this.toast.add({ severity: 'info', summary: 'Nothing due', detail: 'Selected items are already cleared' }); return; }

    this.paying = true;
    Promise.all(tasks).then((oks) => {
      const okCount = oks.filter(Boolean).length;
      if (okCount > 0) this.toast.add({ severity: 'success', summary: 'Payment recorded', detail: `Applied to ${okCount} item(s)` });
      // Reload dues
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
