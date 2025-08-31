export interface Student {
  StudentID?: number;
  StudentName: string;
  Gender: 'M' | 'F' | 'O';
  DOB: string; // ISO date
  SectionID?: number;
  ClassID?: number;
  ClassName?: string;
  SectionName?: string;
  FatherName?: string;
  MotherName?: string;
  FatherContactNumber?: string;
  MotherContactNumber?: string;
  AdmissionDate?: string;
  Status?: string;
}
