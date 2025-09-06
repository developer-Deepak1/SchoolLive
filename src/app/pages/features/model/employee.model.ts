export interface Employee {
  EmployeeID?: number;
  EmployeeName: string;
  Gender?: 'M' | 'F' | 'O';
  DOB?: string;
  RoleID?: number;
  RoleName?: string;
  JoiningDate?: string;
  Salary?: number;
  Status?: string;
  Username?: string;
  ContactNumber?: string;
  EmailID?: string;
  // Optional parent / emergency contact fields
  FatherName?: string;
  FatherContact?: string;
  MotherName?: string;
  MotherContact?: string;
}
