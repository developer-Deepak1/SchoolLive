import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StudentAdmission } from './student-admission';

describe('StudentAdmission', () => {
    let component: StudentAdmission;
    let fixture: ComponentFixture<StudentAdmission>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [StudentAdmission]
        }).compileComponents();

        fixture = TestBed.createComponent(StudentAdmission);
        component = fixture.componentInstance;
        fixture.detectChanges();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
