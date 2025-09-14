import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SchoolAdminProfile } from './school-admin-profile';

describe('SchoolAdminProfile', () => {
  let component: SchoolAdminProfile;
  let fixture: ComponentFixture<SchoolAdminProfile>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SchoolAdminProfile]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SchoolAdminProfile);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
