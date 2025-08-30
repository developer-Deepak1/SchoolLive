import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddEditClasses } from './add-edit-classes';

describe('AddEditClasses', () => {
  let component: AddEditClasses;
  let fixture: ComponentFixture<AddEditClasses>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AddEditClasses]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AddEditClasses);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
