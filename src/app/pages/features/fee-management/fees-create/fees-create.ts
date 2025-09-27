import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

// PrimeNG Imports
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { CheckboxModule } from 'primeng/checkbox';
import { ToggleSwitchModule } from 'primeng/toggleswitch';
import { TooltipModule } from 'primeng/tooltip';
import { CardModule } from 'primeng/card';
import { TableModule } from 'primeng/table';
import { MessageService } from 'primeng/api';
import { ToastModule } from 'primeng/toast';

// Services
import { ClassesService } from '../../services/classes.service';
import { SectionsService } from '../../services/sections.service';
import { AcademicYearService } from '../../services/academic-year.service';
import { UserService } from '@/services/user.service';
import { FeeService, FeeData as ApiFeeData, FeeWithClassSections } from '../services/fee.service';
import { Classes, Section } from '../../model/classes.model';

// Local UI-only interfaces

interface ScheduleData {
  scheduleType: string;
  intervalMonths?: number;
  dayOfMonth?: number;
  // PrimeNG datepicker works with Date objects; accept either Date or ISO string
  startDate?: Date | string;
  endDate?: Date | string;
  nextDueDate?: Date | string;
  reminderDaysBefore?: number;
}

interface ClassSection {
  classId: number;
  className: string;
  sectionId: number;
  sectionName: string;
  amount: number | string;
  mappingId?: number;
  selected: boolean;
}

@Component({
  selector: 'app-fees-create',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ButtonModule,
    InputTextModule,
    InputNumberModule,
    SelectModule,
    DatePickerModule,
    CheckboxModule,
    ToggleSwitchModule,
    TooltipModule,
    CardModule,
    TableModule,
    ToastModule
  ],
  providers: [MessageService],
  templateUrl: './fees-create.html',
  styleUrl: './fees-create.scss'
})
export class FeesCreate implements OnInit {
  private classesService = inject(ClassesService);
  private sectionsService = inject(SectionsService);
  private feeService = inject(FeeService);
  private messageService = inject(MessageService);
  private router = inject(Router);
  private academicYearService = inject(AcademicYearService);
  private userService = inject(UserService);

  // Form Data (DB-native names for API)
  feeData: ApiFeeData = {
    FeeName: '',
    IsActive: true,
    Schedule: null,
    ClassSectionMapping: []
  };

  // Schedule data for new schema
  scheduleData: ScheduleData = {
    scheduleType: 'OneTime',
    reminderDaysBefore: 5
  };

  // Options for dropdowns
  scheduleTypeOptions = [
    { label: 'One Time', value: 'OneTime' },
    { label: 'On Demand', value: 'OnDemand' },
    { label: 'Recurring', value: 'Recurring' }
  ];

  // Recurring interval options (maps to IntervalMonths column)
  recurringIntervalOptions = [
    { label: 'Monthly', value: 1 },
    { label: 'Quarterly', value: 3 },
    { label: 'Half Yearly', value: 6 },
    { label: 'Yearly', value: 12 }
  ];

  // Options for schedule-specific fields
  daysOfMonth = Array.from({length: 31}, (_, i) => ({
    label: `${i+1}${this.getOrdinalSuffix(i+1)}`, value: i+1
  }));

  // Class and Section Data
  classes: Classes[] = [];
  sections: Section[] = [];
  classSections: ClassSection[] = [];

  // Fee Management
  allFees: FeeWithClassSections[] = [];
  activeFees: FeeWithClassSections[] = [];
  inactiveFees: FeeWithClassSections[] = [];
  activeTabIndex = 0;

  // UI State
  isLoading = false;
  editMode = false;
  editingFeeId?: number;
  // Human-readable validation message set by isFormValid()
  validationErrorMessage?: string | null = null;

  // Academic year bounds (used for date validation)
  academicYearStart?: Date;
  academicYearEnd?: Date;
  // Effective minimum date for pickers (tomorrow or academicYearStart whichever is later)
  effectiveMinDate?: Date;
  todayDate: Date = new Date();

  ngOnInit() {
    this.loadAcademicYearBounds();
    this.loadClassesAndSections();
    this.loadFees();
  }

