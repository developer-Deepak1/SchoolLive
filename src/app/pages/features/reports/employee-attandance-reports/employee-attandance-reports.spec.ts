import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EmployeeAttandanceReports } from './employee-attandance-reports';

describe('EmployeeAttandanceReports', () => {
    let component: EmployeeAttandanceReports;
    let fixture: ComponentFixture<EmployeeAttandanceReports>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [EmployeeAttandanceReports]
        }).compileComponents();

        fixture = TestBed.createComponent(EmployeeAttandanceReports);
        component = fixture.componentInstance;
        fixture.detectChanges();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
