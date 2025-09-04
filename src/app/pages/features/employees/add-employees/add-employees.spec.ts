import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddEmployees } from './add-employees';

describe('AddEmployees', () => {
    let component: AddEmployees;
    let fixture: ComponentFixture<AddEmployees>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [AddEmployees]
        }).compileComponents();

        fixture = TestBed.createComponent(AddEmployees);
        component = fixture.componentInstance;
        fixture.detectChanges();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
