import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AcademicCalander } from './academic-calander';

describe('AcademicCalander', () => {
    let component: AcademicCalander;
    let fixture: ComponentFixture<AcademicCalander>;

    beforeEach(async () => {
        await TestBed.configureTestingModule({
            imports: [AcademicCalander]
        }).compileComponents();

        fixture = TestBed.createComponent(AcademicCalander);
        component = fixture.componentInstance;
        fixture.detectChanges();
    });

    it('should create', () => {
        expect(component).toBeTruthy();
    });
});
