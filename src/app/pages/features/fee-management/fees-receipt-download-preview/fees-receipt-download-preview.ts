import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges, computed, inject, signal } from '@angular/core';
import { DomSanitizer, SafeResourceUrl } from '@angular/platform-browser';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { MessageService } from 'primeng/api';
import { firstValueFrom } from 'rxjs';

import { PdfService } from '@/services/pdf.service';
import { StudentsService } from '../../services/students.service';
import { Student } from '../../model/student.model';
import { StudentFeesService, StudentFeeLedgerRow } from '../services/student-fees.service';

@Component({
  selector: 'app-fees-receipt-download-preview',
  standalone: true,
  imports: [CommonModule, ProgressSpinnerModule],
  templateUrl: './fees-receipt-download-preview.html',
  styleUrl: './fees-receipt-download-preview.scss'
})
export class FeesReceiptDownloadPreview implements OnChanges {
  @Input() feeId: number | null = null;
  @Input() studentId: number | null = null;
  @Input() studentFeeId: number | null = null;
  @Input() isPreview = false;

  @Output() downloaded = new EventEmitter<void>();
  @Output() previewReady = new EventEmitter<void>();
  @Output() failed = new EventEmitter<string>();

  private feesApi = inject(StudentFeesService);
  private studentsApi = inject(StudentsService);
  private pdf = inject(PdfService);
  private sanitizer = inject(DomSanitizer);
  private toast = inject(MessageService, { optional: true });

  private loadingSignal = signal(false);
  loading = computed(() => this.loadingSignal());

  private pdfUrlSignal = signal<SafeResourceUrl | null>(null);
  pdfUrl = computed(() => this.pdfUrlSignal());

  ngOnChanges(changes: SimpleChanges): void {
    if ((changes['feeId'] || changes['studentId'] || changes['studentFeeId'] || changes['isPreview']) && this.feeId && this.studentId) {
      void this.generateReceipt();
    }
  }

  private async generateReceipt(): Promise<void> {
    this.loadingSignal.set(true);
    this.pdfUrlSignal.set(null);

    try {
      const [ledgerRows, student] = await Promise.all([
        firstValueFrom(this.feesApi.getLedger(this.studentId!, { include_paid: true, only_due: false })),
        firstValueFrom(this.studentsApi.getStudent(this.studentId!))
      ]);

      const receiptRow = this.selectReceiptRow(ledgerRows);
      if (!receiptRow) {
        throw new Error('Receipt details not available for the selected fee.');
      }

      const doc = this.pdf.buildFeeReceiptDoc(receiptRow, student);

      if (this.isPreview) {
        const dataUrl = await this.pdf.getDataUrl(doc);
        const safeUrl = this.sanitizer.bypassSecurityTrustResourceUrl(dataUrl);
        this.pdfUrlSignal.set(safeUrl);
        this.previewReady.emit();
      } else {
        const studentName = student?.StudentName?.replace(/\s+/g, '_') || 'student';
        this.pdf.downloadDoc(doc, `fee_receipt_${studentName}_${receiptRow.FeeID}.pdf`);
        this.downloaded.emit();
      }
    } catch (err: any) {
      const detail = err?.message || 'Failed to generate fee receipt.';
      if (this.toast) {
        this.toast.add({ severity: 'error', summary: 'Receipt', detail });
      }
      this.failed.emit(detail);
    } finally {
      this.loadingSignal.set(false);
    }
  }

  private selectReceiptRow(rows: StudentFeeLedgerRow[]): StudentFeeLedgerRow | undefined {
    if (this.studentFeeId) {
      const exact = rows.find(r => r.StudentFeeID === this.studentFeeId);
      if (exact) {
        return exact;
      }
    }
    const paidRows = rows.filter(r => r.FeeID === this.feeId && (r.Status || '').toLowerCase() === 'paid');
    if (paidRows.length) {
      return paidRows[0];
    }
    return rows.find(r => r.FeeID === this.feeId);
  }
}
