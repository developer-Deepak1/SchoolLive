import { Injectable } from '@angular/core';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
import type { TDocumentDefinitions } from 'pdfmake/interfaces';
import type { StudentFeeLedgerRow } from '@/pages/features/fee-management/services/student-fees.service';
import type { Student } from '@/pages/features/model/student.model';

@Injectable({ providedIn: 'root' })
export class PdfService {
  private fontsReady = false;

  private ensureFonts() {
    if (this.fontsReady) return;
    const anyPdfMake = pdfMake as any;
    if (!anyPdfMake.vfs) {
      anyPdfMake.vfs = (pdfFonts as any).pdfMake?.vfs ?? (pdfFonts as any).vfs;
    }
    this.fontsReady = true;
  }

  buildFeeReceiptDoc(
    receipt: StudentFeeLedgerRow,
    student: Student | null,
    opts?: { receiptDate?: string; institutionName?: string; issuedBy?: string }
  ): TDocumentDefinitions {
    this.ensureFonts();
    const issueDate = opts?.receiptDate ?? new Date().toLocaleDateString();
    const schoolName = opts?.institutionName ?? student?.SchoolName ?? 'School';
    const studentName = student?.StudentName ?? 'Student';
    const classSection = [student?.ClassName, student?.SectionName].filter(Boolean).join(' - ');
  const receiptNumber = this.buildReceiptNumber(student?.SchoolID, student?.StudentID, receipt.StudentFeeID);
  const monthLabel = receipt.DueDate ? this.formatMonth(receipt.DueDate) : null;

    const fineAmount = receipt.ComputedFine ?? receipt.FineAmount ?? 0;
    const totalAmount = Number(receipt.Amount || 0) + Number(fineAmount) - Number(receipt.DiscountAmount || 0);
    const paidAmount = Number(receipt.AmountPaid || 0);
    const outstanding = Number(receipt.Outstanding ?? 0);

    return {
      info: {
        title: `Fee Receipt - ${studentName}`,
        subject: `Fee receipt for ${studentName}`
      },
      background: (_currentPage: number, pageSize: { width: number; height: number }) => ({
        text: 'schoollive.in',
        color: '#d1d5db',
        opacity: 0.08,
        bold: true,
        italics: true,
        fontSize: 72,
        alignment: 'center',
        margin: [0, pageSize.height / 2 - 36, 0, 0]
      }),
      content: [
        {
          columns: [
            { text: `Receipt No: ${receiptNumber}`, alignment: 'left', style: 'receiptMeta' },
            { text: `Date: ${issueDate}`, alignment: 'right', style: 'receiptMeta' }
          ],
          margin: [0, 0, 0, 12]
        },
        { text: schoolName, style: 'title' },
        { text: 'Fee Receipt', style: 'subtitle' },
        {
          columns: [
            [
              { text: `Name of Student: ${studentName}` },
              { text: `Class & Section: ${classSection || '-'}` }
            ],
            [
              { text: monthLabel ? `Fee: ${receipt.FeeName} (${monthLabel})` : `Fee: ${receipt.FeeName}`, alignment: 'right' },
              { text: `Status: ${receipt.Status}`, alignment: 'right' }
            ]
          ],
          columnGap: 24,
          margin: [0, 16, 0, 16]
        },
        {
          table: {
            widths: ['*', 'auto'],
            body: [
              [ { text: 'Description', style: 'headerCell' }, { text: 'Amount', style: 'headerCell', alignment: 'right' } ],
              [ 'Fee Amount', { text: this.formatCurrency(receipt.Amount), alignment: 'right' } ],
              [ 'Fine', { text: this.formatCurrency(fineAmount), alignment: 'right' } ],
              [ 'Discount', { text: this.formatCurrency(receipt.DiscountAmount), alignment: 'right' } ],
              [ 'Outstanding', { text: this.formatCurrency(outstanding), alignment: 'right' } ],
              [ 'Total Payable', { text: this.formatCurrency(totalAmount), alignment: 'right', bold: true } ],
              [ 'Amount Paid', { text: this.formatCurrency(paidAmount), alignment: 'right', bold: true } ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: `Due Date: ${receipt.DueDate || '-'}`, margin: [0, 16, 0, 0] },
        { text: opts?.issuedBy ? `Issued By: ${opts.issuedBy}` : '', margin: [0, 4, 0, 0] },
        { text: 'For any queries regarding fees, please contact the school office.', margin: [0, 4, 0, 0] },
        { text: 'Note: This is a computer-generated receipt.', margin: [0, 12, 0, 0], italics: true }
      ],
      styles: {
        title: { fontSize: 18, bold: true, alignment: 'center' },
        subtitle: { fontSize: 14, alignment: 'center', margin: [0, 8, 0, 8] },
        headerCell: { bold: true },
        receiptMeta: { fontSize: 10, color: '#4b5563' },
        contactHeader: { fontSize: 12, bold: true, margin: [0, 0, 0, 6] }
      },
      defaultStyle: { fontSize: 11 }
    };
  }

  downloadDoc(docDefinition: TDocumentDefinitions, filename = 'fee-receipt.pdf'): void {
    this.ensureFonts();
    pdfMake.createPdf(docDefinition).download(filename);
  }

  async getDataUrl(docDefinition: TDocumentDefinitions): Promise<string> {
    this.ensureFonts();
    return new Promise((resolve, reject) => {
      pdfMake.createPdf(docDefinition).getDataUrl((dataUrl: string) => {
        if (!dataUrl) {
          reject(new Error('Unable to generate PDF preview'));
        } else {
          resolve(dataUrl);
        }
      });
    });
  }

  private formatCurrency(value: number | null | undefined): string {
    const amount = Number(value ?? 0);
    return amount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  private formatMonth(dateValue: string | Date): string | null {
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) {
      return null;
    }
    return d.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
  }

  private buildReceiptNumber(schoolId?: number, studentId?: number, feeId?: number): string {
    const normalize = (val?: number) => (val !== undefined && val !== null ? String(val) : 'NA');
    return [normalize(schoolId), normalize(studentId), normalize(feeId)].join('');
  }
}
