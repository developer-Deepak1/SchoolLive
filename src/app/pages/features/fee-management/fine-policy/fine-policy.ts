import { Component, OnInit, inject, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
// PrimeNG
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { ToggleSwitchModule } from 'primeng/toggleswitch';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { MessageService, ConfirmationService } from 'primeng/api';
// Services
import { FeeService } from '../services/fee.service';
import { FinePolicyService } from '@/pages/features/fee-management/services/fine-policy.service';
import { Fee, FinePolicy as FinePolicyModel, FinePolicyRequest } from '../services/fee-management.models';

@Component({
  selector: 'app-fine-policy',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    TableModule, ButtonModule, CardModule, InputNumberModule, InputTextModule, SelectModule, ToggleSwitchModule, ToastModule, ConfirmDialogModule
  ],
  templateUrl: './fine-policy.html',
  styleUrl: './fine-policy.scss',
  providers: [MessageService, ConfirmationService]
})
export class FinePolicy implements OnInit {
  @ViewChild('formCard', { static: false }) formCard?: ElementRef<HTMLDivElement>;
  private feeService = inject(FeeService);
  private svc = inject(FinePolicyService);
  private toast = inject(MessageService);
  private confirm = inject(ConfirmationService);

  // UI state
  loading = false;
  saving = false;

  // Data
  feeOptions: { label: string; value: number | null }[] = [{ label: 'All Fees', value: null }];
  applyTypeOptions: { label: string; value: 'Fixed' | 'PerDay' | 'Percentage' }[] = [
    { label: 'Fixed', value: 'Fixed' },
    { label: 'Per Day', value: 'PerDay' },
    { label: 'Percentage', value: 'Percentage' }
  ];
  // policies is now a flattened list where each item may carry group metadata
  policies: Array<FinePolicyModel & { __groupStart?: boolean; __groupSize?: number }> = [];
  // map finePolicyId -> global index (0-based) for fast lookup in template
  policiesIndexMap: Record<number, number> = {};

  // Form model (template-driven)
  form: FinePolicyRequest & { finePolicyId?: number } = this.createEmptyForm();
  editingId: number | null = null;

  ngOnInit(): void {
    this.loadFees();
    this.loadPolicies();
  }

  private createEmptyForm(): FinePolicyRequest & { finePolicyId?: number } {
    return {
      finePolicyId: undefined,
      feeId: null,
      applyType: 'Fixed',
      amount: 0,
      graceDays: 0,
      maxAmount: null,
      isActive: true
    };
  }

  private loadFees() {
    this.feeService.getFees().subscribe({
      next: (fees: Fee[]) => {
        const opts: { label: string; value: number | null }[] = [{ label: 'All Fees', value: null }];
        (fees || []).forEach(f => {
          if (f.feeId != null && f.isActive === true && f.schedule?.scheduleType !== 'OneTime') {
            opts.push({ label: f.feeName, value: f.feeId });
          }
        });
        this.feeOptions = opts;
      },
      error: () => {
        this.feeOptions = [{ label: 'All Fees', value: null }];
      }
    });
  }

  private loadPolicies() {
    this.loading = true;
    this.svc.list().subscribe({
      next: (rows: FinePolicyModel[]) => { 
        this.policies = (rows || []).slice();
        // sort by feeId so groups are contiguous (null/AllFees first)
        this.policies.sort((a,b) => {
          const A = a.feeId == null ? -1 : a.feeId;
          const B = b.feeId == null ? -1 : b.feeId;
          return Number(A) - Number(B);
        });
        // Build flattened policies with group metadata (groupStart and groupSize)
        const map = new Map<string, FinePolicyModel[]>();
        for (const p of this.policies || []) {
          const key = p.feeId == null ? '__ALL__' : String(p.feeId);
          if (!map.has(key)) map.set(key, []);
          map.get(key)!.push(p);
        }
        const flattened: Array<FinePolicyModel & { __groupStart?: boolean; __groupSize?: number }> = [];
        for (const [key, group] of map.entries()) {
          const size = group.length;
          for (let i = 0; i < group.length; i++) {
            const item = group[i] as FinePolicyModel & { __groupStart?: boolean; __groupSize?: number };
            if (i === 0) {
              item.__groupStart = true;
              item.__groupSize = size;
            } else {
              item.__groupStart = false;
            }
            flattened.push(item);
          }
        }
        this.policies = flattened;
        // build index map after flattening
        this.policiesIndexMap = {};
        this.policies.forEach((p, i) => { if (p.finePolicyId != null) this.policiesIndexMap[p.finePolicyId] = i; });
        this.loading = false; 
      },
      error: () => { this.policies = []; this.loading = false; }
    });
  }

  // grouping handled inline in loadPolicies to produce flattened policies with metadata

  // Return 1-based global index for a policy row. Used from template to avoid inline arrow functions.
  getGlobalIndex(row: FinePolicyModel): number {
    if (!row || row.finePolicyId == null) return -1;
    const idx = this.policiesIndexMap[row.finePolicyId];
    return typeof idx === 'number' ? (idx + 1) : -1;
  }

