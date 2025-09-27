import { Component, OnInit, inject } from '@angular/core';
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
import { FeeService, FeeWithClassSections } from '../services/fee.service';
import { FinePolicyService, FinePolicy as FinePolicyModel } from '@/pages/features/fee-management/services/fine-policy.service';

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
  policies: FinePolicyModel[] = [];

  // Form model (template-driven)
  form: FinePolicyModel = this.createEmptyForm();
  editingId: number | null = null;

  ngOnInit(): void {
    this.loadFees();
    this.loadPolicies();
  }

  private createEmptyForm(): FinePolicyModel {
    return {
      FeeID: null,
      ApplyType: 'Fixed',
      Amount: 0,
      GraceDays: 0,
      MaxAmount: null,
      IsActive: true
    } as FinePolicyModel;
  }

  private loadFees() {
    this.feeService.getFees().subscribe({
      next: (fees: FeeWithClassSections[]) => {
        const opts: { label: string; value: number | null }[] = [{ label: 'All Fees', value: null }];
        (fees || []).forEach(f => {
          if (f.FeeID != null && f.IsActive == true) opts.push({ label: f.FeeName, value: f.FeeID });
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
      next: (rows: FinePolicyModel[]) => { this.policies = rows || []; this.loading = false; },
      error: () => { this.policies = []; this.loading = false; }
    });
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
    this.form.ApplyType = type;
    this.form.Amount = 0;
  }

  openNew() {
    this.editingId = null;
    this.form = this.createEmptyForm();
  }

  edit(row: FinePolicyModel) {
    this.editingId = row.FinePolicyID ?? null;
    this.form = {
      FinePolicyID: row.FinePolicyID,
      FeeID: row.FeeID ?? null,
      ApplyType: row.ApplyType,
      Amount: Number(row.Amount || 0),
      GraceDays: Number(row.GraceDays || 0),
      MaxAmount: row.MaxAmount != null ? Number(row.MaxAmount) : null,
      IsActive: !!row.IsActive
    } as FinePolicyModel;
  }

  save() {
    // basic validation
    if (this.form.Amount == null || Number(this.form.Amount) < 0) {
      this.toast.add({ severity: 'warn', summary: 'Validation', detail: 'Amount must be 0 or more' });
      return;
    }
    if (this.form.GraceDays == null || Number(this.form.GraceDays) < 0) {
      this.toast.add({ severity: 'warn', summary: 'Validation', detail: 'Grace days must be 0 or more' });
      return;
    }
    if (this.form.ApplyType === 'Percentage' && Number(this.form.Amount) > 100) {
      this.toast.add({ severity: 'warn', summary: 'Validation', detail: 'Percentage cannot exceed 100' });
      return;
    }

    const payload: FinePolicyModel = {
      ...(this.editingId ? { FinePolicyID: this.editingId } : {}),
      FeeID: this.form.FeeID ?? null,
      ApplyType: this.form.ApplyType,
      Amount: Number(this.form.Amount || 0),
      GraceDays: Number(this.form.GraceDays || 0),
      MaxAmount: this.form.MaxAmount != null ? Number(this.form.MaxAmount) : null,
      IsActive: !!this.form.IsActive
    } as FinePolicyModel;

    this.saving = true;
    const req$ = this.editingId ? this.svc.update(payload) : this.svc.create(payload);
    req$.subscribe({
      next: (_saved: FinePolicyModel) => {
        this.saving = false;
        this.toast.add({ severity: 'success', summary: 'Saved', detail: 'Fine policy saved' });
        this.loadPolicies();
      },
      error: () => { this.saving = false; this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save' }); }
    });
  }

  toggleStatus(row: FinePolicyModel, event?: any) {
    if (!row.FinePolicyID) return;
    // prefer event.checked/value like other components
    const prev = !!row.IsActive;
    const intended = event && (typeof event.checked === 'boolean' ? event.checked : (typeof event.value === 'boolean' ? event.value : !prev)) || !prev;
    // optimistic
    row.IsActive = intended;
    this.svc.toggleStatus(row.FinePolicyID, intended).subscribe({
      next: (ok: boolean) => {
        if (!ok) { row.IsActive = prev; this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update status' }); }
      },
      error: () => { row.IsActive = prev; this.toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update status' }); }
    });
  }

  remove(row: FinePolicyModel) {
    if (!row.FinePolicyID) return;
    this.confirm.confirm({
      header: 'Confirm', message: 'Delete this fine policy?', icon: 'pi pi-exclamation-triangle',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.svc.delete(row.FinePolicyID!).subscribe({
          next: (ok: boolean) => { if (ok) { this.toast.add({ severity: 'success', summary: 'Deleted', detail: 'Fine policy deleted' }); this.loadPolicies(); } else { this.toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed' }); } },
          error: () => this.toast.add({ severity: 'error', summary: 'Error', detail: 'Delete failed' })
        });
      }
    });
  }

}
