export enum StreamType {
    SCIENCE = 'Science',
    COMMERCE = 'Commerce',
    ARTS = 'Arts',
    NONE = 'None'
}

export interface Section {
    sectionId: number | undefined;   // Changed from number | null to number | undefined
    sectionName: string;
    classId: number;
    sectionTeacherId?: number;
    maxStrength?: number;
    roomNumber?: string;
    shift?: string;
}

export interface Classes {
    classId: number;          // Unique ID
    className: string;        // e.g., "10th Standard"
    classCode?: string;       // e.g., "C10"
    academicYear?: string;    // e.g., "2025-26"
    classTeacherId?: number;  // Teacher assigned
    stream?: StreamType;      // Science / Commerce / Arts
    maxStrength?: number;     // Maximum students
    sections?: Section[];     // List of sections
}