  // Helpers
  feeLabel(id: number | null | undefined): string {
    if (id == null) return 'All Fees';
    const f = this.feeOptions.find(o => o.value === id);
    return f?.label || `Fee #${id}`;
  }

  amountSuffix(t: string | undefined): string {
    if (t === 'PerDay') return '/day';
    if (t === 'Percentage') return '%';
    return '';
  }

  amountLabel(t: 'Fixed' | 'PerDay' | 'Percentage' | string | undefined): string {
    switch (t) {
      case 'PerDay':
        return 'Amount (per day)';
      case 'Percentage':
        return 'Percentage';
      case 'Fixed':
      default:
        return 'Amount';
    }
  }

  // Actions
  onApplyTypeChange(type: 'Fixed' | 'PerDay' | 'Percentage') {
    this.form.applyType = type;
    this.form.amount = 0;
  }

  openNew() {
    this.editingId = null;
    this.form = this.createEmptyForm();
  }

  edit(row: FinePolicyModel) {
    this.editingId = row.finePolicyId ?? null;
    this.form = {
      finePolicyId: row.finePolicyId,
      feeId: row.feeId ?? null,
      applyType: row.applyType,
      amount: Number(row.amount || 0),
      graceDays: Number(row.graceDays || 0),
      maxAmount: row.maxAmount != null ? Number(row.maxAmount) : null,
      isActive: !!row.isActive
    };
    // On small screens, scroll the form card into view so user sees the edit form
    try {
      const width = window.innerWidth || document.documentElement.clientWidth;
      if (width <= 960 && this.formCard && this.formCard.nativeElement) {
        // small delay to allow change detection to render form values
        setTimeout(() => {
          try {
            // Scroll a bit further so the card sits below any fixed headers
            const OFFSET = 120; // px to offset from top — increases how much we scroll
            const rect = this.formCard!.nativeElement.getBoundingClientRect();
            const target = (window.scrollY || window.pageYOffset) + rect.top - OFFSET;
            window.scrollTo({ top: target, behavior: 'smooth' });
          } catch (err) {
            // fallback
            this.formCard!.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }, 300);
      }
    } catch (e) {
      // ignore in non-browser environments
    }
  }

  save() {
    // basic validation
    if (this.form.amount == null || Number(this.form.amount) < 0) {
      this.toast.add({ severity: 'warn', summary: 'Validation', detail: 'Amount must be 0 or more' });
      return;
    }
    if (this.form.graceDays == null || Number(this.form.graceDays) < 0) {
      this.toast.add({ severity: 'warn', summary: 'Validation', detail: 'Grace days must be 0 or more' });
      return;
    }
    if (this.form.applyType === 'Percentage' && Number(this.form.amount) > 100) {
      this.toast.add({ severity: 'warn', summary: 'Validation', detail: 'Percentage cannot exceed 100' });
      return;
    }

    const payload: FinePolicyRequest = {
      feeId: this.form.feeId ?? null,
      applyType: this.form.applyType,
      amount: Number(this.form.amount || 0),
      graceDays: Number(this.form.graceDays || 0),
      maxAmount: this.form.maxAmount != null ? Number(this.form.maxAmount) : null,
      isActive: !!this.form.isActive
    };

    this.saving = true;
    const req$ = this.editingId
      ? this.svc.update(this.editingId, payload)
      : this.svc.create(payload);
    req$.subscribe({
      next: (_saved: FinePolicyModel) => {
        this.saving = false;
        this.toast.add({ severity: 'success', summary: 'Saved', detail: 'Fine policy saved' });
        this.loadPolicies();
        // Clear edit mode and reset the form after successful save
        this.openNew();
      },
      error: () => { this.saving = false; this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save' }); }
    });
  }

  toggleStatus(row: FinePolicyModel, event?: any) {
    if (!row.finePolicyId) return;
    // prefer event.checked/value like other components
    const prev = !!row.isActive;
    const intended = event && (typeof event.checked === 'boolean' ? event.checked : (typeof event.value === 'boolean' ? event.value : !prev)) || !prev;
    // optimistic
    row.isActive = intended;
    this.svc.toggleStatus(row, intended).subscribe({
      next: (updated: FinePolicyModel) => {
        row.isActive = updated.isActive;
        this.toast.add({ severity: 'info', summary: 'Status Updated', detail: 'Fine policy status updated' });
      },
      error: () => { row.isActive = prev; this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update status' }); }
    });
  }

  remove(row: FinePolicyModel) {
    if (!row.finePolicyId) return;
    this.confirm.confirm({
      header: 'Confirm', message: 'Are you sure you want to delete this fine policy?', icon: 'pi pi-exclamation-triangle',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.svc.delete(row.finePolicyId!).subscribe({
          next: (ok: boolean) => { if (ok) { this.toast.add({ severity: 'success', summary: 'Deleted', detail: 'Fine policy deleted' }); this.loadPolicies(); } else { this.toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed' }); } },
          error: () => this.toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed' })
        });
      }
    });
  }

}
