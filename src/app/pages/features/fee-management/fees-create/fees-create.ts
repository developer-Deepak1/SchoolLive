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
import { FeeService } from '../services/fee.service';
import {
  ClassSectionMapping,
  Fee,
  FeeRequestPayload,
  FeeSchedule,
  ScheduleType
} from '../services/fee-management.models';
import { Classes, Section } from '../../model/classes.model';

// Local UI-only interfaces

interface ScheduleData {
  scheduleType: ScheduleType;
  intervalMonths?: number | null;
  dayOfMonth?: number | null;
  startDate?: Date | string | null;
  endDate?: Date | string | null;
  nextDueDate?: Date | string | null;
  reminderDaysBefore?: number | null;
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

interface FeeDraft {
  feeId?: number;
  feeName: string;
  isActive: boolean;
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

  // Form Data aligned with new fee management models
  feeDraft: FeeDraft = {
    feeName: '',
    isActive: true
  };

  // Schedule data for new schema
  scheduleData: ScheduleData = {
    scheduleType: 'OneTime',
    reminderDaysBefore: 5,
    intervalMonths: null,
    dayOfMonth: null,
    startDate: null,
    endDate: null,
    nextDueDate: null
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
  allFees: Fee[] = [];
  activeFees: Fee[] = [];
  inactiveFees: Fee[] = [];
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
    this.activeFees = this.allFees.filter(fee => !!fee.isActive);
    this.inactiveFees = this.allFees.filter(fee => !fee.isActive);
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
    const schedule = this.buildSchedulePayload();

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
      const payload: FeeRequestPayload = {
        feeName: this.feeDraft.feeName.trim(),
        isActive: this.feeDraft.isActive,
        schedule,
        classSectionMappings: this.buildClassSectionMappings()
      };

      let result: Fee | null;

      if (this.editMode && this.editingFeeId) {
        result = (await this.feeService.updateFee(this.editingFeeId, payload).toPromise()) ?? null;
      } else {
        result = (await this.feeService.createFee(payload).toPromise()) ?? null;
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

  editFee(fee: Fee) {
    if (!fee.feeId) return;

    this.editMode = true;
    this.editingFeeId = fee.feeId;
    
    this.feeDraft = {
      feeId: fee.feeId,
      feeName: fee.feeName,
      isActive: fee.isActive
    };

    const sched = fee.schedule ?? null;
    this.scheduleData = {
      scheduleType: sched?.scheduleType ?? 'OneTime',
      intervalMonths: sched?.intervalMonths ?? null,
      dayOfMonth: sched?.dayOfMonth ?? null,
      startDate: this.parseDate(sched?.startDate ?? undefined) ?? null,
      endDate: this.parseDate(sched?.endDate ?? undefined) ?? null,
      nextDueDate: this.parseDate(sched?.nextDueDate ?? undefined) ?? null,
      reminderDaysBefore: sched?.reminderDaysBefore ?? 5
    };

    // Reset all class sections to unselected first
    this.classSections.forEach(cs => {
      cs.selected = false;
      cs.amount = 0;
      cs.mappingId = undefined;
    });

    // If fee has classSections data, map the amounts correctly using effectiveAmount
    if (fee.classSectionMappings && fee.classSectionMappings.length > 0) {
      fee.classSectionMappings.forEach(feeClassSection => {
        const matchingCs = this.classSections.find(cs => 
          cs.classId === feeClassSection.classId && cs.sectionId === feeClassSection.sectionId
        );
        if (matchingCs) {
          // Respect the mapping's IsActive flag: only mark selected when active
          matchingCs.selected = !!feeClassSection.isActive;
          matchingCs.amount = Number(feeClassSection.amount ?? 0);
          matchingCs.mappingId = feeClassSection.mappingId ?? undefined;
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

  async toggleFeeStatus(fee: Fee, event?: any) {
    if (!fee.feeId) return;

    try {
      // The toggle component emits an event with the new checked state. Prefer that when available
      const previousStatus = !!fee.isActive;
      let intendedStatus: boolean;

      if (event && (typeof event.checked === 'boolean' || typeof event.value === 'boolean')) {
        // PrimeNG ToggleSwitch may use `checked` or `value`
        intendedStatus = typeof event.checked === 'boolean' ? event.checked : event.value;
      } else {
        // Fallback: invert the model (should not be necessary when event is passed)
        intendedStatus = !previousStatus;
      }

      // Optimistically update UI so toggle feels responsive
      fee.isActive = intendedStatus;
      this.updateFeeArrays();

      const updated = await this.feeService.toggleFeeStatus(fee.feeId, intendedStatus).toPromise();

      if (updated) {
        fee.isActive = updated.isActive;
        this.messageService.add({
          severity: 'info',
          summary: 'Status Updated',
          detail: `Fee ${intendedStatus ? 'activated' : 'deactivated'} successfully`
        });
      } else {
        // Revert UI to previous state if API failed
        fee.isActive = previousStatus;
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
    
    this.feeDraft = {
      feeName: '',
      isActive: true
    };

    // Reset schedule UI state to defaults
    this.scheduleData = {
      scheduleType: 'OneTime',
      intervalMonths: null,
      dayOfMonth: null,
      startDate: null,
      endDate: null,
      nextDueDate: null,
      reminderDaysBefore: 5
    };

    // Recompute effective min date (tomorrow or academic year start)
    this.effectiveMinDate = this.computeEffectiveMinDate();

    // Reset class-section selections and amounts
    this.classSections.forEach(cs => {
      cs.selected = true;
      cs.amount = 0;
      cs.mappingId = undefined;
    });
  }

  private buildSchedulePayload(): FeeSchedule | null {
    const type = this.scheduleData.scheduleType;
    if (!type) {
      return null;
    }

    const toNumberOrNull = (value: number | string | null | undefined): number | null => {
      if (value === null || value === undefined || value === '') {
        return null;
      }
      const parsed = Number(value);
      return Number.isNaN(parsed) ? null : parsed;
    };

    const schedule: FeeSchedule = {
      scheduleType: type,
      intervalMonths: toNumberOrNull(this.scheduleData.intervalMonths ?? null),
      dayOfMonth: toNumberOrNull(this.scheduleData.dayOfMonth ?? null),
      startDate: this.formatAsDateOnly(this.scheduleData.startDate),
      endDate: this.formatAsDateOnly(this.scheduleData.endDate),
      nextDueDate: this.formatAsDateOnly(this.scheduleData.nextDueDate),
      reminderDaysBefore: toNumberOrNull(this.scheduleData.reminderDaysBefore ?? null)
    };

    switch (type) {
      case 'OnDemand':
        schedule.intervalMonths = null;
        schedule.dayOfMonth = null;
        // OnDemand fees should not send reminder days
        schedule.reminderDaysBefore = null;
        break;
      case 'OneTime':
        schedule.intervalMonths = null;
        schedule.dayOfMonth = null;
        // OneTime fees do not use reminders, ensure API receives null
        schedule.reminderDaysBefore = null;
        break;
      case 'Recurring':
        schedule.startDate = null;
        schedule.endDate = null;
        break;
      default:
        break;
    }

    return schedule;
  }

  private buildClassSectionMappings(): ClassSectionMapping[] {
    const selectedClassSections = this.classSections.filter(cs => cs.selected);
    return selectedClassSections.map(cs => ({
      mappingId: cs.mappingId ?? undefined,
      feeId: this.editingFeeId,
      classId: cs.classId,
      sectionId: cs.sectionId,
      amount: this.normaliseAmount(cs.amount),
      isActive: true
    }));
  }

  private normaliseAmount(raw: number | string | null | undefined): number | null {
    if (raw === null || raw === undefined || raw === '') {
      return null;
    }
    if (typeof raw === 'number') {
      return Number.isNaN(raw) ? null : raw;
    }
    if (typeof raw === 'string') {
      const cleaned = raw.replace(/[^0-9.\-]/g, '');
      if (!cleaned) {
        return null;
      }
      const parsed = Number(cleaned);
      return Number.isNaN(parsed) ? null : parsed;
    }
    const parsed = Number(raw as any);
    return Number.isNaN(parsed) ? null : parsed;
  }

  private isFormValid(): boolean {
    this.validationErrorMessage = null;

  const feeName = this.feeDraft.feeName?.trim() ?? '';
    if (!feeName || !this.scheduleData.scheduleType || this.getSelectedCount() === 0) {
      console.debug('Validation failed: basic checks', {
        feeName,
        scheduleType: this.scheduleData.scheduleType,
        selectedCount: this.getSelectedCount()
      });
      if (!feeName) this.validationErrorMessage = 'Please enter a fee name.';
      else if (!this.scheduleData.scheduleType) this.validationErrorMessage = 'Please select a schedule type.';
      else if (this.getSelectedCount() === 0) this.validationErrorMessage = 'Please select at least one class/section.';
      return false;
    }

    switch (this.scheduleData.scheduleType) {
      case 'OnDemand': {
        const start = this.parseDate(this.scheduleData.startDate);
        const end = this.parseDate(this.scheduleData.endDate);
        if (!start || !end) {
          this.validationErrorMessage = 'Please provide both Start Date and End/Due Date for On Demand fees.';
          return false;
        }
        if (this.academicYearStart && this.academicYearEnd) {
          if (!this.isWithinAcademicYear(start) || !this.isWithinAcademicYear(end)) {
            this.validationErrorMessage = 'Dates must fall within the academic year bounds.';
            return false;
          }
        }
        if (start.getTime() > end.getTime()) {
          this.validationErrorMessage = 'Start Date cannot be after End/Due Date.';
          return false;
        }
        return true;
      }
      case 'OneTime': {
        if (this.academicYearStart && this.academicYearEnd) {
          const endDate = this.parseDate(this.scheduleData.endDate);
          const nextDue = this.parseDate(this.scheduleData.nextDueDate);
          if (endDate && !this.isWithinAcademicYear(endDate)) {
            this.validationErrorMessage = 'End date must be within the academic year.';
            return false;
          }
          if (nextDue && !this.isWithinAcademicYear(nextDue)) {
            this.validationErrorMessage = 'Next due date must be within the academic year.';
            return false;
          }
        }
        return true;
      }
      case 'Recurring': {
        const day = this.scheduleData.dayOfMonth ?? null;
        const interval = this.scheduleData.intervalMonths ?? null;
        const dayValue = day === null ? NaN : Number(day);
        const intervalValue = interval === null ? NaN : Number(interval);
        const dayOk = !Number.isNaN(dayValue) && dayValue > 0 && dayValue <= 31;
        const intervalOk = !Number.isNaN(intervalValue) && [1, 3, 6, 12].includes(intervalValue);
        if (!dayOk || !intervalOk) {
          this.validationErrorMessage = 'Recurring fees require a valid day of month and interval.';
          console.debug('Validation failed: Recurring needs valid dayOfMonth and intervalMonths', {
            scheduleData: this.scheduleData,
            dayOk,
            intervalOk
          });
          return false;
        }
        return true;
      }
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
  private formatAsDateOnly(val: Date | string | null | undefined): string | null {
    if (!val) return null;
    const d = this.parseDate(val);
    if (!d) return null;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }

  // Determine whether a fee is editable: allow edit when StartDate is not in the future.
  // Returns true when fee has no StartDate, or StartDate <= today (local calendar day).
  public canEditFee(fee: Fee | undefined | null): boolean {
    if (!fee) return false;
    try {
      const sd = this.parseDate(fee.schedule?.startDate ?? undefined);
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
        // Clear reminders for OnDemand in the UI
        this.scheduleData.reminderDaysBefore = null;
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
