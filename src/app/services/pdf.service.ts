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

  buildFeeReceiptDoc(receipt: StudentFeeLedgerRow, student: Student | null, opts?: { receiptDate?: string; institutionName?: string; issuedBy?: string }): TDocumentDefinitions {
    this.ensureFonts();
    const issueDate = opts?.receiptDate ?? new Date().toLocaleDateString();
    const schoolName = opts?.institutionName ?? 'School';
    const studentName = student?.StudentName ?? 'Student';
    const classSection = [student?.ClassName, student?.SectionName].filter(Boolean).join(' - ');

  const fineAmount = receipt.ComputedFine ?? receipt.FineAmount ?? 0;
  const totalAmount = Number(receipt.Amount || 0) + Number(fineAmount) - Number(receipt.DiscountAmount || 0);
    const paidAmount = Number(receipt.AmountPaid || 0);
    const outstanding = Number(receipt.Outstanding ?? 0);

    return {
      info: {
        title: `Fee Receipt - ${studentName}`,
        subject: `Fee receipt for ${studentName}`
      },
      content: [
        { text: schoolName, style: 'title' },
        { text: 'Fee Receipt', style: 'subtitle' },
        {
          columns: [
            [
              { text: `Student: ${studentName}` },
              { text: `Student ID: ${student?.StudentID ?? ''}` },
              { text: `Class & Section: ${classSection || '-'}` }
            ],
            [
              { text: `Receipt Date: ${issueDate}`, alignment: 'right' },
              { text: `Fee: ${receipt.FeeName}`, alignment: 'right' },
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
              [ 'Total Payable', { text: this.formatCurrency(totalAmount), alignment: 'right', bold: true } ],
              [ 'Amount Paid', { text: this.formatCurrency(paidAmount), alignment: 'right', bold: true } ],
              [ 'Outstanding', { text: this.formatCurrency(outstanding), alignment: 'right' } ]
            ]
          },
          layout: 'lightHorizontalLines'
        },
        { text: `Due Date: ${receipt.DueDate || '-'}`, margin: [0, 16, 0, 0] },
        { text: opts?.issuedBy ? `Issued By: ${opts.issuedBy}` : '', margin: [0, 4, 0, 0] },
        { text: 'Thank you for your payment.', margin: [0, 24, 0, 0], italics: true }
      ],
      styles: {
        title: { fontSize: 18, bold: true, alignment: 'center' },
        subtitle: { fontSize: 14, alignment: 'center', margin: [0, 8, 0, 8] },
        headerCell: { bold: true }
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
}
