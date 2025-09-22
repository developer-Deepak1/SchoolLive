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
import { FeeService, FeeData as ApiFeeData, FeeWithClassSections } from '../services/fee.service';
import { Classes, Section } from '../../model/classes.model';

// Interfaces
interface FeeData {
  feeId?: number;
  feeName: string;
  frequency: string;
  startDate: Date | null;
  lastDueDate: Date | null;
  amount: number;
  isActive: boolean;
  classSectionMapping?: Array<{classId: number, sectionId: number, amount?: number}>;
}

interface ClassSection {
  classId: number;
  className: string;
  sectionId: number;
  sectionName: string;
  amount: number;
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

  // Form Data
  feeData: FeeData = {
    feeName: '',
    frequency: '',
    startDate: null,
    lastDueDate: null,
    amount: 0,
    isActive: true,
    classSectionMapping: []
  };

  // Options for dropdowns
  feeTemplates = [
    {
      name: 'Admission Fee',
      frequency: 'onetime',
      amount: 5000,
      description: 'One-time admission fee for new students'
    },
    {
      name: 'Tuition Fee',
      frequency: 'monthly',
      amount: 3000,
      description: 'Monthly tuition fee'
    },
    {
      name: 'Exam Fee',
      frequency: 'yearly',
      amount: 1500,
      description: 'Annual examination fee'
    },
    {
      name: 'Library Fee',
      frequency: 'yearly',
      amount: 500,
      description: 'Annual library subscription fee'
    },
    {
      name: 'Sports Fee',
      frequency: 'yearly',
      amount: 800,
      description: 'Annual sports and activities fee'
    },
    {
      name: 'Transport Fee',
      frequency: 'monthly',
      amount: 2000,
      description: 'Monthly transportation fee'
    }
  ];

  frequencyOptions = [
    { label: 'One Time', value: 'onetime' },
    { label: 'On Demand', value: 'ondemand' },
    { label: 'Daily', value: 'daily' },
    { label: 'Weekly', value: 'weekly' },
    { label: 'Monthly', value: 'monthly' },
    { label: 'Yearly', value: 'yearly' }
  ];

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

  ngOnInit() {
    this.loadClassesAndSections();
    this.loadFees();
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
            amount: this.feeData.amount || 0,
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
          amount: this.feeData.amount || 0,
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
    this.activeFees = this.allFees.filter(fee => fee.isActive);
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
        cs.amount = this.feeData.amount || 0;
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
    if (this.feeData.amount > 0) {
      const selectedSections = this.classSections.filter(cs => cs.selected);
      if (selectedSections.length > 0) {
        selectedSections.forEach(cs => {
          cs.amount = this.feeData.amount;
        });
      }
    }
  }

  // Bulk select with amount application
  selectAllClassSectionsWithAmount() {
    this.classSections.forEach(cs => {
      cs.selected = true;
      cs.amount = this.feeData.amount || 0;
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
    if (!this.isFormValid()) {
      this.messageService.add({
        severity: 'warn',
        summary: 'Validation Error',
        detail: 'Please fill all required fields and select at least one class/section'
      });
      return;
    }

    this.isLoading = true;

    try {
      // Get selected class/section combinations
      const selectedClassSections = this.classSections.filter(cs => cs.selected);
      const classSectionMapping = selectedClassSections.map(cs => {
        const mapping: {classId: number, sectionId: number, amount?: number} = {
          classId: cs.classId,
          sectionId: cs.sectionId
        };
        
        // Include amount only if it's different from 0 and set
        if (cs.amount && cs.amount > 0) {
          mapping.amount = cs.amount;
        }
        
        return mapping;
      });

      // Convert FeeData to ApiFeeData
      const apiFeeData: ApiFeeData = {
        feeId: this.editMode ? this.editingFeeId : undefined,
        feeName: this.feeData.feeName,
        frequency: this.feeData.frequency,
        startDate: this.feeData.startDate!,
        lastDueDate: this.feeData.lastDueDate!,
        amount: this.feeData.amount,
        isActive: this.feeData.isActive,
        status: this.feeData.isActive ? 'Active' : 'Inactive',
        classSectionMapping: classSectionMapping
      };

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
    this.editMode = true;
    this.editingFeeId = fee.feeId;
    
    this.feeData = {
      feeId: fee.feeId,
      feeName: fee.feeName,
      frequency: fee.frequency,
      startDate: fee.startDate,
      lastDueDate: fee.lastDueDate,
      amount: fee.amount,
      isActive: fee.isActive
    };

    // Since we're not storing class sections separately, just keep all selected
    this.classSections.forEach(cs => {
      cs.selected = true;
      cs.amount = fee.amount;
    });

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async toggleFeeStatus(fee: FeeWithClassSections) {
    if (!fee.feeId) return;

    try {
      const newStatus = !fee.isActive;
      const success = await this.feeService.toggleFeeStatus(fee.feeId, newStatus).toPromise();
      
      if (success) {
        fee.isActive = newStatus;
        this.updateFeeArrays();
        
        this.messageService.add({
          severity: 'info',
          summary: 'Status Updated',
          detail: `Fee ${fee.isActive ? 'activated' : 'deactivated'} successfully`
        });
      } else {
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
      feeName: '',
      frequency: '',
      startDate: null,
      lastDueDate: null,
      amount: 0,
      isActive: true,
      classSectionMapping: []
    };

    this.classSections.forEach(cs => {
      cs.selected = true;
      cs.amount = 0;
    });
  }

  private isFormValid(): boolean {
    return !!(
      this.feeData.feeName &&
      this.feeData.frequency &&
      this.feeData.startDate &&
      this.feeData.lastDueDate &&
      this.feeData.amount > 0 &&
      this.getSelectedCount() > 0
    );
  }

  // Helper method to get fee name label (now just returns the name as is)
  getFeeNameLabel(value: string): string {
    return value; // Since we're using custom names now
  }

  // Helper method to get frequency label
  getFrequencyLabel(value: string): string {
    const option = this.frequencyOptions.find(opt => opt.value === value);
    return option ? option.label : value;
  }

  // Apply template to form
  applyTemplate(template: any) {
    this.feeData.feeName = template.name;
    this.feeData.frequency = template.frequency;
    this.feeData.amount = template.amount;
    
    // Set default dates if not already set
    if (!this.feeData.startDate) {
      this.feeData.startDate = new Date();
    }
    if (!this.feeData.lastDueDate) {
      // Set due date based on frequency
      const dueDate = new Date();
      switch (template.frequency) {
        case 'monthly':
          dueDate.setMonth(dueDate.getMonth() + 1);
          break;
        case 'yearly':
          dueDate.setFullYear(dueDate.getFullYear() + 1);
          break;
        case 'weekly':
          dueDate.setDate(dueDate.getDate() + 7);
          break;
        case 'daily':
          dueDate.setDate(dueDate.getDate() + 1);
          break;
        default:
          dueDate.setMonth(dueDate.getMonth() + 1);
      }
      this.feeData.lastDueDate = dueDate;
    }

    // Apply amount to all class sections and select them
    this.classSections.forEach(cs => {
      cs.amount = template.amount;
      cs.selected = true;
    });

    this.messageService.add({
      severity: 'info',
      summary: 'Template Applied',
      detail: `${template.name} template has been applied. You can modify the details as needed.`
    });
  }
}
