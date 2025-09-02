import { Component, OnInit, ViewChild, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, FormsModule } from '@angular/forms';
import { Table, TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { SplitButtonModule } from 'primeng/splitbutton';
import { ToastModule } from 'primeng/toast';
import { ToolbarModule } from 'primeng/toolbar';
import { InputTextModule } from 'primeng/inputtext';
import { DatePickerModule } from 'primeng/datepicker';
import { InputIconModule } from 'primeng/inputicon';
import { IconFieldModule } from 'primeng/iconfield';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { SelectModule } from 'primeng/select';
import { ConfirmationService, MessageService } from 'primeng/api';
import { AcademicYear, AcademicYearResponse } from '../model/academic-year.model';
import { toLocalYMDIST } from '@/utils/date-utils';
import { AcademicYearService } from '../services/academic-year.service';

interface Column { field: string; header: string; }

// -----------------------------
// Constants & Utility Typings
// -----------------------------
const STATUS_VALUES = ['Active', 'Upcoming', 'End'] as const;
type AcademicStatus = typeof STATUS_VALUES[number];
const STATUS_ACTIVE: AcademicStatus = 'Active';

// Map status -> badge tailwind classes (excluding common base classes)
const STATUS_BADGE_CLASSES: Record<AcademicStatus, string> = {
  Active: 'bg-green-100 text-green-800',
  Upcoming: 'bg-blue-100 text-blue-800',
  End: 'bg-red-100 text-red-800'
};

@Component({
  selector: 'app-academic-years',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    TableModule,
    ButtonModule,
  SplitButtonModule,
    ToastModule,
    ToolbarModule,
    InputTextModule,
    InputIconModule,
    IconFieldModule,
    ConfirmDialogModule,
    DatePickerModule,
    SelectModule
  ],
  templateUrl: './academic-years.html',
  styleUrl: './academic-years.scss',
  providers: [MessageService, ConfirmationService, AcademicYearService]
})
export class AcademicYears implements OnInit {
  yearForm!: FormGroup;
  editing = false;            // true when editing existing record
  submitted = false;          // form submit attempt flag
  years = signal<AcademicYear[]>([]); // reactive list

  // Options for status select (derived from constants)
  readonly statusOptions = STATUS_VALUES.map(v => ({ label: v, value: v }));

  // (Kept for potential column-driven table extension, though not currently used directly)
  cols: Column[] = [
    { field: 'AcademicYearName', header: 'Year Name' },
    { field: 'StartDate', header: 'Start Date' },
    { field: 'EndDate', header: 'End Date' },
    { field: 'Status', header: 'Status' }
  ];

  @ViewChild('dt') dt!: Table;

  // Menu items for split button (end-date actions)
  endPresetItems: any[] = [
    { label: '1 month', command: () => this.applyDurationPreset('1m') },
    { label: '3 months', command: () => this.applyDurationPreset('3m') },
    { label: '6 months', command: () => this.applyDurationPreset('6m') },
    { label: '1 year', command: () => this.applyDurationPreset('1y') }
  ];

  constructor(
    private fb: FormBuilder,
    private service: AcademicYearService,
    private msg: MessageService,
    private confirm: ConfirmationService
  ) { this.initForm(); }

  private initForm() {
    this.yearForm = this.fb.group({
      AcademicYearID: [null],
      AcademicYearName: ['', [Validators.required]],
  StartDate: [null, [Validators.required]],
  EndDate: [null, [Validators.required]],
      Status: [STATUS_ACTIVE, [Validators.required]]
    });
    this.resetFormState();

    // React to StartDate changes in one place
    this.yearForm.get('StartDate')?.valueChanges.subscribe(startDateValue => {
      const statusControl = this.yearForm.get('Status');
      if (startDateValue) {
        this.enable('EndDate');
        if (!statusControl?.disabled) {
          statusControl?.setValue(this.computeStatusFromStart(startDateValue));
        }
      } else {
        this.disable('EndDate');
  this.yearForm.patchValue({ EndDate: null });
        if (!statusControl?.disabled) statusControl?.setValue(STATUS_ACTIVE);
      }
    });
  }

  // Centralized helper to set the form to a known clean state
  private resetFormState() {
    this.yearForm.reset({
      AcademicYearID: null,
      AcademicYearName: '',
      StartDate: null,
      EndDate: null,
      Status: STATUS_ACTIVE
    });
    this.submitted = false;
    this.yearForm.markAsPristine();
    this.yearForm.markAsUntouched();
    this.disable('EndDate');
    this.enable('Status');
  }

  // Convenience helpers
  private enable(name: string) { this.yearForm.get(name)?.enable({ emitEvent: false }); }
  private disable(name: string) { this.yearForm.get(name)?.disable({ emitEvent: false }); }

