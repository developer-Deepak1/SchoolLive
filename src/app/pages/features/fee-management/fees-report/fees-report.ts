import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { AutoCompleteModule } from 'primeng/autocomplete';
import { InputTextModule } from 'primeng/inputtext';
import { ToastModule } from 'primeng/toast';
import { ButtonModule } from 'primeng/button';
import { TagModule } from 'primeng/tag';
import { MessageService } from 'primeng/api';

import { StudentsService } from '../../services/students.service';
import { Student } from '../../model/student.model';
import { StudentFeesService, StudentFeeLedgerRow } from '../services/student-fees.service';
import { FeeService, FeeWithClassSections } from '../services/fee.service';
import { FeesReceiptDownloadPreview } from '../fees-receipt-download-preview/fees-receipt-download-preview';
import { UserService } from '@/services/user.service';
import { USER_ROLES } from '@/pages/common/constant';

type ScheduleType = 'Recurring' | 'OnDemand' | 'OneTime' | 'Unknown';

@Component({
  selector: 'app-fees-report',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, AutoCompleteModule, InputTextModule, ToastModule, ButtonModule, TagModule, FeesReceiptDownloadPreview],
  providers: [MessageService],
  templateUrl: './fees-report.html',
  styleUrl: './fees-report.scss'
})
export class FeesReport implements OnInit {
  // Services
  private studentsApi = inject(StudentsService);
  private feesApi = inject(StudentFeesService);
  private feeService = inject(FeeService);
  private toast = inject(MessageService);
  private userService = inject(UserService);

  // State
  loading = false;
  students = signal<Student[]>([]);
  studentInput: Student | null = null;
  studentSuggestions: Student[] = [];
  selectedStudentId: number | null = null;
  isStudentUser = false;

  // Fee schedule map for categorization
  private feeTypeById = new Map<number, ScheduleType>();

  // Paid lists
  private paidRows = signal<StudentFeeLedgerRow[]>([]);
  receiptRequest = signal<{ feeId: number; studentId: number; studentFeeId: number; isPreview: boolean; token: number } | null>(null);
  monthlyPaid = computed(() => this.paidRows().filter(r => this.getType(r.FeeID) === 'Recurring'));
  onDemandPaid = computed(() => this.paidRows().filter(r => this.getType(r.FeeID) === 'OnDemand'));
  oneTimePaid = computed(() => this.paidRows().filter(r => this.getType(r.FeeID) === 'OneTime'));

  // Totals
  totalAmountMonthly = computed(() => this.monthlyPaid().reduce((s, r) => s + Number(r.AmountPaid || 0), 0));
  totalAmountOnDemand = computed(() => this.onDemandPaid().reduce((s, r) => s + Number(r.AmountPaid || 0), 0));
  totalAmountOneTime = computed(() => this.oneTimePaid().reduce((s, r) => s + Number(r.AmountPaid || 0), 0));

  ngOnInit(): void {
    this.initializeUserContext();
    if (!this.isStudentUser) {
      this.loadStudents();
    }
    this.loadFeeTypes();
    if (this.isStudentUser && this.selectedStudentId) {
      this.loadPaidLedger();
    }
  }

  private initializeUserContext() {
    const roleId = this.userService.getRoleId();
    if (roleId === USER_ROLES.ROLE_STUDENT) {
      const sid = this.userService.getStudentId();
      if (sid) {
        this.isStudentUser = true;
        this.selectedStudentId = sid;
      } else {
        this.isStudentUser = false;
      }
    } else {
      this.isStudentUser = false;
    }
  }

  // Load all students for autocomplete
  private loadStudents() {
    this.studentsApi.getStudents({ status: 'Active' }).subscribe({
      next: rows => this.students.set(rows || []),
      error: () => this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load students' })
    });
  }

  // Load fee schedule types map
  private loadFeeTypes() {
    this.feeService.getFees().subscribe({
      next: (fees: FeeWithClassSections[]) => {
        this.feeTypeById.clear();
        for (const f of fees || []) {
          const t = (f?.Schedule?.ScheduleType as ScheduleType) || 'Unknown';
          if (typeof f.FeeID === 'number') this.feeTypeById.set(f.FeeID, t);
        }
      },
      error: () => {
        // Not critical for page render; fall back to Unknown
      }
    });
  }

  // Autocomplete search
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
    this.studentInput = s || null;
    this.loadPaidLedger();
  }

  onStudentCleared() {
    this.studentInput = null;
    this.selectedStudentId = null;
    this.paidRows.set([]);
  }

  downloadReceipt(row: StudentFeeLedgerRow) {
    if (!this.selectedStudentId) {
      this.toast.add({ severity: 'warn', summary: 'Receipt', detail: 'Select a student first.' });
      return;
    }

    const request = {
      feeId: row.FeeID,
      studentId: this.selectedStudentId,
      studentFeeId: row.StudentFeeID,
      isPreview: false,
      token: Date.now()
    };
    this.receiptRequest.set(null);
    setTimeout(() => this.receiptRequest.set(request), 0);
  }

  onReceiptDownloaded() {
    this.toast.add({ severity: 'success', summary: 'Receipt', detail: 'Fee receipt downloaded.' });
    this.receiptRequest.set(null);
  }

  onReceiptFailed(detail: string) {
    this.receiptRequest.set(null);
    this.toast.add({ severity: 'error', summary: 'Receipt', detail });
  }

  // Fetch and filter only PAID ledger rows for selected student
  private loadPaidLedger() {
    if (!this.selectedStudentId) return;
    this.loading = true;
    this.feesApi.getLedger(this.selectedStudentId, { include_paid: true, only_due: false }).subscribe({
      next: (rows) => {
        const paid = (rows || []).filter(r => (r.Status || '').toLowerCase() === 'paid');
        this.paidRows.set(paid);
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load paid fees' });
      }
    });
  }

  // Helper: resolve schedule type for a FeeID
  private getType(feeId: number): ScheduleType {
    const t = this.feeTypeById.get(feeId);
    if (t === 'Recurring' || t === 'OnDemand' || t === 'OneTime') return t;
    return 'OnDemand'; // sensible default
  }

  // Util
  formatAmount(n: any): string { return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

  statusSeverity(row: StudentFeeLedgerRow): 'success' | 'info' | 'warning' | 'danger' | undefined {
    const status = (row.Status || '').toLowerCase();
    if (status === 'paid') return 'success';
    if (status === 'partial') return 'warning';
    if (status === 'overdue') return 'danger';
    return 'info';
  }
}
