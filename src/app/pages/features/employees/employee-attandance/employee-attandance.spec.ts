import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EmployeeAttandance } from './employee-attandance';

describe('EmployeeAttandance', () => {
    let component: EmployeeAttandance;
    let fixture: ComponentFixture<EmployeeAttandance>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [EmployeeAttandance]
        }).compileComponents();

        fixture = TestBed.createComponent(EmployeeAttandance);
        component = fixture.componentInstance;
        fixture.detectChanges();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
