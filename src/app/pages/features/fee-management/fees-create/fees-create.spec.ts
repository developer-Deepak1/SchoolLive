import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FeesCreate } from './fees-create';

describe('FeesCreate', () => {
  let component: FeesCreate;
  let fixture: ComponentFixture<FeesCreate>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FeesCreate]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FeesCreate);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
