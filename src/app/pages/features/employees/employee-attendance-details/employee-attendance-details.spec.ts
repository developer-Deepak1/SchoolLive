import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EmployeeAttendanceDetails } from './employee-attendance-details';

describe('EmployeeAttendanceDetails', () => {
  let component: EmployeeAttendanceDetails;
  let fixture: ComponentFixture<EmployeeAttendanceDetails>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EmployeeAttendanceDetails]
    })
    .compileComponents();

    fixture = TestBed.createComponent(EmployeeAttendanceDetails);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
