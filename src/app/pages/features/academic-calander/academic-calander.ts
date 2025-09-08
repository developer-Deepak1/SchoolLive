import { Component, OnDestroy, OnInit } from '@angular/core';
import { ChartConfiguration, ChartData, ChartType } from 'chart.js';
import { CommonModule } from '@angular/common';
import { BaseChartDirective } from 'ng2-charts';
import { TableModule } from 'primeng/table';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { InputTextModule } from 'primeng/inputtext';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { FormsModule } from '@angular/forms';
import { AcademicYearService } from '../services/academic-year.service';
import { MessageService, ConfirmationService } from 'primeng/api';
import { toLocalYMDIST } from '@/utils/date-utils';
import { AcademicCalendarService } from '../services/academic-calendar.service';
import ChartDataLabels from 'chartjs-plugin-datalabels';
@Component({
  selector: 'app-academic-calander',
  standalone: true,
  imports: [
    CommonModule,
    BaseChartDirective,
    TableModule,
    // charts
    // ng2-charts provides ChartDirective via standalone import when needed in template
    CardModule,
    ButtonModule,
    SelectModule,
    DatePickerModule,
    ToastModule,
    ConfirmDialogModule,
    InputTextModule,
    FormsModule
  ],
  providers: [MessageService, ConfirmationService],
  templateUrl: './academic-calander.html',
  styleUrls: ['./academic-calander.scss']
})
export class AcademicCalander implements OnInit, OnDestroy {
  // Academic Years
  academicYears: any[] = [];
  selectedAcademicYear: any = null;
  public barChartPlugins = [ChartDataLabels];
  constructor(
    private academicYearService: AcademicYearService,
    private calendarService: AcademicCalendarService,
    private msg: MessageService,
    private confirmationService: ConfirmationService
  ) { }

  ngOnInit(): void {
    this.loadAcademicYears();
  }

  loadAcademicYears() {
    this.academicYearService.getAcademicYears().subscribe({
      next: (data: any[]) => {
        this.academicYears = data.map(y => ({ label: y.AcademicYearName, value: y }));
        const active = data.find(y => y.Status && String(y.Status).toLowerCase() === 'active');
        // prefer active academic year, otherwise default to first
        const sel = active ? active : (this.academicYears.length > 0 ? this.academicYears[0].value : null);
        if (sel) {
          this.selectedAcademicYear = { label: sel.AcademicYearName, value: sel };
          // compute bounds and reset UI state
          this.setAcademicYearBounds(sel);
          this.cancelEditHoliday();
          // load year-specific data
          this.loadWeeklyOffs();
          this.loadHolidays();
          this.loadMonthlyWorkingDays();
        }
      },
      error: (err) => {
        console.error('Failed to load academic years', err);
      }
    });
  }

  onAcademicYearChange(e: any) {
    this.selectedAcademicYear = e;
    this.setAcademicYearBounds(e?.value || e);
    this.cancelEditHoliday();
    this.loadWeeklyOffs();
    this.loadHolidays();
    this.loadMonthlyWorkingDays();
  }
  // Weekly Off
  daysOfWeek = [
    { label: 'Monday', value: 1 },
    { label: 'Tuesday', value: 2 },
    { label: 'Wednesday', value: 3 },
    { label: 'Thursday', value: 4 },
    { label: 'Friday', value: 5 },
    { label: 'Saturday', value: 6 },
    { label: 'Sunday', value: 7 }
  ];
  weeklyOffs: any[] = [];
  // internal set for quick membership checks (stores values)
  weeklyOffSet = new Set<number>();

  toggleWeeklyDay(day: any) {
    const val = day.value;
    if (this.weeklyOffSet.has(val)) {
      this.weeklyOffSet.delete(val);
      this.weeklyOffs = this.weeklyOffs.filter(o => o.value !== val);
    } else {
      this.weeklyOffSet.add(val);
      this.weeklyOffs.push(day);
    }
    // update server-side selection
    this.saveWeeklyOffs();
  }

  isDayOff(day: any) {
    return this.weeklyOffSet.has(day.value);
  }

  removeWeeklyOff(off: any) {
    this.weeklyOffSet.delete(off.value);
    this.weeklyOffs = this.weeklyOffs.filter(o => o.value !== off.value);
    this.saveWeeklyOffs();
  }