  // Compute status: if startDate is strictly after today => Upcoming, otherwise Active
  private computeStatusFromStart(startDateValue: any): AcademicStatus {
    const sd = this.toLocalDateOnly(startDateValue);
    if (!sd) return STATUS_ACTIVE;
    const today = new Date();
    const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    return (sd.getTime() > todayOnly.getTime() ? 'Upcoming' : STATUS_ACTIVE);
  }

  // Expose predicted status for template display
  get predictedStatus(): string {
    const sd = this.yearForm.get('StartDate')?.value;
    if (!sd) return '';
    return this.computeStatusFromStart(sd);
  }

  // Parse various date inputs into a local date-only Date (midnight local)
  private toLocalDateOnly(v: any): Date | null {
    // null/undefined check
    if (v === null || v === undefined) return null;

    // If already a Date, normalize to local midnight
    if (v instanceof Date) return new Date(v.getFullYear(), v.getMonth(), v.getDate());

    // Numeric timestamp (ms)
    if (typeof v === 'number' && isFinite(v)) {
      const d = new Date(v);
      return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    }

    if (typeof v === 'string') {
      // 1) Plain date like YYYY-MM-DD -> use parts (avoids timezone interpretation)
      const ymd = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (ymd) return new Date(Number(ymd[1]), Number(ymd[2]) - 1, Number(ymd[3]));

      // 2) ISO datetime (YYYY-MM-DDTHH:MM:SS...) -> extract date prefix to avoid TZ shifts
      const isoDatePrefix = v.match(/^(\d{4}-\d{2}-\d{2})/);
      if (isoDatePrefix) {
        const parts = isoDatePrefix[1].split('-');
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
      }

      // 3) Numeric string timestamp
      const num = Number(v);
      if (!isNaN(num) && isFinite(num)) {
        const d = new Date(num);
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
      }

      // 4) Fallback: rely on Date parsing and normalize
      const parsed = new Date(v);
      if (!isNaN(parsed.getTime())) return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    }

    return null;
  }

  ngOnInit(): void { this.loadYears(); }

  loadYears() {
    this.service.getAcademicYearsResponse().subscribe({
      next: response => {
        if (response.success && response.data) {
          this.years.set(response.data);
        } else {
          this.years.set([]);
          if (response.message) {
            this.msg.add({ severity: 'warn', summary: 'Warning', detail: response.message.toString(), life: 3000 });
          }
        }
      },
      error: (err) => {
        console.error('Load years error:', err);
        this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load academic years', life: 3000 });
      }
    });
  }

  openNew() {
    this.editing = false;
    this.resetFormState();
  }

  editYear(y: AcademicYear) {
    
    const formData = {
      AcademicYearID: y.AcademicYearID ?? null,
  AcademicYearName: y.AcademicYearName ?? '',
  StartDate: y.StartDate ? this.toLocalDateOnly(y.StartDate) : null,
  EndDate: y.EndDate ? this.toLocalDateOnly(y.EndDate) : null,
      Status: y.Status as AcademicStatus
    };
    this.yearForm.patchValue(formData);
  this.editing = true;
  // Status cannot be changed if Active (business rule)
  (y.Status === STATUS_ACTIVE) ? this.disable('Status') : this.enable('Status');
  formData.StartDate ? this.enable('EndDate') : this.disable('EndDate');
  }

  deleteYear(y: AcademicYear) {
    if (!y.AcademicYearID) return;
    this.confirm.confirm({
      message: `Delete ${y.AcademicYearName}?`,
      header: 'Confirm',
      icon: 'pi pi-exclamation-triangle',
      accept: () => {
        this.service.deleteAcademicYearResponse(y.AcademicYearID!).subscribe({
          next: response => {
            if (response.success) {
              this.msg.add({ 
                severity: 'success', 
                summary: 'Deleted', 
                detail: response.message?.toString() || 'Academic Year deleted', 
                life: 3000 
              });
              this.loadYears();
            } else {
              this.msg.add({ 
                severity: 'error', 
                summary: 'Error', 
                detail: response.message?.toString() || 'Delete failed', 
                life: 3000 
              });
            }
          },
          error: (err) => {
            console.error('Delete error:', err);
            this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Delete failed', life: 3000 });
          }
        });
      }
    });
  }

  saveYear() {
    this.submitted = true;
    if (this.yearForm.invalid) {
      this.msg.add({ severity: 'error', summary: 'Error', detail: 'Fix form errors', life: 3000 });
      return;
    }

    const formValue = this.yearForm.getRawValue(); // include disabled controls
    const val: AcademicYear = {
      ...formValue,
      StartDate: this.formatDateOnly(formValue.StartDate),
      EndDate: this.formatDateOnly(formValue.EndDate)
    };

    const request$ = val.AcademicYearID
      ? this.service.updateAcademicYearResponse(val)
      : this.service.createAcademicYearResponse(val);

    request$.subscribe({
      next: response => {
        if (response.success) {
          this.msg.add({
            severity: 'success',
            summary: 'Saved',
            detail: response.message?.toString() || `Academic Year ${val.AcademicYearID ? 'Updated' : 'Created'}`,
            life: 3000
          });
          this.loadYears();
          this.editing = false;
          this.resetFormState();
        } else {
          this.msg.add({
            severity: 'error',
            summary: 'Error',
            detail: response.message?.toString() || 'Save failed',
            life: 3000
          });
        }
      },
      error: err => {
        this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Save failed', life: 3000 });
      }
    });
  }

