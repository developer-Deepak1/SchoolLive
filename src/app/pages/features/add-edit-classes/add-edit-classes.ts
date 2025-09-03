import { CommonModule } from '@angular/common';
import { Component, OnInit, ViewChild, signal } from '@angular/core';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ConfirmationService, MessageService } from 'primeng/api';
import { StudentsService } from '../services/students.service';
import { SectionsService } from '../services/sections.service';
import { UserService } from '@/services/user.service';
import { HttpClient, HttpClientModule } from '@angular/common/http';
import { environment } from '../../../../environments/environment';
import { Table, TableModule } from 'primeng/table';
import { Classes, StreamType } from '../model/classes.model';
import { ClassesService } from '../services/classes.service';
import { ButtonModule } from 'primeng/button';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DialogModule } from 'primeng/dialog';
import { IconFieldModule } from 'primeng/iconfield';
import { InputIconModule } from 'primeng/inputicon';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { RadioButtonModule } from 'primeng/radiobutton';
import { RatingModule } from 'primeng/rating';
import { RippleModule } from 'primeng/ripple';
import { SelectModule } from 'primeng/select';
import { TagModule } from 'primeng/tag';
import { TextareaModule } from 'primeng/textarea';
import { ToastModule } from 'primeng/toast';
import { ToolbarModule } from 'primeng/toolbar';

interface Column {
    field: string;
    header: string;
    customExportHeader?: string;
}

interface ExportColumn {
    title: string;
    dataKey: string;
}
@Component({
    selector: 'app-add-edit-classes',
    templateUrl: './add-edit-classes.html',
    styleUrl: './add-edit-classes.scss',
    standalone: true,
    imports: [
        CommonModule,
        ReactiveFormsModule,
        FormsModule,
        TableModule,
        ButtonModule,
        RippleModule,
        ToastModule,
        ToolbarModule,
    InputTextModule,
    SelectModule,
    InputNumberModule,
        DialogModule,
        ConfirmDialogModule,
        InputIconModule,
        IconFieldModule,
        InputTextModule
    ],
    providers: [MessageService, ClassesService, ConfirmationService]
})
export class AddEditClasses implements OnInit {
    classForm!: FormGroup;
    classDialog: boolean = false;
    // View sections for a specific class
    viewSectionsDialog: boolean = false;
    selectedClassForView: Classes | null = null;
    classes = signal<Classes[]>([]);
    // Sections
    sections = signal<any[]>([]);
    sectionForm!: FormGroup;
    sectionDialog: boolean = false;
    sectionSubmitted: boolean = false;
    selectedClasses!: Classes[] | null;
    submitted: boolean = false;

    @ViewChild('dt') dt!: Table;

    cols: Column[] = [
        { field: 'ClassName', header: 'Class Name' },
    { field: 'SectionCount', header: 'Sections' },
        { field: 'ClassCode', header: 'Class Code' },
        { field: 'Stream', header: 'Stream' },
        { field: 'MaxStrength', header: 'Max Strength' }
    ];

    streamOptions = [
        { label: StreamType.SCIENCE, value: StreamType.SCIENCE },
        { label: StreamType.COMMERCE, value: StreamType.COMMERCE },
        { label: StreamType.ARTS, value: StreamType.ARTS },
        { label: StreamType.NONE, value: StreamType.NONE }
    ];

    constructor(
        private fb: FormBuilder,
        private classesService: ClassesService,
        private messageService: MessageService,
        private confirmationService: ConfirmationService,
    private studentsService: StudentsService,
    private sectionsService: SectionsService,
    private userService: UserService,
    private http: HttpClient
    ) {
        this.initForm();
        this.initSectionForm();
    }

    private initForm() {
        this.classForm = this.fb.group({
            ClassID: [null],
            ClassName: ['', [Validators.required]],
            ClassCode: ['', [Validators.required]],
            Stream: [StreamType.NONE, [Validators.required]],
            MaxStrength: [null, [Validators.required, Validators.min(1)]]
        });
    }

    private initSectionForm() {
        this.sectionForm = this.fb.group({
            SectionID: [null],
            SectionName: ['', [Validators.required]],
            MaxStrength: [null, [Validators.required, Validators.min(1)]],
            ClassID: [null, [Validators.required]],
            SchoolID: [null],
            AcademicYearID: [null]
        });
    }

