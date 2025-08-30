import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StudentAttandance } from './student-attandance';

describe('StudentAttandance', () => {
  let component: StudentAttandance;
  let fixture: ComponentFixture<StudentAttandance>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StudentAttandance]
    })
    .compileComponents();

    fixture = TestBed.createComponent(StudentAttandance);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
