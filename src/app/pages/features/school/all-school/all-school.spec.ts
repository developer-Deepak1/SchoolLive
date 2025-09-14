import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AllSchool } from './all-school';

describe('AllSchool', () => {
  let component: AllSchool;
  let fixture: ComponentFixture<AllSchool>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AllSchool]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AllSchool);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
