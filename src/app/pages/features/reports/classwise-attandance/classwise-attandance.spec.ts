import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ClasswiseAttandance } from './classwise-attandance';

describe('ClasswiseAttandance', () => {
  let component: ClasswiseAttandance;
  let fixture: ComponentFixture<ClasswiseAttandance>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ClasswiseAttandance]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ClasswiseAttandance);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
