import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Classes, StreamType } from '../model/classes.model';

@Injectable({
    providedIn: 'root'
})
export class ClassesService {
    private http = inject(HttpClient);

    getClasses(): Promise<Classes[]> {
        const demoData: Classes[] = [
            {
                classId: 1,
                className: "1st Standard",
                classCode: "C01",
                academicYear: "2025-26",
                classTeacherId: 101,
                stream: StreamType.NONE,
                maxStrength: 40,
                sections: [
                    {
                        sectionId: 1,
                        sectionName: "A",
                        classId: 1,
                        sectionTeacherId: 201,
                        maxStrength: 20,
                        roomNumber: "101",
                        shift: "Morning"
                    },
                    {
                        sectionId: 2,
                        sectionName: "B",
                        classId: 1,
                        sectionTeacherId: 202,
                        maxStrength: 20,
                        roomNumber: "102",
                        shift: "Morning"
                    }
                ]
            },
            {
                classId: 2,
                className: "2nd Standard",
                classCode: "C02",
                academicYear: "2025-26",
                classTeacherId: 102,
                stream: StreamType.NONE,
                maxStrength: 40,
                sections: []
            },
            {
                classId: 3,
                className: "3rd Standard",
                classCode: "C03",
                academicYear: "2025-26",
                classTeacherId: 103,
                stream: StreamType.NONE,
                maxStrength: 45,
                sections: []
            },
            {
                classId: 4,
                className: "4th Standard",
                classCode: "C04",
                academicYear: "2025-26",
                classTeacherId: 104,
                stream: StreamType.NONE,
                maxStrength: 45,
                sections: []
            },
            {
                classId: 5,
                className: "5th Standard",
                classCode: "C05",
                academicYear: "2025-26",
                classTeacherId: 105,
                stream: StreamType.NONE,
                maxStrength: 50,
                sections: []
            },
            {
                classId: 6,
                className: "6th Standard",
                classCode: "C06",
                academicYear: "2025-26",
                classTeacherId: 106,
                stream: StreamType.NONE,
                maxStrength: 50,
                sections: []
            },
            {
                classId: 7,
                className: "7th Standard",
                classCode: "C07",
                academicYear: "2025-26",
                classTeacherId: 107,
                stream: StreamType.NONE,
                maxStrength: 55,
                sections: []
            },
            {
                classId: 8,
                className: "8th Standard",
                classCode: "C08",
                academicYear: "2025-26",
                classTeacherId: 108,
                stream: StreamType.NONE,
                maxStrength: 55,
                sections: []
            },
            {
                classId: 9,
                className: "9th Standard",
                classCode: "C09",
                academicYear: "2025-26",
                classTeacherId: 109,
                stream: StreamType.NONE,
                maxStrength: 60,
                sections: []
            },
            {
                classId: 10,
                className: "10th Standard",
                classCode: "C10",
                academicYear: "2025-26",
                classTeacherId: 110,
                stream: StreamType.NONE,
                maxStrength: 60,
                sections: []
            },
            {
                classId: 11,
                className: "11th Standard",
                classCode: "C11",
                academicYear: "2025-26",
                classTeacherId: 111,
                stream: StreamType.SCIENCE,
                maxStrength: 65,
                sections: [
                    {
                        sectionId: 21,
                        sectionName: "Science-A",
                        classId: 11,
                        sectionTeacherId: 211,
                        maxStrength: 32,
                        roomNumber: "301",
                        shift: "Morning"
                    },
                    {
                        sectionId: 22,
                        sectionName: "Science-B",
                        classId: 11,
                        sectionTeacherId: 212,
                        maxStrength: 33,
                        roomNumber: "302",
                        shift: "Morning"
                    }
                ]
            },
            {
                classId: 12,
                className: "12th Standard",
                classCode: "C12",
                academicYear: "2025-26",
                classTeacherId: 112,
                stream: StreamType.COMMERCE,
                maxStrength: 65,
                sections: [
                    {
                        sectionId: 23,
                        sectionName: "Commerce-A",
                        classId: 12,
                        sectionTeacherId: 213,
                        maxStrength: 32,
                        roomNumber: "401",
                        shift: "Morning"
                    },
                    {
                        sectionId: 24,
                        sectionName: "Arts-A",
                        classId: 12,
                        sectionTeacherId: 214,
                        maxStrength: 33,
                        roomNumber: "402",
                        shift: "Morning"
                    }
                ]
            }
        ];

        return Promise.resolve(demoData);
    }

    // Add these methods when implementing actual API integration
    createClass(class_: Classes) {
        // return this.http.post<Classes>('/api/classes', class_);
    }

    updateClass(class_: Classes) {
        // return this.http.put<Classes>(`/api/classes/${class_.classId}`, class_);
    }

    deleteClass(classId: number) {
        // return this.http.delete(`/api/classes/${classId}`);
    }
}
