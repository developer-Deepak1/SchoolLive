export enum StreamType {
    SCIENCE = 'SCIENCE',
    COMMERCE = 'COMMERCE',
    ARTS = 'ARTS',
    NONE = 'NONE'
}

export interface Section {
    sectionId: number | undefined; // Changed from number | null to number | undefined
    sectionName: string;
    classId: number;
    sectionTeacherId?: number;
    maxStrength?: number;
    roomNumber?: string;
    shift?: string;
}

export interface Classes {
    ClassID: number; // Unique ID
    ClassName: string; // e.g., "10th Standard"
    ClassCode?: string; // e.g., "C10"
    ClassTeacherID?: number; // Teacher assigned
    Stream?: StreamType; // Science / Commerce / Arts
    MaxStrength?: number; // Maximum students
}