    // Section handlers
    // Load all sections (no class filter) and attach ClassName when missing
    loadSections() {
    // Use SectionsService to fetch sections for current user's school & academic year; attach ClassName when missing
    const filters: any = {};
    const schoolId = this.userService.getSchoolId();
    const ay = this.userService.getAcademicYearId();
    if (schoolId) filters.school_id = schoolId;
    if (ay) filters.academic_year_id = ay;
    this.sectionsService.getSections(filters).subscribe({
            next: (data) => {
                const classesMap = new Map<number, string>(this.classes().map(c => [c.ClassID as number, c.ClassName]));
                const mapped = (data || []).map((s: any) => ({ ...s, ClassName: s.ClassName || classesMap.get(s.ClassID) || '' }));
                this.sections.set(mapped);
                // After sections loaded, merge section counts into classes
                this.mergeSectionCounts();
            },
            error: () => { this.sections.set([]); this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to load sections', life: 3000 }); }
        });
    }

    openNewSection() {
        this.sectionSubmitted = false;
    this.sectionForm.reset();
    // ensure Class select is enabled for creating a new section
    this.sectionForm.get('ClassID')?.enable();
    // default to first class if available
    const firstClass = this.classes()[0];
    if (firstClass) this.sectionForm.patchValue({ ClassID: firstClass.ClassID });
    // set defaults for school and academic year from current user
    const schoolId = this.userService.getSchoolId();
    const ay = this.userService.getAcademicYearId();
    if (schoolId) this.sectionForm.patchValue({ SchoolID: schoolId });
    if (ay) this.sectionForm.patchValue({ AcademicYearID: ay });
    this.sectionDialog = true;
    }

    editSection(section: any) {
    // ensure form has SchoolID and AcademicYearID set when editing
    this.sectionForm.patchValue(section);
    if (section.ClassID) this.sectionForm.patchValue({ ClassID: section.ClassID });
    if (section.SchoolID) this.sectionForm.patchValue({ SchoolID: section.SchoolID });
    if (section.AcademicYearID) this.sectionForm.patchValue({ AcademicYearID: section.AcademicYearID });
    // disable changing the Class when editing an existing section
    this.sectionForm.get('ClassID')?.disable();
    this.sectionDialog = true;
    }

    saveSection() {
        this.sectionSubmitted = true;
        if (this.sectionForm.invalid) {
            this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Please fill required fields.', life: 3000 });
            return;
        }
    // include disabled controls (ClassID when editing) by using getRawValue()
    const formValue = this.sectionForm.getRawValue();
        const payload: any = {
            SectionName: formValue.SectionName,
            MaxStrength: formValue.MaxStrength || null,
            ClassID: formValue.ClassID
        };
    // include AcademicYearID and SchoolID (use user defaults when missing)
    payload.AcademicYearID = formValue.AcademicYearID || this.userService.getAcademicYearId();
    payload.SchoolID = formValue.SchoolID || this.userService.getSchoolId();
        if (formValue.SectionID) {
            this.sectionsService.updateSection(formValue.SectionID, payload).subscribe({
                next: () => { this.messageService.add({ severity: 'success', summary: 'Updated', detail: 'Section updated', life: 3000 }); this.loadSections(); },
                error: () => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to update section', life: 3000 })
            });
        } else {
            this.sectionsService.createSection(payload).subscribe({
                next: () => { this.messageService.add({ severity: 'success', summary: 'Created', detail: 'Section created', life: 3000 }); this.loadSections(); },
                error: () => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to create section', life: 3000 })
            });
        }
        this.sectionDialog = false;
        this.sectionForm.reset();
        // re-enable Class select after dialog closes so it's ready for next create
        this.sectionForm.get('ClassID')?.enable();
    }

    onSectionDialogHide() {
        // centralize cleanup: reset form, clear submission flag, and ensure Class select enabled
        this.sectionForm.reset();
        this.sectionSubmitted = false;
        this.sectionForm.get('ClassID')?.enable();
    }

    deleteSection(section: any) {
        if (!section || !section.SectionID) return;
        this.confirmationService.confirm({
            message: `Are you sure you want to delete ${section.SectionName}?`,
            header: 'Confirm',
            icon: 'pi pi-exclamation-triangle',
            accept: () => {
                this.sectionsService.deleteSection(section.SectionID).subscribe({
                    next: () => { this.messageService.add({ severity: 'success', summary: 'Deleted', detail: 'Section deleted', life: 3000 }); this.loadSections(); },
                    error: () => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete section', life: 3000 })
                });
            }
        });
    }

    ngOnInit() {
        this.loadClasses();
    }

    loadClasses() {
        this.classesService.getClasses().subscribe({
            next: (data) => this.classes.set(data),
            error: (err) => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to load classes', life: 3000 }),
            complete: () => { this.loadSections(); this.mergeSectionCounts(); }
        });
    }

    // compute and attach SectionCount for each class using loaded sections
    private mergeSectionCounts() {
        const cls = this.classes();
        const secs = this.sections();
        if (!cls || cls.length === 0) return;
        // build counts map by ClassID
        const counts = new Map<number, number>();
        for (const s of secs) {
            const id = Number(s.ClassID);
            counts.set(id, (counts.get(id) || 0) + 1);
        }
        const updated = cls.map(c => ({ ...c, SectionCount: counts.get(c.ClassID as number) || 0 }));
        this.classes.set(updated);
    }

    openNew() {
        this.submitted = false;
        this.classForm.reset();
        this.classDialog = true;
    }

    // Open the sections dialog filtered for a specific class
    viewSections(class_: Classes) {
        this.selectedClassForView = class_;
        // ensure sections are loaded
        if (!this.sections() || this.sections().length === 0) {
            this.loadSections();
        }
        this.viewSectionsDialog = true;
    }

    // Returns sections filtered for the selected class
    filteredSections(): any[] {
        if (!this.selectedClassForView) return [];
        const clsId = this.selectedClassForView.ClassID;
        return (this.sections() || []).filter(s => Number(s.ClassID) === Number(clsId));
    }

    editClass(class_: Classes) {
        this.classForm.patchValue(class_);
        this.classDialog = true;
    }

    deleteClass(class_: Classes) {
        this.confirmationService.confirm({
            message: `Are you sure you want to delete ${class_.ClassName}?`,
            header: 'Confirm',
            icon: 'pi pi-exclamation-triangle',
            accept: () => {
                this.classesService.deleteClass(class_.ClassID).subscribe({
                    next: (success) => {
                        if (success) {
                            this.messageService.add({ severity: 'success', summary: 'Successful', detail: 'Class Deleted', life: 3000 });
                            this.loadClasses();
                        } else {
                            this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete class', life: 3000 });
                        }
                    },
                    error: () => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete class', life: 3000 }),
                    complete: () => {}
                });
            }
        });
    }

    deleteSelectedClasses() {
        this.confirmationService.confirm({
            message: 'Are you sure you want to delete the selected classes?',
            header: 'Confirm',
            icon: 'pi pi-exclamation-triangle',
            accept: () => {
                this.classes.set(this.classes().filter(val => !this.selectedClasses?.includes(val)));
                this.selectedClasses = null;
                this.messageService.add({
                    severity: 'success',
                    summary: 'Successful',
                    detail: 'Classes Deleted',
                    life: 3000
                });
            }
        });
    }

    saveClass() {
        this.submitted = true;

        if (this.classForm.invalid) {
            this.messageService.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please fill all required fields correctly.',
                life: 3000
            });
            return;
        }