  // Holidays
  holidayDate: any;
  holidayTitle: string = '';
  holidayType: any;
  holidayTypes = [
    { label: 'Holiday', value: 'Holiday' },
    { label: 'WorkingDay', value: 'WorkingDay' }
  ];
  holidays: any[] = [];
  // edit state
  editingHoliday: any = null;
  // cached min/max dates for the selected academic year — updated only when selection changes
  minHolidayDate: Date | null = null;
  maxHolidayDate: Date | null = null;
  // Range preview support (client-side only)
  holidayEndDate: any = null;
  rangeMode: boolean = false;
  rangePreview: string[] = [];
  previewCollapsed: boolean = false;

  // Clear end-date and preview when range mode is toggled off
  onRangeModeChange(val: boolean) {
    this.rangeMode = !!val;
    if (!this.rangeMode) {
      this.holidayEndDate = null;
      this.rangePreview = [];
      this.previewCollapsed = false;
    }
  }

  // Helper: parse a value (Date, 'YYYY-MM-DD', ISO) into a local Date at midnight
  private parseToLocalDate(v: any): Date | null {
    if (!v && v !== 0) return null;
    if (v instanceof Date) {
      return new Date(v.getFullYear(), v.getMonth(), v.getDate());
    }
    if (typeof v === 'number') {
      const dt = new Date(v);
      return new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
    }
    if (typeof v === 'string') {
      // plain YYYY-MM-DD
      const ymd = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (ymd) {
        const y = Number(ymd[1]);
        const m = Number(ymd[2]) - 1;
        const d = Number(ymd[3]);
        return new Date(y, m, d);
      }
      // try ISO parse then convert to local date-only
      const parsed = new Date(v);
      if (!isNaN(parsed.getTime())) return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    }
    return null;
  }

  startEditHoliday(h: any) {
    this.editingHoliday = { ...h };
    // populate top form fields for editing
    const rawDate = this.editingHoliday.date;
    if (rawDate) {
      // support several date formats: Date object, YYYY-MM-DD, ISO datetime, or timestamp
      if (rawDate instanceof Date) {
        this.holidayDate = new Date(rawDate.getFullYear(), rawDate.getMonth(), rawDate.getDate());
      } else if (typeof rawDate === 'string') {
        // handle plain YYYY-MM-DD reliably
        const ymd = rawDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (ymd) {
          const y = Number(ymd[1]);
          const m = Number(ymd[2]) - 1;
          const d = Number(ymd[3]);
          this.holidayDate = new Date(y, m, d);
        } else {
          // fallback to Date parsing for ISO strings
          const parsed = this.parseToLocalDate(rawDate);
          this.holidayDate = parsed;
        }
      } else if (typeof rawDate === 'number') {
        this.holidayDate = this.parseToLocalDate(rawDate);
      } else {
        this.holidayDate = null;
      }
    } else {
      this.holidayDate = null;
    }
    this.holidayTitle = this.editingHoliday.title || '';
    // set holidayType to matching option object
    this.holidayType = this.holidayTypes.find(x => x.value === this.editingHoliday.type) || null;
  }

  cancelEditHoliday() {
    this.editingHoliday = null;
    this.holidayDate = null;
    this.holidayTitle = '';
    this.holidayType = null;
  }

  // saveEditHoliday removed — editing is handled inline via addHoliday/update flows

  // normalize backend holiday object to UI-friendly shape
  private normalizeHoliday(h: any) {
    if (!h) return null;
    const id = h.HolidayID ?? h.id ?? h.Holiday_Id ?? null;
    const rawDate = h.Date ?? h.date ?? null;
    let date = null;
    if (rawDate) {
      if (typeof rawDate === 'string') {
        date = rawDate.split('T')[0].split(' ')[0];
      } else {
        try { date = toLocalYMDIST(new Date(rawDate)); } catch (e) { date = String(rawDate); }
      }
    }
    const title = h.Title ?? h.title ?? '';
    const type = h.Type ?? h.type ?? '';
    return { id, date, title, type };
  }

