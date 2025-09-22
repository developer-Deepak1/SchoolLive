import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FeesReport } from './fees-report';

describe('FeesReport', () => {
  let component: FeesReport;
  let fixture: ComponentFixture<FeesReport>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FeesReport]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FeesReport);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
