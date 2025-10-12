import { Injectable } from '@angular/core';
import pdfMake from 'pdfmake/build/pdfmake';
import pdfFonts from 'pdfmake/build/vfs_fonts';
import type { TDocumentDefinitions } from 'pdfmake/interfaces';
import type { StudentFeeLedgerEntry } from '@/pages/features/fee-management/services/student-fees.service';
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
  receipt: StudentFeeLedgerEntry,
    student: Student | null,
    opts?: { receiptDate?: string; institutionName?: string; issuedBy?: string; isRecurring?: boolean }
  ): TDocumentDefinitions {
    this.ensureFonts();
    const issueDate = opts?.receiptDate ?? new Date().toLocaleDateString();
    const schoolName = opts?.institutionName ?? student?.SchoolName ?? 'School';
  const studentName = student?.StudentName ?? 'Student';
    const studentIdText = student?.StudentID ? String(student.StudentID) : '-';
    const usernameText = student?.Username ?? '-';
    const classSection = [student?.ClassName, student?.SectionName].filter(Boolean).join(' - ');
  const receiptNumber = this.buildReceiptNumber(student?.SchoolID, student?.StudentID, receipt.studentFeeId);
  // Only show month label for recurring fees (caller should set opts.isRecurring = true when appropriate)
  const monthLabel = (opts?.isRecurring && receipt.dueDate) ? this.formatMonth(receipt.dueDate) : null;
  // Prepare fee text: only append the month label if it's not already present in the FeeName
  const rawFeeName = receipt.feeName ?? '';
  const feeText = (monthLabel && rawFeeName && !rawFeeName.toLowerCase().includes(monthLabel.toLowerCase()))
    ? `${rawFeeName} (${monthLabel})`
    : (rawFeeName || '-');

  const fineAmount = receipt.computedFine ?? receipt.fineAmount ?? 0;
  const totalAmount = Number(receipt.amount || 0) + Number(fineAmount) - Number(receipt.discountAmount || 0);
  const paidAmount = Number(receipt.amountPaid || 0);
  const outstanding = Number(receipt.outstanding ?? 0);
  const paymentDate = this.formatDate(receipt.collectedDate);

    return {
      info: {
        title: `Fee Receipt - ${studentName}`,
        subject: `Fee receipt for ${studentName}`
      },
      background: (_currentPage: number, pageSize: { width: number; height: number }) => {
        const watermark = 'schoollive.in';
        // place near bottom-left and rotate to diagonal across the page
        const x = pageSize.width * 0.1;
        const y = pageSize.height * 0.3;
        // return as `any` to satisfy pdfMake types while keeping rotation/absolutePosition
        const wm: any = {
          text: watermark,
          absolutePosition: { x, y },
          fontSize: 72,
          color: '#d1d5db',
          opacity: 0.08,
          bold: true,
          italics: true,
          rotation: -45
        };
        return wm as any;
      },
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
              // Bold label and title-case the student name
              { text: `Name of Student: ${this.toTitleCase(studentName)}`, bold: true },
              // Father's name (title-cased when present)
              { text: `Father's Name: ${this.toTitleCase(student?.FatherName ?? '-')}` },
              // Show the username as the Student ID alias per request
              { text: `Student ID: ${usernameText}` }
            ],
            [
              { text: `Class & Section: ${classSection || '-'}`, alignment: 'right' },
              { text: opts?.issuedBy ? `Issued By: ${opts.issuedBy}` : '', alignment: 'right' }
            ]
          ],
          columnGap: 24,
          margin: [0, 16, 0, 16]
        },
        {
          table: {
            widths: ['auto', '*', 'auto', 'auto', 'auto'],
            body: [
              [
                { text: 'Date of Payment', style: 'headerCell', alignment: 'center', fillColor: '#f3f4f6' },
                { text: 'Fee Name', style: 'headerCell', alignment: 'center', fillColor: '#f3f4f6' },
                { text: 'Amount', style: 'headerCell', alignment: 'center', fillColor: '#f3f4f6' },
                { text: 'Fine', style: 'headerCell', alignment: 'center', fillColor: '#f3f4f6' },
                { text: 'Discount', style: 'headerCell', alignment: 'center', fillColor: '#f3f4f6' }
              ],
              [
                { text: paymentDate, alignment: 'center' },
                { text: feeText, alignment: 'center' },
                { text: this.formatCurrency(receipt.amount), alignment: 'right' },
                { text: this.formatCurrency(fineAmount), alignment: 'right' },
                { text: this.formatCurrency(receipt.discountAmount), alignment: 'right' }
              ]
            ]
          },
          layout: {
            hLineWidth: function (i: number, node: any) { return 1; },
            vLineWidth: function (i: number, node: any) { return 1; },
            hLineColor: function (i: number, node: any) { return '#e5e7eb'; },
            vLineColor: function (i: number, node: any) { return '#e5e7eb'; },
            paddingLeft: function(i: number, node: any) { return 6; },
            paddingRight: function(i: number, node: any) { return 6; },
            paddingTop: function(i: number, node: any) { return 6; },
            paddingBottom: function(i: number, node: any) { return 6; }
          }
        },
        {
          stack: [
            { text: `Total Amount Paid: ${this.formatCurrency(paidAmount)}`, margin: [0, 16, 0, 0], style: 'totalPaid' },
            { text: `Total Payable: ${this.formatCurrency(totalAmount)}`, margin: [0, 4, 0, 0] },
            { text: `Outstanding Balance: ${this.formatCurrency(outstanding)}`, margin: [0, 4, 0, 16] }
          ]
        },
        { text: 'For any queries regarding fees, please contact the school office.', margin: [0, 0, 0, 4] },
        { text: 'Note: This is a computer-generated receipt.', italics: true }
      ],
      styles: {
        title: { fontSize: 18, bold: true, alignment: 'center' },
        subtitle: { fontSize: 14, alignment: 'center', margin: [0, 8, 0, 8] },
        headerCell: { bold: true },
        receiptMeta: { fontSize: 10, color: '#4b5563' },
        contactHeader: { fontSize: 12, bold: true, margin: [0, 0, 0, 6] },
        totalPaid: { bold: true }
      },
      defaultStyle: { fontSize: 11 }
    };
  }

  downloadDoc(docDefinition: TDocumentDefinitions, filename = 'fee-receipt.pdf'): void {
    this.ensureFonts();
    pdfMake.createPdf(docDefinition).download(filename);
  }

  openDoc(docDefinition: TDocumentDefinitions): void {
    this.ensureFonts();
    try {
      pdfMake.createPdf(docDefinition).open();
    } catch (e) {
      // last-resort: download
      pdfMake.createPdf(docDefinition).download('fee-receipt.pdf');
    }
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

  private formatDate(dateValue: string | Date | null | undefined): string {
    if (!dateValue) return '-';
    const d = new Date(dateValue);
    if (Number.isNaN(d.getTime())) {
      return '-';
    }
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  private toTitleCase(input: string): string {
    if (!input) return input;
    return input
      .toLowerCase()
      .split(' ')
      .filter(Boolean)
      .map(w => w[0].toUpperCase() + w.slice(1))
      .join(' ');
  }

  private buildReceiptNumber(schoolId?: number, studentId?: number, feeId?: number): string {
    const normalize = (val?: number) => (val !== undefined && val !== null ? String(val) : 'NA');
    return [normalize(schoolId), normalize(studentId), normalize(feeId)].join('');
  }
}