  cancelEdit() {
    this.editing = false;
    this.resetFormState();
  }

  onGlobalFilter(table: Table, event: Event) {
    table.filterGlobal((event.target as HTMLInputElement).value, 'contains');
  }

  isFieldInvalid(name: string): boolean {
    const c = this.yearForm.get(name);
    if (!c) return false;
    
    // Only show validation errors if:
    // 1. Field is invalid AND
    // 2. Either the field has been touched/dirty OR form has been submitted
    return c.invalid && (c.dirty || c.touched || (this.submitted && !!c.errors));
  }

  get startDateValue(): Date | null {
  const startDate = this.yearForm.get('StartDate')?.value;
  return this.toLocalDateOnly(startDate);
  }

  get isStatusDisabled(): boolean { return !!this.yearForm.get('Status')?.disabled; }

  get isEndDateDisabled(): boolean { return !this.yearForm.get('StartDate')?.value; }

  // Earliest allowed StartDate: one day after the maximum EndDate among existing academic years
  // If editing an existing year, exclude it from the scan so its own EndDate doesn't push the
  // allowed earliest start past its current StartDate. This allows editing without being blocked
  // by the min date rule derived from other records.
  get nextStartDate(): Date | null {
    const list = this.years();
    if (!list || list.length === 0) return null;

    const excludeId = this.yearForm.get('AcademicYearID')?.value ?? null;
    let maxEnd: Date | null = null;
    for (const y of list) {
      if (excludeId != null && y.AcademicYearID === excludeId) continue; // skip current record when editing
      if (!y.EndDate) continue;
      const d = this.toLocalDateOnly(y.EndDate);
      if (!d) continue;
      if (!maxEnd || d.getTime() > maxEnd.getTime()) maxEnd = d;
    }
    if (!maxEnd) return null;
    return new Date(maxEnd.getFullYear(), maxEnd.getMonth(), maxEnd.getDate() + 1);
  }

  // Helper: set StartDate to the earliest allowed date
  jumpToNextStart() {
    const d = this.nextStartDate;
    if (!d) return;
    // patch form value with a Date instance
    this.yearForm.patchValue({ StartDate: new Date(d.getFullYear(), d.getMonth(), d.getDate()) });
    // enable EndDate since StartDate now has a value
    const endDateControl = this.yearForm.get('EndDate');
    endDateControl?.enable();
  }

  // Helper: set EndDate to a sensible default based on StartDate (Start + 1 year - 1 day)
  jumpToDefaultEnd() {
    const s = this.startDateValue;
    if (!s) return;
    const end = new Date(s.getFullYear(), s.getMonth(), s.getDate());
    end.setFullYear(end.getFullYear() + 1);
    end.setDate(end.getDate() - 1);
    this.yearForm.patchValue({ EndDate: end });
    const endDateControl = this.yearForm.get('EndDate');
    endDateControl?.enable();
  }

  // Apply a preset duration (e.g. '1m', '3m', '6m', '1y') to compute and set EndDate
  applyDurationPreset(preset: string | null) {
    if (!preset) return;
    const s = this.startDateValue;
    if (!s) return;
    let end: Date;
    switch (preset) {
      case '1m':
        end = this.addMonths(s, 1);
        break;
      case '3m':
        end = this.addMonths(s, 3);
        break;
      case '6m':
        end = this.addMonths(s, 6);
        break;
      case '1y':
        end = this.addMonths(s, 12);
        break;
      default:
        return;
    }
    this.yearForm.patchValue({ EndDate: end });
    this.yearForm.get('EndDate')?.enable();
  }

  // Add months to a date and return end-date as (start + months) - 1 day for inclusive range
  private addMonths(date: Date, months: number): Date {
    const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    d.setMonth(d.getMonth() + months);
    // make the end inclusive by subtracting 1 day
    d.setDate(d.getDate() - 1);
    return d;
  }

  private formatDateOnly(dateValue: any): string {
    if (!dateValue) return '';
    
    let date: Date;
    if (dateValue instanceof Date) {
      date = dateValue;
    } else if (typeof dateValue === 'string') {
      date = new Date(dateValue);
    } else {
      return '';
    }
    
    // Format as YYYY-MM-DD in India Standard Time (date only, no time)
    return toLocalYMDIST(date) || '';
  }

  // Table helpers
  statusBadgeClass(status: string): string {
    // Fallback if status not in map (defensive)
    const s = (status as AcademicStatus);
    return STATUS_BADGE_CLASSES[s] || 'bg-gray-100 text-gray-800';
  }
}

