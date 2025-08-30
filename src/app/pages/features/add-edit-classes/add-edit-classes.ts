import { CommonModule } from '@angular/common';
import { Component, OnInit, ViewChild, signal } from '@angular/core';
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ConfirmationService, MessageService } from 'primeng/api';
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
    classes = signal<Classes[]>([]);
    selectedClasses!: Classes[] | null;
    submitted: boolean = false;

    @ViewChild('dt') dt!: Table;

    cols: Column[] = [
        { field: 'className', header: 'Class Name' },
        { field: 'classCode', header: 'Class Code' },
        { field: 'stream', header: 'Stream' },
        { field: 'maxStrength', header: 'Max Strength' }
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
        private confirmationService: ConfirmationService
    ) {
        this.initForm();
    }

    private initForm() {
        this.classForm = this.fb.group({
            classId: [null],
            className: ['', [Validators.required]],
            classCode: ['', [Validators.required]],
            stream: [StreamType.NONE, [Validators.required]],
            maxStrength: [null, [Validators.required, Validators.min(1)]],
            classTeacherId: [null],
            sections: [[]]
        });
    }

    ngOnInit() {
        this.loadClasses();
    }

    loadClasses() {
        this.classesService.getClasses().then((data) => {
            this.classes.set(data);
        });
    }

    openNew() {
        this.submitted = false;
        this.classForm.reset();
        this.classDialog = true;
    }

    editClass(class_: Classes) {
        this.classForm.patchValue(class_);
        this.classDialog = true;
    }

    deleteClass(class_: Classes) {
        this.confirmationService.confirm({
            message: `Are you sure you want to delete ${class_.className}?`,
            header: 'Confirm',
            icon: 'pi pi-exclamation-triangle',
            accept: () => {
                this.classes.set(this.classes().filter(val => val.classId !== class_.classId));
                this.messageService.add({
                    severity: 'success',
                    summary: 'Successful',
                    detail: 'Class Deleted',
                    life: 3000
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
            let _classes = this.classes();

            if (formValue.classId) {
                // Update existing class
                const index = _classes.findIndex(c => c.classId === formValue.classId);
                if (index !== -1) {
                    _classes[index] = { ..._classes[index], ...formValue };
                    this.messageService.add({
                        severity: 'success',
                        summary: 'Successful',
                        detail: 'Class Updated',
                        life: 3000
                    });
                }
            } else {
                // Create new class
                const newClass = {
                    ...formValue,
                    classId: this.generateClassId()
                };
                _classes.push(newClass);
                this.messageService.add({
                    severity: 'success',
                    summary: 'Successful',
                    detail: 'Class Created',
                    life: 3000
                });
            }

            this.classes.set([..._classes]);
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

    private generateClassId(): number {
        // Simple ID generation - in real app, this should come from backend
        return Math.floor(Math.random() * 1000) + 1;
    }

    // Helper method to check form control validity
    isFieldInvalid(fieldName: string): boolean {
        const field = this.classForm.get(fieldName);
        return field ? (field.invalid && (field.dirty || field.touched || this.submitted)) : false;
    }
}
