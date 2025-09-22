import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CollectFees } from './collect-fees';

describe('CollectFees', () => {
  let component: CollectFees;
  let fixture: ComponentFixture<CollectFees>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CollectFees]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CollectFees);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
