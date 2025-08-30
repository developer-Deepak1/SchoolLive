import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StudentAttandanceReports } from './student-attandance-reports';

describe('StudentAttandanceReports', () => {
  let component: StudentAttandanceReports;
  let fixture: ComponentFixture<StudentAttandanceReports>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StudentAttandanceReports]
    })
    .compileComponents();

    fixture = TestBed.createComponent(StudentAttandanceReports);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
