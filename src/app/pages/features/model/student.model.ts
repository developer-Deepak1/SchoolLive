export interface Student {
  StudentID?: number;
  Username?: string; // login username (from Tx_Users)
  StudentName: string;
  Gender: 'M' | 'F' | 'O';
  DOB: string; // ISO date
  SectionID?: number;
  ClassID?: number;
  SchoolID?: number;
  ClassName?: string;
  SectionName?: string;
  FatherName?: string;
  MotherName?: string;
  FatherContactNumber?: string;
  MotherContactNumber?: string;
  AdmissionDate?: string;
  Status?: string;
  SchoolName?: string;
}