  addHoliday() {
    // Basic required-field validation
    if (!this.holidayDate) {
      this.msg.add({ severity: 'warn', summary: 'Missing Date', detail: 'Please select a holiday date' });
      return;
    }
    if (!this.holidayType) {
      this.msg.add({ severity: 'warn', summary: 'Missing Type', detail: 'Please select a holiday type' });
      return;
    }
    if (!this.holidayTitle || String(this.holidayTitle).trim() === '') {
      this.msg.add({ severity: 'warn', summary: 'Missing Title', detail: 'Please enter a holiday title' });
      return;
    }

    if (this.holidayDate && this.holidayType) {
      // Ensure selected date is within academic year bounds
      const pickedDate = this.parseToLocalDate(this.holidayDate) || (this.holidayDate instanceof Date ? new Date(this.holidayDate.getFullYear(), this.holidayDate.getMonth(), this.holidayDate.getDate()) : null);
      if (!pickedDate) {
        this.msg.add({ severity: 'warn', summary: 'Invalid Date', detail: 'Please select a valid start date' });
        return;
      }
      const min = this.minHolidayDate;
      const max = this.maxHolidayDate;
      if (min && pickedDate < min) {
        this.msg.add({ severity: 'warn', summary: 'Invalid Date', detail: `Holiday must be on or after ${toLocalYMDIST(min)}` });
        return;
      }
      if (max && pickedDate > max) {
        this.msg.add({ severity: 'warn', summary: 'Invalid Date', detail: `Holiday must be on or before ${toLocalYMDIST(max)}` });
        return;
      }

      // If in range mode, validate end date and call range API
      if (this.rangeMode) {
        if (!this.holidayEndDate) {
          this.msg.add({ severity: 'warn', summary: 'End date required', detail: 'Please select an end date for the range' });
          return;
        }
        const endDate = this.parseToLocalDate(this.holidayEndDate) || (this.holidayEndDate instanceof Date ? new Date(this.holidayEndDate.getFullYear(), this.holidayEndDate.getMonth(), this.holidayEndDate.getDate()) : null);
        if (!endDate) {
          this.msg.add({ severity: 'warn', summary: 'Invalid Date', detail: 'Please select a valid end date' });
          return;
        }
        if (endDate < pickedDate) {
          this.msg.add({ severity: 'warn', summary: 'Invalid Range', detail: 'End date must be the same or after start date' });
          return;
        }
        if (min && endDate < min) { this.msg.add({ severity: 'warn', summary: 'Invalid Date', detail: `End date must be on or after ${min.toISOString().split('T')[0]}` }); return; }
        if (max && endDate > max) { this.msg.add({ severity: 'warn', summary: 'Invalid Date', detail: `End date must be on or before ${max.toISOString().split('T')[0]}` }); return; }

        const formattedStart = toLocalYMDIST(pickedDate);
        const formattedEnd = toLocalYMDIST(endDate);
        const payloadRange = {
          AcademicYearID: this.selectedAcademicYear?.value?.AcademicYearID || null,
          StartDate: formattedStart,
          EndDate: formattedEnd,
          Title: this.holidayTitle,
          Type: this.holidayType.value
        };

        this.calendarService.createHolidayRange(payloadRange).subscribe({
          next: (res: any) => {
            if (res && res.success) {
              const created = res.data?.createdDates?.length ?? 0;
              const skipped = res.data?.skippedDates?.length ?? 0;
              this.showToast('success', 'Range created', `${created} created, ${skipped} skipped`);
              this.loadHolidays();
            } else {
              this.showToast('warn', 'Range create', res?.message ?? 'Unexpected server response');
            }
            this.holidayDate = null; this.holidayEndDate = null; this.holidayTitle = ''; this.holidayType = null; this.rangePreview = [];
          },
          error: (err: any) => {
            console.error('Create holiday range failed', err);
            this.showToast('error', 'Range create failed', err?.error?.message ?? err?.message ?? 'Server error', 6000);
            this.holidayDate = null; this.holidayEndDate = null; this.holidayTitle = ''; this.holidayType = null; this.rangePreview = [];
            this.previewCollapsed = false; this.editingHoliday = null;
          }
        });
        return;
      }

      const formattedDate = toLocalYMDIST(pickedDate) || (typeof this.holidayDate === 'string' ? this.holidayDate.split('T')[0] : null);
      const payload = {
        AcademicYearID: this.selectedAcademicYear?.value?.AcademicYearID || null,
        Date: formattedDate,
        Title: this.holidayTitle,
        Type: this.holidayType.value
      };

      // Prevent duplicate single-date holidays (unless updating the same record)
      if (!this.editingHoliday) {
        const exists = this.holidays.find(h => h.date === formattedDate);
        if (exists) {
          this.msg.add({ severity: 'warn', summary: 'Duplicate', detail: `A holiday already exists for ${formattedDate}` });
          return;
        }
      } else {
        // If editing, ensure we don't accidentally duplicate to another existing date
        const existsOther = this.holidays.find(h => h.date === formattedDate && h.id !== this.editingHoliday.id);
        if (existsOther) {
          this.msg.add({ severity: 'warn', summary: 'Duplicate', detail: `Another holiday already exists for ${formattedDate}` });
          return;
        }
      }
      // If editingHoliday is set, perform update instead of create
      if (this.editingHoliday && this.editingHoliday.id) {
        this.calendarService.updateHoliday(this.editingHoliday.id, payload).subscribe({
          next: (res) => {
            const message = (res && res.message) ? res.message : (res && res.success ? 'Holiday updated' : 'Holiday update response');
            if (res && res.success) {
              this.msg.add({ severity: 'success', summary: 'Updated', detail: message });
              this.loadHolidays();
            } else {
              this.msg.add({ severity: 'warn', summary: 'Update', detail: message });
              this.loadHolidays();
            }
            this.cancelEditHoliday();
            this.loadMonthlyWorkingDays();
          },
          error: (err) => {
            console.error('Update holiday failed', err);
            const detail = err?.error?.message ?? err?.message ?? 'Server error';
            this.msg.add({ severity: 'error', summary: 'Update failed', detail });
            // optimistic update fallback
            this.holidays = this.holidays.map(h => h.id === this.editingHoliday.id ? { ...h, date: payload.Date, title: payload.Title, type: payload.Type } : h);
            this.cancelEditHoliday();
          }
        });
      } else {
        this.calendarService.createHoliday(payload).subscribe({
          next: (res) => {
            if (res && res.success && res.data) {
              if (res.data && (res.data.HolidayID || res.data.id)) {
                const norm = this.normalizeHoliday(res.data);
                if (norm) this.holidays = [...this.holidays, norm];
              } else {
                this.loadHolidays();
                // refresh chart after server-created entries
              }
              this.msg.add({ severity: 'success', summary: 'Created', detail: (res && res.message) ? res.message : 'Holiday created' });
            } else {
              const fallback = { id: null, date: formattedDate, title: this.holidayTitle, type: this.holidayType?.value };
              this.holidays.push(fallback);
              const detail = (res && res.message) ? res.message : 'Unexpected response from server';
              this.msg.add({ severity: 'warn', summary: 'Notice', detail });
              // ensure chart refresh even on unusual responses
            }
            this.loadMonthlyWorkingDays();
          },
          error: (err) => {
            console.error('Create holiday failed', err);
            const fallback = { id: null, date: formattedDate, title: this.holidayTitle, type: this.holidayType?.value };
            this.holidays.push(fallback);
            const detail = err?.error?.message ?? err?.message ?? 'Server error';
            this.msg.add({ severity: 'error', summary: 'Create failed', detail });
            // attempt chart refresh after error (server may have partial changes)
            this.loadMonthlyWorkingDays();
          }
        });
        this.holidayDate = null;
        this.holidayTitle = '';
        this.holidayType = null;
      }
    }
  }