  async loadAcademicYearBounds() {
    try {
      const yrs = await this.academicYearService.getAcademicYears().toPromise();
      if (!yrs || yrs.length === 0) return;

      // Prefer academic year id from logged-in user when available
      const userAcademicYearId = this.userService.getAcademicYearId();
      let chosen: any;

      if (userAcademicYearId) {
        chosen = yrs.find((y: any) => (y.AcademicYearID || (y as any).id) === userAcademicYearId);
      }

      // Fallback: find an active/current academic year (Status === 'active' or truthy)
      if (!chosen) {
        chosen = yrs.find((y: any) => (y.Status && String(y.Status).toLowerCase() === 'active') || (y as any).isCurrent);
      }

      // Last resort: pick the latest by StartDate
      if (!chosen) {
        chosen = yrs.slice().sort((a: any, b: any) => new Date(b.StartDate).getTime() - new Date(a.StartDate).getTime())[0];
      }

      if (chosen) {
        this.academicYearStart = this.parseDate(chosen.StartDate) || undefined;
        this.academicYearEnd = this.parseDate(chosen.EndDate) || undefined;
        // compute effective min date after academic year bounds load
        this.effectiveMinDate = this.computeEffectiveMinDate();
      }
    } catch (err) {
      console.error('Failed to load academic years', err);
    }
  }

