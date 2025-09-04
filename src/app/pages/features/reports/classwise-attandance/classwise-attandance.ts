import { Component, HostListener, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TableModule } from 'primeng/table';
import { SelectModule } from 'primeng/select';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { TagModule } from 'primeng/tag';
import { DialogModule } from 'primeng/dialog';

interface AttendanceRow {
    studentName: string;
    className: string;
    // statuses indexed by day-1, e.g. statuses[0] => day 1
    statuses: string[];
    remarks?: string;
}

@Component({
    selector: 'app-classwise-attandance',
    standalone: true,
    imports: [CommonModule, FormsModule, TableModule, SelectModule, DatePickerModule, ButtonModule, TagModule, DialogModule],
    templateUrl: './classwise-attandance.html',
    styleUrl: './classwise-attandance.scss'
})
export class ClasswiseAttandance implements OnInit {
    classes = ['1A', '1B', '2A', '3A'];
    selectedClass = this.classes[0];
    classOptions = this.classes.map((c) => ({ label: c, value: c }));
    // using ISO date string for p-calendar/ngModel
    date = new Date();

    // computed days for current month
    days: number[] = [];

    rows: AttendanceRow[] = [];

    isMobile = false;
    selected: AttendanceRow | null = null;
    detailDialogVisible = false;

    ngOnInit() {
        this.updateGrid();
        this.checkMobile();
    }

    // compute number of days for selected month/year and rebuild sample grid
    updateGrid() {
        const year = this.date.getFullYear();
        const month = this.date.getMonth(); // 0-based
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        this.days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
        this.loadSampleData(daysInMonth);
    }

    loadSampleData(daysInMonth: number) {
        // create a few sample students with random attendance for the month
        const students = ['Alice Johnson', 'Brian Smith', 'Chloe Brown', 'Daniel Lee', 'Eva Green'];
        this.rows = students.map((name) => ({
            studentName: name,
            className: this.selectedClass,
            statuses: Array.from({ length: daysInMonth }, () => this.randomStatus()),
            remarks: ''
        }));
    }

    randomStatus(): string {
        // return 'p' (present), 'l' (late), 'h' (half-day), or 'a' (absent)
        const pool = ['p', 'p', 'p', 'l', 'h', 'a'];
        return pool[Math.floor(Math.random() * pool.length)];
    }

    refresh() {
        // typically call API here; for scaffold just recompute grid for selected date/class
        this.updateGrid();
    }

    getPresentCount(row: AttendanceRow) {
        return row.statuses.filter((s) => s === 'p' || s === 'h').length;
    }

    getAbsentCount(row: AttendanceRow) {
        return row.statuses.filter((s) => s === 'a').length;
    }

    getSeverity(status: string) {
        switch ((status || '').toLowerCase()) {
            case 'p':
                return 'success';
            case 'l':
                return 'warning';
            case 'h':
                return 'info';
            case 'a':
                return 'danger';
            default:
                return null;
        }
    }

    @HostListener('window:resize')
    checkMobile() {
        this.isMobile = window.innerWidth <= 768; // tweak breakpoint
    }

    openDetails(r: AttendanceRow) {
        this.selected = r;
        this.detailDialogVisible = true;
    }
}
