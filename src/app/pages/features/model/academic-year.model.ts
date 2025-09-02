export interface AcademicYear {
    AcademicYearID?: number | null;
    AcademicYearName: string;
    StartDate: string; // ISO date string (YYYY-MM-DD)
    EndDate: string;   // ISO date string
    Status?: string; // indicates active/current academic year
}

export interface AcademicYearResponse{
    success:Boolean,
    message?:String,
    data?:AcademicYear[]
}