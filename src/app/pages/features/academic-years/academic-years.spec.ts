import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AcademicYears } from './academic-years';

describe('AcademicYears', () => {
  let component: AcademicYears;
  let fixture: ComponentFixture<AcademicYears>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AcademicYears]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AcademicYears);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
