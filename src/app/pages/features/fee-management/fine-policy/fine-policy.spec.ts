import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FinePolicy } from './fine-policy';

describe('FinePolicy', () => {
  let component: FinePolicy;
  let fixture: ComponentFixture<FinePolicy>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FinePolicy]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FinePolicy);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
