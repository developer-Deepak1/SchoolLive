import { ComponentFixture, TestBed } from '@angular/core/testing';
import { NoopAnimationsModule } from '@angular/platform-browser/animations';
import { of } from 'rxjs';

import { FeesUpcoming } from './fees-upcoming';
import { StudentsService } from '../../services/students.service';
import { StudentFeesService } from '../services/student-fees.service';
import { UserService } from '@/services/user.service';

class StudentsServiceStub {
  getStudents() { return of([]); }
}

class StudentFeesServiceStub {
  getLedger() { return of([]); }
}

class UserServiceStub {
  getRoleId() { return null; }
  getStudentId() { return null; }
}

describe('FeesUpcoming', () => {
  let component: FeesUpcoming;
  let fixture: ComponentFixture<FeesUpcoming>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FeesUpcoming, NoopAnimationsModule],
      providers: [
        { provide: StudentsService, useClass: StudentsServiceStub },
        { provide: StudentFeesService, useClass: StudentFeesServiceStub },
        { provide: UserService, useClass: UserServiceStub }
      ]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FeesUpcoming);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
