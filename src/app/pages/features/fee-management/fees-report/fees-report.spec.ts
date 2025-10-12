import { ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { FeesReport } from './fees-report';
import { StudentsService } from '../../services/students.service';
import { StudentFeesService } from '../services/student-fees.service';
import { FeeService } from '../services/fee.service';

describe('FeesReport', () => {
  let component: FeesReport;
  let fixture: ComponentFixture<FeesReport>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FeesReport],
      providers: [
        {
          provide: StudentsService,
          useValue: { getStudents: () => of([]) }
        },
        {
          provide: StudentFeesService,
          useValue: { getLedger: () => of([]) }
        },
        {
          provide: FeeService,
          useValue: { getFees: () => of([]) }
        }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(FeesReport);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
