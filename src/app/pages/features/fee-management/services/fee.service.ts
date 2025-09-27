import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import { UserService } from '@/services/user.service';

export interface FeeData {
  FeeID?: number;
  FeeName: string;
  IsActive: boolean;
  SchoolID?: number;
  AcademicYearID?: number;
  CreatedAt?: Date;
  UpdatedAt?: Date;
  CreatedBy?: string;
  UpdatedBy?: string;
  Schedule?: {
    ScheduleID?: number;
    FeeID?: number;
    ScheduleType: string;
    IntervalMonths?: number;
    DayOfMonth?: number;
  StartDate?: string | Date;
  EndDate?: string | Date;
  NextDueDate?: string | Date;
    ReminderDaysBefore?: number;
    CreatedAt?: string;
    UpdatedAt?: string;
    CreatedBy?: string;
    UpdatedBy?: string;
  } | null;
  ClassSectionMapping?: Array<{
    MappingID?: number;
    FeeID?: number;
    ClassID: number;
    SectionID: number;
    Amount?: number;
    IsActive?: boolean;
    CreatedAt?: string;
    UpdatedAt?: string;
    CreatedBy?: string;
    UpdatedBy?: string;
  }>;
}

export interface FeeWithClassSections extends FeeData {
  classCount?: number;
}

@Injectable({
  providedIn: 'root'
})
export class FeeService {
  private http = inject(HttpClient);
  private userService = inject(UserService);
  private baseUrl = `${environment.baseURL.replace(/\/+$/, '')}/api/fees`;

  /**
   * Get all fees with optional filters
   */
  getFees(): Observable<FeeWithClassSections[]> {
    const url = `${this.baseUrl}/list`;

    return this.http.get<any>(url).pipe(
      map(response => {
        if (response && response.success && response.data) {
          return response.data.map((fee: any) => this.mapApiResponseToFee(fee));
        }
        return [];
      })
    );
  }

  /**
   * Get a specific fee by ID
   */
  getFee(id: number): Observable<FeeWithClassSections | null> {
    return this.http.get<any>(`${this.baseUrl}/${id}`).pipe(
      map(response => {
        if (response && response.success && response.data) {
          return this.mapApiResponseToFee(response.data);
        }
        return null;
      })
    );
  }

  /**
   * Create a new fee
   */
  createFee(feeData: FeeData): Observable<FeeWithClassSections | null> {
    const payload = this.mapFeeToApiRequest(feeData);

    return this.http.post<any>(`${this.baseUrl}/create`, payload).pipe(
      map(response => {
        if (response && response.success && response.data) {
          return this.mapApiResponseToFee(response.data);
        }
        return null;
      })
    );
  }

  /**
   * Update an existing fee
   */
  updateFee(feeData: FeeData): Observable<FeeWithClassSections | null> {
    if (!feeData.FeeID) {
      throw new Error('Fee ID is required for update');
    }

    const payload = this.mapFeeToApiRequest(feeData);

    return this.http.put<any>(`${this.baseUrl}/update/${feeData.FeeID}`, payload).pipe(
      map(response => {
        if (response && response.success && response.data) {
          return this.mapApiResponseToFee(response.data);
        }
        return null;
      })
    );
  }

  /**
   * Toggle fee active status
   */
  toggleFeeStatus(id: number, isActive: boolean): Observable<boolean> {
    return this.http.patch<any>(`${this.baseUrl}/${id}/status`, { IsActive: isActive }).pipe(
      map(response => response && response.success === true)
    );
  }

  /**
   * Delete a fee
   */
  deleteFee(id: number): Observable<boolean> {
    return this.http.delete<any>(`${this.baseUrl}/${id}`).pipe(
      map(response => response && response.success === true)
    );
  }

  /**
   * Get fees by frequency
   */
  getFeesByFrequency(frequency: string): Observable<FeeWithClassSections[]> {
    const url = `${this.baseUrl}/frequency/${frequency}`;

    return this.http.get<any>(url).pipe(
      map(response => {
        if (response && response.success && response.data) {
          return response.data.map((fee: any) => this.mapApiResponseToFee(fee));
        }
        return [];
      })
    );
  }

  /**
   * Get fees by status
   */
  getFeesByStatus(status: 'active' | 'inactive'): Observable<FeeWithClassSections[]> {
    const url = `${this.baseUrl}/status/${status}`;

    return this.http.get<any>(url).pipe(
      map(response => {
        if (response && response.success && response.data) {
          return response.data.map((fee: any) => this.mapApiResponseToFee(fee));
        }
        return [];
      })
    );
  }

  /**
   * Map API response to fee object
   */
  private mapApiResponseToFee(api: any): FeeWithClassSections {
    const mappings = api.ClassSectionMapping || [];
    let classCount = 0;
    if (Array.isArray(mappings) && mappings.length > 0) {
      const s = new Set<number>();
      for (const m of mappings) {
        const id = m.ClassID;
        if (id !== undefined && id !== null) s.add(Number(id));
      }
      classCount = s.size;
    } else if (typeof api.classCount === 'number') {
      classCount = api.classCount;
    }

    return {
      FeeID: api.FeeID,
      FeeName: api.FeeName || '',
      IsActive: Boolean(api.IsActive),
      AcademicYearID: api.AcademicYearID,
      SchoolID: api.SchoolID,
      CreatedAt: api.CreatedAt ? new Date(api.CreatedAt) : undefined,
      UpdatedAt: api.UpdatedAt ? new Date(api.UpdatedAt) : undefined,
      CreatedBy: api.CreatedBy,
      UpdatedBy: api.UpdatedBy,
      Schedule: api.Schedule ?? null,
      ClassSectionMapping: mappings,
      classCount
    };
  }

  /**
   * Map fee object to API request format
   */
  private mapFeeToApiRequest(fee: FeeData): any {
    const payload: any = {
      FeeName: fee.FeeName,
      IsActive: fee.IsActive,
    };

    if (fee.ClassSectionMapping && fee.ClassSectionMapping.length) {
      payload.ClassSectionMapping = fee.ClassSectionMapping.map(m => {
        const out: any = {
          ClassID: m.ClassID,
          SectionID: m.SectionID,
          Amount: m.Amount
        };
        if ((m as any).MappingID) out.MappingID = (m as any).MappingID;
        return out;
      });
    }

    if (fee.Schedule) {
      payload.Schedule = {
        ScheduleType: fee.Schedule.ScheduleType,
        IntervalMonths: fee.Schedule.IntervalMonths,
        DayOfMonth: fee.Schedule.DayOfMonth,
        StartDate: fee.Schedule.StartDate,
        EndDate: fee.Schedule.EndDate,
        NextDueDate: fee.Schedule.NextDueDate,
        ReminderDaysBefore: fee.Schedule.ReminderDaysBefore
      };
    }

    return payload;
  }
}