  // Build a simple client-side preview of dates between holidayDate and holidayEndDate (inclusive)
  computeRangePreview() {
    this.rangePreview = [];
    if (!this.holidayDate || !this.holidayEndDate) return;
    const sd = this.parseToLocalDate(this.holidayDate);
    const ed = this.parseToLocalDate(this.holidayEndDate);
    if (!sd || !ed) return;
    if (isNaN(sd.getTime()) || isNaN(ed.getTime())) return;
    if (ed < sd) return;
    const start: Date = sd;
    const end: Date = ed;
    const list: string[] = [];
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
      // use IST-aware formatter to avoid UTC day shift
      const ymd = toLocalYMDIST(d);
      if (ymd) list.push(ymd);
    }
    this.rangePreview = list;
  }

  removeHoliday(h: any) {
    const id = h?.id ?? h?.HolidayID ?? null;
    // ask for confirmation before deleting
    this.confirmationService.confirm({
      message: 'Are you sure you want to delete this holiday?.',
      header: 'Confirm Delete',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Delete',
      rejectLabel: 'Cancel',
      accept: () => {
        if (id) {
          this.calendarService.deleteHoliday(id).subscribe({
            next: (res) => { this.msg.add({ severity: 'success', summary: 'Deleted', detail: (res && res.message) ? res.message : 'Holiday deleted' }); this.loadHolidays(); this.loadMonthlyWorkingDays(); },
            error: (err) => { console.error('Delete holiday failed', err); this.holidays = this.holidays.filter(x => x !== h); const detail = err?.error?.message ?? err?.message ?? 'Server error'; this.msg.add({ severity: 'error', summary: 'Delete failed', detail }); }
          });
        } else {
          this.holidays = this.holidays.filter(x => x !== h);
        }
      }
    });
  }

  // Load weekly offs from server for selected academic year
  loadWeeklyOffs() {
    const ay = this.selectedAcademicYear?.value?.AcademicYearID;
    this.calendarService.getWeeklyOffs(ay).subscribe({
      next: (data: any[]) => {
        this.weeklyOffs = [];
        this.weeklyOffSet.clear();
        data.forEach(d => {
          const val = Number(d.DayOfWeek);
          const label = this.daysOfWeek.find(x => x.value === val)?.label || `Day ${val}`;
          this.weeklyOffs.push({ label, value: val });
          this.weeklyOffSet.add(val);
        });
      },
      error: (err) => { console.error('Failed to load weekly offs', err); }
    });
  }

  saveWeeklyOffs() {
    const ay = this.selectedAcademicYear?.value?.AcademicYearID;
    const days = Array.from(this.weeklyOffSet.values());
    if (!ay) return;
    this.calendarService.setWeeklyOffs(ay, days).subscribe({
      next: (ok) => {
        if (ok) {
          this.showToast('success', 'Weekly Offs', 'Weekly offs updated');
          // refresh chart data after weekly offs change
        } else {
          // service maps to boolean; inform user that server responded negatively
          this.showToast('warn', 'Weekly Offs', 'Server did not confirm update');
          // still attempt to refresh chart
        }
        this.loadMonthlyWorkingDays();
      },
      error: (err) => {
        console.error('Failed to save weekly offs', err);
        const detail = err?.error?.message ?? err?.message ?? 'Server error';
        this.showToast('error', 'Weekly Offs save failed', detail, 6000);
      }
    });
  }

  loadHolidays() {
    const ay = this.selectedAcademicYear?.value?.AcademicYearID;
    this.calendarService.getHolidays(ay).subscribe({
      next: (data: any[]) => {
        this.holidays = (data || []).map(d => this.normalizeHoliday(d)).filter(x => x !== null);
      }, error: (err) => console.error('Failed to load holidays', err)
    });
  }

  // Load monthly working days from server (falls back to compute if server side has no persisted copy)
  loadMonthlyWorkingDays() {
    const ay = this.selectedAcademicYear?.value?.AcademicYearID;
    this.calendarService.getMonthlyWorkingDays(ay).subscribe({
      next: (res: any) => {
        if (!res) return;
        // API returns either { months:[], workingDays:[], labels:[] } or computed shape
        const labels = res.labels ?? (res.months ? res.months.map((m: string) => (new Date(m + '-01')).toLocaleString(undefined, { month: 'short' })) : []);
        const data = res.workingDays ?? (res.datasets && res.datasets[0] ? res.datasets[0].data : []);
        if (labels && data) {
          this.barChartData = { labels, datasets: [{ data, label: 'Working Days', backgroundColor: '#10b981' }] };
        }
      }, error: (err) => { console.error('Failed to load monthly working days', err); }
    });
  }

  // Exception Working Days
  // Exception Working Days removed; use holiday.type === 'WorkingDay' instead

  // search functionality intentionally removed; tables remain simple and local

  // Helper to show toasts via PrimeNG MessageService and also log attempts for debugging
  showToast(severity: 'success' | 'info' | 'warn' | 'error', summary: string, detail?: string, life: number = 4000) {
    try {
      // Console log helps debug when toasts are not visible in the UI
      console.log('[Toast]', { severity, summary, detail, life });
      this.msg.add({ severity, summary, detail, life });
    } catch (e) {
      // Fallback log if MessageService is not available for some reason
      console.error('showToast failed', e, { severity, summary, detail, life });
    }
  }
  // Chart: monthly working days for selected academic year
  public barChartType: ChartType = 'bar';
  public barChartData: ChartData<'bar'> = { labels: [], datasets: [{ data: [], label: 'Working Days', backgroundColor: '#10b981' }] };
  public barChartOptions: ChartConfiguration['options'] = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      datalabels: {
        anchor: 'center',
        align: 'center',
        color: '#ffffffff',
        font: { weight: 'bold', size: 12 },
        formatter: (value) => value
      }
    },
    scales: {
      y: { display: false },
      x: { display: true }
    }
  };


  // Compute and cache min/max date bounds for the currently selected academic year
  private setAcademicYearBounds(yearObj: any) {
    if (!yearObj) {
      this.minHolidayDate = null;
      this.maxHolidayDate = null;
      return;
    }
    const s = yearObj.StartDate || yearObj.start_date || yearObj.from || yearObj.start || null;
    const e = yearObj.EndDate || yearObj.end_date || yearObj.to || yearObj.end || null;
    try {
      this.minHolidayDate = s ? this.parseToLocalDate(s) : null;
    } catch (err) {
      this.minHolidayDate = null;
    }
    try {
      this.maxHolidayDate = e ? this.parseToLocalDate(e) : null;
    } catch (err) {
      this.maxHolidayDate = null;
    }

  }
  ngOnDestroy(): void {
  }

}