        if (this.classForm.valid) {
            const formValue = this.classForm.value;
            if (formValue.ClassID) {
                // Update existing class (API)
                this.classesService.updateClass(formValue).subscribe({
                    next: (updated) => {
                        if (updated) {
                            this.messageService.add({ severity: 'success', summary: 'Successful', detail: 'Class Updated', life: 3000 });
                            this.loadClasses();
                        } else {
                            this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to update class', life: 3000 });
                        }
                    },
                    error: () => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to update class', life: 3000 }),
                    complete: () => {}
                });
            } else {
                // Create new class (API)
                this.classesService.createClass(formValue).subscribe({
                    next: (created) => {
                        if (created) {
                            this.messageService.add({ severity: 'success', summary: 'Successful', detail: 'Class Created', life: 3000 });
                            this.loadClasses();
                        } else {
                            this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to create class', life: 3000 });
                        }
                    },
                    error: () => this.messageService.add({ severity: 'error', summary: 'Error', detail: 'Failed to create class', life: 3000 }),
                    complete: () => {}
                });
            }

            // close dialog; list will refresh from API callbacks
            this.classDialog = false;
            this.classForm.reset();
        }
    }

    hideDialog() {
        this.classDialog = false;
        this.submitted = false;
    }

    onGlobalFilter(table: Table, event: Event) {
        table.filterGlobal((event.target as HTMLInputElement).value, 'contains');
    }

    exportCSV() {
        this.dt.exportCSV();
    }
    
    // Helper method to check form control validity
    isFieldInvalid(fieldName: string): boolean {
        const field = this.classForm.get(fieldName);
        return field ? (field.invalid && (field.dirty || field.touched || this.submitted)) : false;
    }
}
