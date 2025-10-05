import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { AutoCompleteModule } from 'primeng/autocomplete';
import { InputTextModule } from 'primeng/inputtext';
import { ToastModule } from 'primeng/toast';
import { TagModule } from 'primeng/tag';
import { MessageService } from 'primeng/api';

import { StudentsService } from '../../services/students.service';
import { Student } from '../../model/student.model';
import { StudentFeesService, StudentFeeLedgerRow } from '../services/student-fees.service';
import { UserService } from '@/services/user.service';
import { USER_ROLES } from '@/pages/common/constant';

type LedgerCategory = 'upcoming' | 'due' | 'overdue';

@Component({
  selector: 'app-fees-upcoming',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, AutoCompleteModule, InputTextModule, ToastModule, TagModule],
  providers: [MessageService],
  templateUrl: './fees-upcoming.html',
  styleUrl: './fees-upcoming.scss'
})
export class FeesUpcoming implements OnInit {
  private studentsApi = inject(StudentsService);
  private feesApi = inject(StudentFeesService);
  private toast = inject(MessageService);
  private userService = inject(UserService);

  loading = false;
  isStudentUser = false;

  // Autocomplete state
  students = signal<Student[]>([]);
  studentInput: Student | null = null;
  studentSuggestions: Student[] = [];
  selectedStudentId: number | null = null;

  // Ledger rows (only dues/outstanding)
  private duesRows = signal<StudentFeeLedgerRow[]>([]);

  // Category-wise computed rows
  // Upcoming exclude rows that should appear in This Month (to avoid duplicates)
  upcomingRows = computed(() => this.duesRows().filter(r => {
    const cat = this.categorize(r);
    if (cat !== 'upcoming') return false;
    // if this row is a candidate for the current month, exclude from upcoming
    if (this.isThisMonthCandidate(r)) return false;
    return true;
  }));

  // This month grouping (shows fees for the current month, including OneTime without explicit DueDate)
  thisMonthRows = computed(() => this.duesRows().filter(r => this.isThisMonthCandidate(r)));

  overdueRows = computed(() => this.filterByCategory('overdue'));

  totalUpcoming = computed(() => this.upcomingRows().reduce((sum, row) => sum + this.getOutstanding(row), 0));
  totalThisMonth = computed(() => this.thisMonthRows().reduce((sum, row) => sum + this.getOutstanding(row), 0));
  totalOverdue = computed(() => this.overdueRows().reduce((sum, row) => sum + this.getOutstanding(row), 0));

  private readonly today = this.getStartOfDay(new Date());

  ngOnInit(): void {
    this.initializeUserContext();
    if (!this.isStudentUser) {
      this.loadStudents();
    }
    if (this.isStudentUser && this.selectedStudentId) {
      this.loadLedger();
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

  private loadStudents() {
    this.studentsApi.getStudents({ status: 'Active' }).subscribe({
      next: rows => this.students.set(rows || []),
      error: () => this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load students' })
    });
  }

  searchStudents(event: any) {
    const query = String(event?.query || '').toLowerCase();
    const allStudents = this.students();
    const filtered = query
      ? allStudents.filter(s => (s.StudentName || '').toLowerCase().includes(query)
        || (s.ClassName || '').toLowerCase().includes(query)
        || (s.SectionName || '').toLowerCase().includes(query)
        || String(s.StudentID || '').includes(query))
      : allStudents;
    this.studentSuggestions = (filtered || []).slice(0, 50);
  }

  onStudentSelected(event: any) {
    const student = event?.value;
    this.selectedStudentId = student?.StudentID || null;
    this.studentInput = student || null;
    this.loadLedger();
  }

  onStudentCleared() {
    this.studentInput = null;
    this.selectedStudentId = null;
    this.duesRows.set([]);
  }

  private loadLedger() {
    if (!this.selectedStudentId) return;
    this.loading = true;
    this.feesApi.getLedger(this.selectedStudentId, { only_due: true, include_paid: false }).subscribe({
      next: rows => {
        const filtered = (rows || []).filter(r => this.getOutstanding(r) > 0);
        this.duesRows.set(this.sortRows(filtered));
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load upcoming fees' });
      }
    });
  }

  private filterByCategory(cat: LedgerCategory): StudentFeeLedgerRow[] {
    return this.duesRows().filter(row => this.categorize(row) === cat);
  }

  private categorize(row: StudentFeeLedgerRow): LedgerCategory {
    const dueDate = this.parseDate(row.DueDate);
    const outstanding = this.getOutstanding(row);
    if (outstanding <= 0) return 'upcoming';
    if (!dueDate) return 'upcoming';
    if (this.isBefore(dueDate, this.today)) return 'overdue';
    if (this.isSameDay(dueDate, this.today)) return 'due';
    return 'upcoming';
  }

  private sortRows(rows: StudentFeeLedgerRow[]): StudentFeeLedgerRow[] {
    return [...rows].sort((a, b) => {
      const da = this.parseDate(a.DueDate);
      const db = this.parseDate(b.DueDate);
      if (!da && !db) return 0;
      if (!da) return 1;
      if (!db) return -1;
      return da.getTime() - db.getTime();
    });
  }

  getOutstanding(row: StudentFeeLedgerRow): number {
    const direct = row.Outstanding;
    if (direct !== undefined && direct !== null) return Number(direct) || 0;
    const amount = Number(row.Amount || 0);
    const fine = Number((row.ComputedFine ?? row.FineAmount) || 0);
    const discount = Number(row.DiscountAmount || 0);
    const paid = Number(row.AmountPaid || 0);
    return Math.max(0, Number((amount + fine - discount - paid).toFixed(2)));
  }

  private parseDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    const parsed = new Date(value);
    return isNaN(parsed.getTime()) ? null : this.getStartOfDay(parsed);
  }

  private getStartOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  private isSameDay(a: Date, b: Date): boolean {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  }

  private isInCurrentMonth(a: Date): boolean {
    return a.getFullYear() === this.today.getFullYear() && a.getMonth() === this.today.getMonth();
  }

  // Decide whether a ledger row should be shown under "This Month"
  private isThisMonthCandidate(row: StudentFeeLedgerRow): boolean {
    // 1) explicit DueDate in current month
    const due = this.parseDate(row.DueDate);
    if (due && this.isInCurrentMonth(due)) return true;

    // 2) if no DueDate, treat OneTime as current-month candidate
    if (!due) {
      // If there's no DueDate and there is outstanding amount, treat it as this month's item
      const outstanding = this.getOutstanding(row);
      if (outstanding > 0) return true;

      // Fallbacks (if outstanding is zero): check explicit schedule/start/end/created hints
      const sched = (row as any).ScheduleType || (row as any).Schedule || null;
      if (sched && String(sched).toLowerCase() === 'onetime') return true;
      const start = this.parseDate((row as any).StartDate);
      if (start && this.isInCurrentMonth(start)) return true;
      const end = this.parseDate((row as any).EndDate);
      if (end && this.isInCurrentMonth(end)) return true;
      const created = this.parseDate((row as any).CreatedAt);
      if (created && this.isInCurrentMonth(created)) return true;
    }
    return false;
  }

  private isBefore(a: Date, b: Date): boolean {
    return a.getTime() < b.getTime();
  }

  formatAmount(num: number | string | null | undefined): string {
    return Number(num || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  statusSeverity(row: StudentFeeLedgerRow): 'success' | 'info' | 'warning' | 'danger' | undefined {
    const status = (row.Status || '').toLowerCase();
    if (status === 'paid') return 'success';
    if (status === 'partial') return 'warning';
    if (status === 'overdue') return 'danger';
    return 'info';
  }
}
