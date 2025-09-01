import { Component, signal, inject } from '@angular/core';
import { toLocalYMDIST } from '@/utils/date-utils';
import { CommonModule } from '@angular/common';
import { AttendanceService, AttendanceRecord } from '@/pages/features/services/attendance.service';
import { StudentsService } from '@/pages/features/services/students.service';
import { FormsModule } from '@angular/forms';
import { TableModule } from 'primeng/table';
import { SelectButtonModule } from 'primeng/selectbutton';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { ButtonModule } from 'primeng/button';
import { CardModule } from 'primeng/card';
import { ToolbarModule } from 'primeng/toolbar';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { TooltipModule } from 'primeng/tooltip';

@Component({
  selector: 'app-student-attandance',
  standalone: true,
  imports: [CommonModule, FormsModule, TableModule, SelectButtonModule, SelectModule, DatePickerModule, ButtonModule, CardModule, ToolbarModule, TagModule, ToastModule, ProgressSpinnerModule, TooltipModule],
  providers: [MessageService],
  templateUrl: './student-attandance.html',
  styleUrl: './student-attandance.scss'
})
export class StudentAttandance {
  private svc: AttendanceService = inject(AttendanceService);
  private msg: MessageService = inject(MessageService);
  private studentsSvc: StudentsService = inject(StudentsService);

  date = signal<string>(toLocalYMDIST(new Date()) || '');
  // separate model for template two-way binding
  dateModel: string = toLocalYMDIST(new Date()) || '';
  loading = signal<boolean>(false);
  rows = signal<(AttendanceRecord & { _dirty?: boolean; _original?: string | null })[]>([]);
  saving = signal<boolean>(false);

  statuses = [
    { label: 'Present (P)', value: 'Present' },
    { label: 'Leave (L)', value: 'Leave' },
    { label: 'Half Day (H)', value: 'HalfDay' },
    { label: 'Absent (A)', value: 'Absent' }
  ];

  classOptions: any[] = [];
  sectionOptions: any[] = [];
  selectedClass: number | null = null;
  selectedSection: number | null = null;
  attendanceTaken = signal<boolean>(false);

  constructor(){
  this.loadClasses();
  }

  private normalizeDate(v: any): string {
    if (!v) return '';
    // Use IST-aware formatting for any incoming value
    try {
      const ymd = toLocalYMDIST(v);
      return ymd || '';
    } catch (e) { return ''; }
  }

  load(date?: string, sectionId?: number){
    // ensure filters selected
    const selClass = this.selectedClass;
    const selSection = sectionId ?? this.selectedSection;
    const dtRaw = date ?? this.dateModel ?? this.date();
    const dt = this.normalizeDate(dtRaw);
    if (!selClass || !selSection || !dt) {
      this.rows.set([]);
      this.attendanceTaken.set(false);
      this.msg.add({ severity: 'info', summary: 'Select filters', detail: 'Please select Class, Section and Date to load attendance.', life: 3500 });
      return;
    }

    this.loading.set(true);
    const sid = selSection ?? undefined;
  this.svc.getDaily(dt, sid).subscribe({
      next: (res: any) => {
        const data = (res.records || []).map((r:any) => ({...r, Status: r.Status || 'Present', _original: r.Status ?? null}));
        this.rows.set(data);
        // detect if attendance already exists for selected date/section
        const taken = data.some((d:any) => d._original !== null);
        this.attendanceTaken.set(taken);
        if (taken) {
          const count = data.filter((d:any)=>d._original!==null).length;
          this.msg.add({ severity: 'warn', summary: 'Attendance exists', detail: `Attendance already recorded (${count} students) for selected date/section. You may update.`, life: 5000 });
        }
        this.loading.set(false);
  },
  error: (err: any) => { this.loading.set(false); }
    });
  }

  loadClasses(){
  this.studentsSvc.getClasses().subscribe({ next: (c:any[]) => { this.classOptions = c || []; }, error: (err: any) => {} });
  }

  onClassChange(){
    if (!this.selectedClass) { this.sectionOptions = []; this.selectedSection = null; return; }
  this.studentsSvc.getSections(this.selectedClass).subscribe({ next: (s:any[]) => { this.sectionOptions = s || []; }, error: (err: any) => { this.sectionOptions = []; } });
  }

  search(){
    this.load(this.dateModel, this.selectedSection ?? undefined);
  }

  markChanged(row: any){
    row._dirty = (row.Status !== row._original);
  }

  get dirtyRows(){
    return this.rows().filter(r => r._dirty);
  }

  save(){
  const entries = this.dirtyRows.map(r => ({ StudentID: r.StudentID, Status: this.toShort(r.Status ?? 'Present') }));
    if(entries.length===0){
      this.msg.add({severity:'info', summary:'No changes'});
      return;
    }
    this.saving.set(true);
  this.svc.save(this.date(), entries).subscribe({
      next: (res: any) => {
        // update originals
        const map = new Map(entries.map(e=>[e.StudentID,e.Status]));
        this.rows.update(list => list.map(r => {
          if(map.has(r.StudentID)){ r._original = r.Status; r._dirty = false; }
          return r;
        }));
        this.saving.set(false);
        this.msg.add({severity:'success', summary:'Attendance saved', detail:`Created ${res.summary.created}, Updated ${res.summary.updated}`});
  },
  error: (err: any) => { this.saving.set(false); this.msg.add({severity:'error', summary:'Save failed'}); }
    });
  }

  toShort(status: string){
    switch(status){
      case 'Present': return 'P';
      case 'Leave': return 'L';
      case 'HalfDay': return 'H';
      case 'Absent': return 'A';
      default: return status;
    }
  }
}
