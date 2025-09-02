import { Component, OnInit, ViewChild, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, FormsModule } from '@angular/forms';
import { Table, TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
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

@Component({
  selector: 'app-academic-years',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    TableModule,
    ButtonModule,
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
  editing = false; // true when editing existing record
  submitted = false;
  years = signal<AcademicYear[]>([]);
  selectedYears!: AcademicYear[] | null;

  statusOptions = [
    { label: 'Active', value: 'Active' },
    { label: 'Upcoming', value: 'Upcoming' },
    { label: 'End', value: 'End' }
  ];

  cols: Column[] = [
    { field: 'AcademicYearName', header: 'Year Name' },
    { field: 'StartDate', header: 'Start Date' },
    { field: 'EndDate', header: 'End Date' },
    { field: 'Status', header: 'Status' }
  ];

  @ViewChild('dt') dt!: Table;

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
      StartDate: ['', [Validators.required]],
      EndDate: ['', [Validators.required]],
      Status: ['Active', [Validators.required]]
    });
    
    // Ensure form starts in pristine state
    this.yearForm.markAsUntouched();
    this.yearForm.markAsPristine();
    
    // Initially disable EndDate since StartDate is empty
    const endDateControl = this.yearForm.get('EndDate');
    endDateControl?.disable();
    
    // Listen for StartDate changes to enable/disable EndDate
    this.yearForm.get('StartDate')?.valueChanges.subscribe(startDateValue => {
      const endDateControl = this.yearForm.get('EndDate');
      if (startDateValue && startDateValue !== '') {
        endDateControl?.enable();
      } else {
        endDateControl?.disable();
        endDateControl?.setValue(''); // Clear EndDate when StartDate is cleared
      }
    });
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
    this.submitted = false;
    this.editing = false;
    this.yearForm.reset();
    this.yearForm.markAsUntouched();
    this.yearForm.markAsPristine();
    
    // Ensure Status field is enabled for new records
    const statusControl = this.yearForm.get('Status');
    statusControl?.enable();
    
    // Disable EndDate initially since StartDate will be empty
    const endDateControl = this.yearForm.get('EndDate');
    endDateControl?.disable();
    
    // Set default status value after reset
    this.yearForm.patchValue({
      Status: 'Active'
    });
  }

  editYear(y: AcademicYear) {
    // Convert string dates to Date objects for the form
    const formData = {
      ...y,
      StartDate: y.StartDate ? new Date(y.StartDate) : null,
      EndDate: y.EndDate ? new Date(y.EndDate) : null
    };
    
    this.yearForm.patchValue(formData);
    this.editing = true;
    
    // Disable Status field if the record has Active status
    const statusControl = this.yearForm.get('Status');
    if (y.Status === 'Active') {
      statusControl?.disable();
    } else {
      statusControl?.enable();
    }
    
    // Enable/disable EndDate based on StartDate value
    const endDateControl = this.yearForm.get('EndDate');
    if (formData.StartDate) {
      endDateControl?.enable();
    } else {
      endDateControl?.disable();
    }
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
    
  // Get form values and format dates properly. Use getRawValue() to include disabled controls (e.g. Status)
  const formValue = this.yearForm.getRawValue();
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
          this.yearForm.reset();
          this.submitted = false;
          this.yearForm.markAsUntouched();
          this.yearForm.markAsPristine();
          
          // Ensure Status field is enabled after successful save
          const statusControl = this.yearForm.get('Status');
          statusControl?.enable();
        } else {
          this.msg.add({ 
            severity: 'error', 
            summary: 'Error', 
            detail: response.message?.toString() || 'Save failed', 
            life: 3000 
          });
        }
      },
      error: (err) => {
        this.msg.add({ severity: 'error', summary: 'Error', detail: err.error?.message || 'Save failed', life: 3000 });
      }
    });
  }

  cancelEdit() { 
    this.editing = false; 
    this.yearForm.reset(); 
    this.submitted = false;
    this.yearForm.markAsUntouched();
    this.yearForm.markAsPristine();
    
    // Ensure Status field is enabled when canceling
    const statusControl = this.yearForm.get('Status');
    statusControl?.enable();
    
    // Reset EndDate state - disable it since form is reset
    const endDateControl = this.yearForm.get('EndDate');
    endDateControl?.disable();
    
    // Set default status value after cancel
    this.yearForm.patchValue({
      Status: 'Active'
    });
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
    if (!startDate) return null;
    
    // If it's already a Date object, return it
    if (startDate instanceof Date) return startDate;
    
    // If it's a string, convert to Date
    if (typeof startDate === 'string') {
      const date = new Date(startDate);
      return isNaN(date.getTime()) ? null : date;
    }
    
    return null;
  }

  get isStatusDisabled(): boolean {
    const statusControl = this.yearForm.get('Status');
    return statusControl ? statusControl.disabled : false;
  }

  get isEndDateDisabled(): boolean {
    const startDateValue = this.yearForm.get('StartDate')?.value;
    return !startDateValue || startDateValue === '';
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
}

