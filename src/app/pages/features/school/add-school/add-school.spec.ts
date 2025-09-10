import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddSchool } from './add-school';

describe('AddSchool', () => {
  let component: AddSchool;
  let fixture: ComponentFixture<AddSchool>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AddSchool]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AddSchool);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
