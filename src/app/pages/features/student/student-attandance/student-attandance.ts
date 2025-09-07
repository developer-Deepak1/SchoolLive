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
    { label: 'P', value: 'Present' },
    { label: 'L', value: 'Leave' },
    { label: 'H', value: 'HalfDay' },
    { label: 'A', value: 'Absent' }
  ];

  classOptions: any[] = [];
  sectionOptions: any[] = [];
  // cache sections per class to avoid repeated requests
  private sectionsCache: Record<number, any[]> = {};
  selectedClass: number | null = null;
  selectedSection: number | null = null;
  sectionsLoading = signal<boolean>(false);
  attendanceTaken = signal<boolean>(false);
  takenBy: string | null = null;
  takenAt: string | null = null;
  savedSuccess = signal<boolean>(false);
  savedClassName: string | null = null;
  savedSectionName: string | null = null;

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
        const data = (res.records || []).map((r:any) => {
          // If no previous attendance, show default 'Present' and mark as dirty so
          // Save will create attendance rows. If previous attendance exists, preserve it.
          const status = r.Status ?? 'Present';
          const original = r.Status ?? null;
          return {...r, Status: status, _original: original, _dirty: (original === null)};
        });
          this.rows.set(data);
          // populate takenBy/takenAt from meta if provided by API
          if (res.meta) {
            this.takenBy = res.meta.takenBy || null;
            this.takenAt = res.meta.takenAt || null;
          } else {
            this.takenBy = null; this.takenAt = null;
          }
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
  this.studentsSvc.getClasses().subscribe({
    next: (c:any[]) => {
      this.classOptions = c || [];
      // fetch all sections in one call and group by ClassID
      this.sectionsLoading.set(true);
      this.studentsSvc.getAllSections().subscribe({
        next: (allSections:any[]) => {
          (allSections || []).forEach((s:any) => {
            const cid = s.ClassID || s.class_id || s.Class || null;
            if (!cid) return;
            if (!this.sectionsCache[cid]) this.sectionsCache[cid] = [];
            this.sectionsCache[cid].push(s);
          });
          this.sectionsLoading.set(false);
        },
        error: () => { this.sectionsLoading.set(false); }
      });
    }, error: (err: any) => {}
  });
  }

  onClassChange(){
  // When class changes, always clear any previously selected section so
  // Search/Reload remain disabled until user explicitly picks a section.
  this.selectedSection = null;
  if (!this.selectedClass) { this.sectionOptions = []; return; }
  // Use cached sections if available
  const cached = this.sectionsCache[this.selectedClass as number];
  if (cached) {
    this.sectionOptions = cached;
  return;
  }
  // fallback to API call if cache miss
  this.sectionsLoading.set(true);
  this.studentsSvc.getSections(this.selectedClass).subscribe({ next: (s:any[]) => { this.sectionOptions = s || []; this.sectionsCache[this.selectedClass as number] = s || []; this.sectionsLoading.set(false); }, error: (err: any) => { this.sectionOptions = []; this.sectionsLoading.set(false); } });
  }

  search(){
    this.load(this.dateModel, this.selectedSection ?? undefined);
  }

  markChanged(row: any, newVal?: string){
    // If user attempts to clear selection (newVal falsy), ignore the action so
    // the existing selection remains. This prevents reassigning previous values
    // explicitly and makes one selection mandatory only when user picks a value.
    if (!newVal || newVal === '') {
      // do nothing - keep current row.Status untouched
    } else {
      row.Status = newVal;
    }
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
  const dt = this.normalizeDate(this.dateModel ?? this.date());
  this.svc.save(dt, entries, this.selectedClass ?? null, this.selectedSection ?? null).subscribe({
      next: (res: any) => {
        // update originals
        const map = new Map(entries.map(e=>[e.StudentID,e.Status]));
        this.rows.update(list => list.map(r => {
          if(map.has(r.StudentID)){ r._original = r.Status; r._dirty = false; }
          return r;
        }));
  this.saving.set(false);
  // show toast
  this.msg.add({severity:'success', summary:'Attendance saved', detail:`Created ${res.summary.created}, Updated ${res.summary.updated}`});
  // update takenBy/takenAt if backend returns meta
  if (res.meta) { this.takenBy = res.meta.takenBy || null; this.takenAt = res.meta.takenAt || null; }
  // capture class/section names for success card, then clear
  const cls = this.classOptions.find(c=>c.ClassID===this.selectedClass);
  const sec = this.sectionOptions.find(s=>s.SectionID===this.selectedSection);
  this.savedClassName = cls ? (cls.ClassName || cls.class_name || null) : null;
  this.savedSectionName = sec ? (sec.SectionName || sec.section_name || null) : null;
  // mark success, clear form and rows, and hide the attendance table
  this.savedSuccess.set(true);
  // clear selection and rows
  this.selectedClass = null; this.selectedSection = null; this.dateModel = toLocalYMDIST(new Date()) || '';
  this.rows.set([]);
  this.attendanceTaken.set(false);
  // auto-hide success after 3 seconds
  setTimeout(()=>{ this.savedSuccess.set(false); }, 5000);
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
