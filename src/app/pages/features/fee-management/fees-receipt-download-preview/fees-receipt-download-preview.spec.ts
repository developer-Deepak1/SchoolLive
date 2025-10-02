import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { FeesReceiptDownloadPreview } from './fees-receipt-download-preview';
import { StudentFeesService } from '../services/student-fees.service';
import { StudentsService } from '../../services/students.service';
import { PdfService } from '@/services/pdf.service';
import { MessageService } from 'primeng/api';

describe('FeesReceiptDownloadPreview', () => {
  let component: FeesReceiptDownloadPreview;
  let fixture: ComponentFixture<FeesReceiptDownloadPreview>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FeesReceiptDownloadPreview],
      providers: [
        { provide: StudentFeesService, useValue: { getLedger: () => of([]) } },
        { provide: StudentsService, useValue: { getStudent: () => of(null) } },
        {
          provide: PdfService,
          useValue: {
            buildFeeReceiptDoc: () => ({}),
            downloadDoc: jasmine.createSpy('downloadDoc'),
            getDataUrl: () => Promise.resolve('data:application/pdf;base64,FAKE')
          }
        },
        { provide: MessageService, useValue: { add: jasmine.createSpy('add') } }
      ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FeesReceiptDownloadPreview);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