  async loadClassesAndSections() {
    try {
      this.isLoading = true;
      
      // Load classes and sections in parallel
      const [classes, sections] = await Promise.all([
        this.classesService.getClasses().toPromise(),
        this.sectionsService.getSections().toPromise()
      ]);

      this.classes = classes || [];
      this.sections = sections || [];

      // Create class-section combinations
      this.createClassSectionCombinations();
    } catch (error) {
      console.error('Error loading classes and sections:', error);
      this.messageService.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to load classes and sections'
      });
    } finally {
      this.isLoading = false;
    }
  }

  createClassSectionCombinations() {
    this.classSections = [];
    
    this.classes.forEach(classItem => {
      // Handle both PascalCase (API response) and camelCase (interface) field names
      const classSections = this.sections.filter(section => {
        const sectionClassId = (section as any).ClassID || section.classId;
        return sectionClassId === classItem.ClassID;
      });
      
      if (classSections.length > 0) {
        classSections.forEach(section => {
          // Handle field name variations from API response
          const sectionId = (section as any).SectionID || section.sectionId || 0;
          const sectionName = (section as any).SectionName || section.sectionName || 'Unknown';
          
          this.classSections.push({
            classId: classItem.ClassID,
            className: classItem.ClassName,
            sectionId: sectionId,
            sectionName: sectionName,
            amount: 0,
            selected: true
          });
        });
      } else {
        // If no sections found, create a default entry
        this.classSections.push({
          classId: classItem.ClassID,
          className: classItem.ClassName,
          sectionId: 0,
          sectionName: 'Default',
          amount: 0,
          selected: true
        });
      }
    });
  }

  async loadFees() {
    try {
      this.isLoading = true;
      this.allFees = await this.feeService.getFees().toPromise() || [];
      this.updateFeeArrays();
    } catch (error) {
      console.error('Error loading fees:', error);
      this.messageService.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to load fees'
      });
      // Fallback to empty arrays
      this.allFees = [];
      this.updateFeeArrays();
    } finally {
      this.isLoading = false;
    }
  }

  updateFeeArrays() {
    this.activeFees = this.allFees.filter(fee => !!fee.IsActive);
    this.inactiveFees = this.allFees.filter(fee => !fee.IsActive);
  }

  selectAllClassSections() {
    this.classSections.forEach(cs => cs.selected = true);
  }

  deselectAllClassSections() {
    this.classSections.forEach(cs => cs.selected = false);
  }

  applyDefaultAmount() {
    this.classSections.forEach(cs => {
      if (cs.selected) {
        cs.amount = 0; // Amount will be set per class section
      }
    });
    
    this.messageService.add({
      severity: 'info',
      summary: 'Amount Applied',
      detail: 'Default amount applied to selected class sections'
    });
  }

  // Watch for amount changes and update selected sections
  onDefaultAmountChange() {
    // Only auto-apply default amount if we're not in edit mode or if user explicitly wants it
    if (!this.editMode) { // Set default amount for new fees
      const selectedSections = this.classSections.filter(cs => cs.selected);
      if (selectedSections.length > 0) {
        selectedSections.forEach(cs => {
          cs.amount = 0; // Default amount, can be changed per class
        });
      }
    }
  }

  // Bulk select with amount application
  selectAllClassSectionsWithAmount() {
    this.classSections.forEach(cs => {
      cs.selected = true;
      cs.amount = 0; // Default amount
    });
    
    this.messageService.add({
      severity: 'info',
      summary: 'All Selected',
      detail: 'All class sections selected with default amount applied'
    });
  }

  getSelectedCount(): number {
    return this.classSections.filter(cs => cs.selected).length;
  }

  areAllSelected(): boolean {
    return this.classSections.length > 0 && this.classSections.every(cs => cs.selected);
  }

  toggleAllSelection(selectAll: boolean) {
    this.classSections.forEach(cs => cs.selected = selectAll);
  }

  // Getter/setter to bind header checkbox via standalone ngModel
  get allSelected(): boolean {
    return this.areAllSelected();
  }

  set allSelected(value: boolean) {
    this.toggleAllSelection(!!value);
  }

  async onSubmit() {
    // Ensure scheduleData is synced into feeData.schedule before validation
    // Coerce numeric fields to numbers because some UI components may provide strings
    // Merge top-level startDate/endDate (bound in template) as fallback for schedule
    this.feeData.Schedule = {
      ScheduleType: this.scheduleData.scheduleType,
      IntervalMonths: this.scheduleData.intervalMonths !== undefined && this.scheduleData.intervalMonths !== null
        ? Number(this.scheduleData.intervalMonths)
        : undefined,
      DayOfMonth: this.scheduleData.dayOfMonth !== undefined && this.scheduleData.dayOfMonth !== null
        ? Number(this.scheduleData.dayOfMonth)
        : undefined,
  // Always send date-only strings (YYYY-MM-DD) to avoid timezone shifts; treat dates as IST calendar days
  StartDate: this.formatAsDateOnly(this.scheduleData.startDate),
  EndDate: this.formatAsDateOnly(this.scheduleData.endDate),
      NextDueDate: this.formatAsDateOnly(this.scheduleData.nextDueDate),
      ReminderDaysBefore: this.scheduleData.reminderDaysBefore !== undefined && this.scheduleData.reminderDaysBefore !== null
        ? Number(this.scheduleData.reminderDaysBefore)
        : undefined
    };

    if (!this.isFormValid()) {
      this.messageService.add({
        severity: 'warn',
        summary: 'Validation Error',
        detail: this.validationErrorMessage || 'Please fill all required fields and select at least one class/section'
      });
      return;
    }

    this.isLoading = true;

    try {
      // Get selected class/section combinations
      const selectedClassSections = this.classSections.filter(cs => cs.selected);
      const classSectionMapping = selectedClassSections.map(cs => {
        // Normalize amount: accept numbers, numeric strings, or currency formatted strings (e.g. "₹1,200.50")
        const raw: number | string | null = (cs.amount !== undefined && cs.amount !== null) ? cs.amount as any : null;
        let amount: number | null = null;

        if (raw !== null && raw !== undefined && raw !== '') {
          if (typeof raw === 'number') {
            amount = raw;
          } else if (typeof raw === 'string') {
            // Strip anything that's not digit, dot, or minus sign
            const cleaned = raw.replace(/[^0-9.\-]/g, '');
            const parsed = cleaned === '' ? NaN : Number(cleaned);
            amount = Number.isNaN(parsed) ? null : parsed;
          } else {
            // Fallback for other types
            const parsed = Number(raw as any);
            amount = Number.isNaN(parsed) ? null : parsed;
          }
        }

        // When amount is null, backend expects null (not 0) for unspecified amounts; but keep 0 if user explicitly set 0
        // Build mapping payload using DB-native keys
        const mapping: any = {
          ClassID: cs.classId,
          SectionID: cs.sectionId,
          Amount: amount
        };

        if ((cs as any).mappingId) mapping.MappingID = (cs as any).mappingId;

        return mapping;
      });

      // Convert FeeData to ApiFeeData
      const apiFeeData: ApiFeeData = {
        FeeID: this.editMode ? this.editingFeeId : undefined,
        FeeName: this.feeData.FeeName,
        IsActive: this.feeData.IsActive,
        ClassSectionMapping: classSectionMapping,
        Schedule: this.feeData.Schedule
      };

      // Ensure OnDemand explicitly sends IntervalMonths and DayOfMonth as null (DB expects null)
      if (apiFeeData.Schedule && apiFeeData.Schedule.ScheduleType === 'OnDemand') {
        (apiFeeData.Schedule as any).IntervalMonths = null;
        (apiFeeData.Schedule as any).DayOfMonth = null;
      }

      // For OneTime also clear recurrence fields
      if (apiFeeData.Schedule && apiFeeData.Schedule.ScheduleType === 'OneTime') {
        (apiFeeData.Schedule as any).IntervalMonths = null;
        (apiFeeData.Schedule as any).DayOfMonth = null;
      }

      // For Recurring schedules explicitly set StartDate/EndDate to null (DB expects null when not applicable)
      if (apiFeeData.Schedule && apiFeeData.Schedule.ScheduleType === 'Recurring') {
        (apiFeeData.Schedule as any).StartDate = null;
        (apiFeeData.Schedule as any).EndDate = null;
      }

  // Include startDate/endDate only when relevant (already formatted as YYYY-MM-DD)
  // No extra top-level dates; all dates are within Schedule using DB-native names

      let result: FeeWithClassSections | null;

      if (this.editMode && this.editingFeeId) {
        result = await this.feeService.updateFee(apiFeeData).toPromise() || null;
      } else {
        result = await this.feeService.createFee(apiFeeData).toPromise() || null;
      }

      if (result) {
        // Reload fees to get updated data
        await this.loadFees();
        this.resetForm();

        this.messageService.add({
          severity: 'success',
          summary: 'Success',
          detail: `Fee ${this.editMode ? 'updated' : 'created'} successfully`
        });
      } else {
        throw new Error('Failed to save fee');
      }

    } catch (error) {
      console.error('Error saving fee:', error);
      this.messageService.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to save fee'
      });
    } finally {
      this.isLoading = false;
    }
  }

  editFee(fee: FeeWithClassSections) {
    if (!fee.FeeID) return;

    this.editMode = true;
    this.editingFeeId = fee.FeeID;
    
    // Use the fee data that's already available (no need for additional API call)
    this.feeData = {
      FeeID: fee.FeeID,
      FeeName: fee.FeeName,
      IsActive: !!fee.IsActive,
      Schedule: fee.Schedule ?? null,
      ClassSectionMapping: fee.ClassSectionMapping ?? []
    };

    // Populate local scheduleData so the template controls for Recurring/OnDemand show current values
    const sched = fee.Schedule || {} as any;
    this.scheduleData = {
      scheduleType: sched.ScheduleType ?? sched.scheduleType ?? 'OneTime',
      intervalMonths: sched.IntervalMonths !== undefined && sched.IntervalMonths !== null ? Number(sched.IntervalMonths) : undefined,
      dayOfMonth: sched.DayOfMonth !== undefined && sched.DayOfMonth !== null ? Number(sched.DayOfMonth) : undefined,
      startDate: this.parseDate(sched.StartDate ?? sched.startDate),
      endDate: this.parseDate(sched.EndDate ?? sched.endDate),
      nextDueDate: this.parseDate(sched.NextDueDate ?? sched.nextDueDate),
      reminderDaysBefore: sched.ReminderDaysBefore !== undefined && sched.ReminderDaysBefore !== null ? Number(sched.ReminderDaysBefore) : 5
    };

    // Reset all class sections to unselected first
    this.classSections.forEach(cs => {
      cs.selected = false;
      cs.amount = 0;
    });

    // If fee has classSections data, map the amounts correctly using effectiveAmount
    if (fee.ClassSectionMapping && fee.ClassSectionMapping.length > 0) {
      fee.ClassSectionMapping.forEach(feeClassSection => {
        const matchingCs = this.classSections.find(cs => 
          cs.classId === feeClassSection.ClassID && cs.sectionId === feeClassSection.SectionID
        );
        if (matchingCs) {
          matchingCs.selected = true;
          matchingCs.amount = Number(feeClassSection.Amount || 0);
          // Preserve mapping id so updates can reference existing DB rows
          (matchingCs as any).mappingId = (feeClassSection as any).MappingID ?? (feeClassSection as any).mappingId ?? undefined;
        }
      });
    } else {
      // Fallback: select all and use base amount
      this.classSections.forEach(cs => {
        cs.selected = true;
        cs.amount = 0; // Will be set from class section mapping
      });
    }

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async toggleFeeStatus(fee: FeeWithClassSections, event?: any) {
    if (!fee.FeeID) return;

    try {
      // The toggle component emits an event with the new checked state. Prefer that when available
      const previousStatus = !!fee.IsActive;
      let intendedStatus: boolean;

      if (event && (typeof event.checked === 'boolean' || typeof event.value === 'boolean')) {
        // PrimeNG ToggleSwitch may use `checked` or `value`
        intendedStatus = typeof event.checked === 'boolean' ? event.checked : event.value;
      } else {
        // Fallback: invert the model (should not be necessary when event is passed)
        intendedStatus = !previousStatus;
      }

      // Optimistically update UI so toggle feels responsive
      fee.IsActive = intendedStatus;
      this.updateFeeArrays();

      const success = await this.feeService.toggleFeeStatus(fee.FeeID, intendedStatus).toPromise();

      if (success) {
        this.messageService.add({
          severity: 'info',
          summary: 'Status Updated',
          detail: `Fee ${intendedStatus ? 'activated' : 'deactivated'} successfully`
        });
      } else {
        // Revert UI to previous state if API failed
        fee.IsActive = previousStatus;
        this.updateFeeArrays();
        throw new Error('Failed to update fee status');
      }
    } catch (error) {
      console.error('Error updating fee status:', error);
      this.messageService.add({
        severity: 'error',
        summary: 'Error',
        detail: 'Failed to update fee status'
      });
    }
  }

  onCancel() {
    this.resetForm();
  }

  resetForm() {
    this.editMode = false;
    this.editingFeeId = undefined;
    
    this.feeData = {
      FeeName: '',
      IsActive: true,
      Schedule: null,
      ClassSectionMapping: []
    };

    // Reset schedule UI state to defaults
    this.scheduleData = {
      scheduleType: 'OneTime',
      intervalMonths: undefined,
      dayOfMonth: undefined,
      startDate: undefined,
      endDate: undefined,
      nextDueDate: undefined,
      reminderDaysBefore: 5
    };

    // Recompute effective min date (tomorrow or academic year start)
    this.effectiveMinDate = this.computeEffectiveMinDate();

    // Reset class-section selections and amounts
    this.classSections.forEach(cs => {
      cs.selected = true;
      cs.amount = 0;
    });
  }

  private isFormValid(): boolean {
    // Reset message
    this.validationErrorMessage = null;

    // Basic validation
    if (!this.feeData.FeeName || !this.scheduleData.scheduleType || this.getSelectedCount() === 0) {
      console.debug('Validation failed: basic checks', {
        feeName: this.feeData.FeeName,
        scheduleType: this.scheduleData.scheduleType,
        selectedCount: this.getSelectedCount()
      });
      if (!this.feeData.FeeName) this.validationErrorMessage = 'Please enter a fee name.';
      else if (!this.scheduleData.scheduleType) this.validationErrorMessage = 'Please select a schedule type.';
      else if (this.getSelectedCount() === 0) this.validationErrorMessage = 'Please select at least one class/section.';
      return false;
    }

    // Frequency-specific validation
    switch (this.scheduleData.scheduleType) {
      case 'OnDemand':
        // Prefer scheduleData if available
        const sdOn = this.scheduleData;
        const okOn = !!(sdOn.startDate && sdOn.endDate);
        if (!okOn) {
          console.debug('Validation failed: OnDemand needs startDate and endDate', sdOn);
          this.validationErrorMessage = 'Please provide both Start Date and End/Due Date for On Demand fees.';
          return false;
        }

        // If academic bounds are available, ensure dates lie within them
        if (this.academicYearStart && this.academicYearEnd) {
          const s = this.parseDate(sdOn.startDate);
          const e = this.parseDate(sdOn.endDate);
          if (!s || !e) {
            this.validationErrorMessage = 'Invalid Start Date or End/Due Date.';
            return false;
          }
          if (!this.isWithinAcademicYear(s) || !this.isWithinAcademicYear(e)) {
            console.debug('Validation failed: OnDemand dates outside academic year', { s, e, ayStart: this.academicYearStart, ayEnd: this.academicYearEnd });
            return false;
          }
          // Also ensure start <= end
          if (s.getTime() > e.getTime()) {
            console.debug('Validation failed: OnDemand start is after end', { s, e });
            this.validationErrorMessage = 'Start Date cannot be after End/Due Date.';
            return false;
          }
        }

        return true;
      case 'OneTime':
        // OneTime no longer requires an end date; basic existence of feeName and selection suffices
        // If an endDate or nextDueDate is provided, ensure it's within academic year
        if (this.academicYearStart && this.academicYearEnd) {
          const ed = this.parseDate(this.scheduleData.endDate);
          const nd = this.parseDate(this.scheduleData.nextDueDate);
          if (ed && !this.isWithinAcademicYear(ed)) return false;
          if (nd && !this.isWithinAcademicYear(nd)) return false;
        }
        return true;
      case 'Recurring':
        // Accept either feeData.schedule or the local scheduleData as the source of truth
      const sd = this.scheduleData;
      const day = sd.dayOfMonth !== undefined && sd.dayOfMonth !== null ? Number(sd.dayOfMonth) : NaN;
      const interval = sd.intervalMonths !== undefined && sd.intervalMonths !== null ? Number(sd.intervalMonths) : NaN;
      const dayOk = !Number.isNaN(day) && day > 0 && day <= 31;
      const intervalOk = !Number.isNaN(interval) && [1,3,6,12].includes(interval);
      const okRec = dayOk && intervalOk;
      if (!okRec) console.debug('Validation failed: Recurring needs valid dayOfMonth and intervalMonths', { sd, dayOk, intervalOk, day, interval });
      return okRec;
      default:
        return true;
    }
  }

  private isWithinAcademicYear(d: Date): boolean {
    if (!d) return false;
    if (this.academicYearStart && d < this.academicYearStart) return false;
    if (this.academicYearEnd && d > this.academicYearEnd) return false;
    return true;
  }

  // Helper method to get fee name label (now just returns the name as is)
  getFeeNameLabel(value?: string): string {
    return value ?? '';
  }

  // Helper method to get schedule type label (accept undefined safely)
  getFrequencyLabel(value?: string): string {
    if (!value) return '';
    const option = this.scheduleTypeOptions.find((opt: any) => opt.value === value);
    return option ? option.label : value;
  }

  // Helper methods for schedule-specific functionality
  getOrdinalSuffix(n: number): string {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return s[(v - 20) % 10] || s[v] || s[0];
  }

  // Parse various incoming date formats into a Date object or undefined
  private parseDate(val: any): Date | undefined {
    if (val === null || val === undefined || val === '') return undefined;
    if (val instanceof Date) return val;
    if (typeof val === 'number') return new Date(val);
    if (typeof val === 'string') {
      // If it's a pure date-only string (YYYY-MM-DD), construct as local date to avoid UTC shift
      const m = /^\d{4}-\d{2}-\d{2}$/.exec(val);
      if (m) {
        const [y, mo, d] = val.split('-').map(Number);
        // Create as local date
        return new Date(y, (mo - 1), d);
      }
      const d1 = new Date(val);
      if (!isNaN(d1.getTime())) return d1;
      // Try adding time to date-only strings
      const maybe = new Date(val + 'T00:00:00');
      if (!isNaN(maybe.getTime())) return maybe;
    }
    return undefined;
  }

  // Format Date or string as date-only (YYYY-MM-DD) using local calendar day (assumed IST usage)
  private formatAsDateOnly(val: Date | string | undefined): string | undefined {
    if (!val) return undefined;
    const d = this.parseDate(val);
    if (!d) return undefined;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  // Determine whether a fee is editable: allow edit when StartDate is not in the future.
  // Returns true when fee has no StartDate, or StartDate <= today (local calendar day).
  public canEditFee(fee: FeeWithClassSections | undefined | null): boolean {
    if (!fee) return false;
    try {
      const sd = this.parseDate((fee as any)?.Schedule?.StartDate);
      if (!sd) return false; // no start date => editable
      const today = new Date();
      today.setHours(0,0,0,0);
      sd.setHours(0,0,0,0);
      return sd.getTime() <= today.getTime();
    } catch (e) {
      return true;
    }
  }

  shouldShowStartDate(): boolean {
    return this.scheduleData.scheduleType === 'OnDemand';
  }

  shouldShowLastDueDate(): boolean {
    return ['OnDemand', 'OneTime'].includes(this.scheduleData.scheduleType || '');
  }

  shouldShowMonthlySettings(): boolean {
    return this.scheduleData.scheduleType === 'Recurring';
  }

  shouldShowScheduleSettings(): boolean {
    return this.scheduleData.scheduleType === 'Recurring';
  }

  onFrequencyChange() {
    // Normalize schedule type and reminder default
    this.scheduleData.scheduleType = this.scheduleData.scheduleType || 'OneTime';
    if (this.scheduleData.reminderDaysBefore === undefined || this.scheduleData.reminderDaysBefore === null) {
      this.scheduleData.reminderDaysBefore = 5;
    }

    const tomorrow = this.getTomorrowDate();
    const ayEnd = this.academicYearEnd;

    // Handle each schedule type in a single place
    switch (this.scheduleData.scheduleType) {
      case 'Recurring':
        // sensible defaults for recurrence
        if (this.scheduleData.dayOfMonth === undefined || this.scheduleData.dayOfMonth === null) this.scheduleData.dayOfMonth = 1;
        if (this.scheduleData.intervalMonths === undefined || this.scheduleData.intervalMonths === null) this.scheduleData.intervalMonths = 1;

        // For new Recurring fees default start/end to tomorrow (user may change or clear them).
        if (!this.editMode) {
          if (!this.scheduleData.startDate) this.scheduleData.startDate = ayEnd && tomorrow > ayEnd ? new Date(ayEnd.getTime()) : tomorrow;
          if (!this.scheduleData.endDate) this.scheduleData.endDate = ayEnd && tomorrow > ayEnd ? new Date(ayEnd.getTime()) : tomorrow;
        }
        break;

      case 'OneTime':
        // Default end date 30 days ahead when absent
        if (!this.scheduleData.endDate) {
          const due = new Date(tomorrow.getTime());
          due.setDate(due.getDate() + 30);
          this.scheduleData.endDate = ayEnd && due > ayEnd ? new Date(ayEnd.getTime()) : due;
        }
        // Clear recurrence fields
        this.scheduleData.intervalMonths = undefined;
        this.scheduleData.dayOfMonth = undefined;
        break;

      case 'OnDemand':
        // Require startDate and endDate for OnDemand; default to tomorrow (start) and same day (end)
        if (!this.scheduleData.startDate) this.scheduleData.startDate = tomorrow;
        if (!this.scheduleData.endDate) this.scheduleData.endDate = this.parseDate(this.scheduleData.startDate) || tomorrow;
        // Clear recurrence fields
        this.scheduleData.intervalMonths = undefined;
        this.scheduleData.dayOfMonth = undefined;
        break;

      default:
        // fallback: clear recurrence fields
        this.scheduleData.intervalMonths = undefined;
        this.scheduleData.dayOfMonth = undefined;
        break;
    }

    // Clamp dates to academic year end and ensure start <= end
    const sd = this.parseDate(this.scheduleData.startDate);
    const ed = this.parseDate(this.scheduleData.endDate);
    if (sd && ayEnd && sd > ayEnd) this.scheduleData.startDate = new Date(ayEnd.getTime());
    if (ed && ayEnd && ed > ayEnd) this.scheduleData.endDate = new Date(ayEnd.getTime());
    const newSd = this.parseDate(this.scheduleData.startDate);
    const newEd = this.parseDate(this.scheduleData.endDate);
    if (newSd && newEd && newSd.getTime() > newEd.getTime()) {
      // Make endDate at least startDate
      this.scheduleData.endDate = new Date(newSd.getTime());
    }
  }

  // Return a Date object for tomorrow (local calendar day)
  private getTomorrowDate(): Date {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(0,0,0,0);
    return d;
  }

  // Compute effective min date for pickers: max(academicYearStart, tomorrow)
  private computeEffectiveMinDate(): Date {
    const tomorrow = this.getTomorrowDate();
    if (this.academicYearStart) {
      // ensure academicYearStart has no time component
      const ay = new Date(this.academicYearStart.getFullYear(), this.academicYearStart.getMonth(), this.academicYearStart.getDate());
      return ay > tomorrow ? ay : tomorrow;
    }
    return tomorrow;
  }
}
