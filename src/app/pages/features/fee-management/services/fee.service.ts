import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '../../../../../environments/environment';
import { UserService } from '@/services/user.service';

export interface FeeData {
  feeId?: number;
  feeName: string;
  frequency: string;
  startDate: Date;
  lastDueDate: Date;
  amount: number;
  isActive: boolean;
  status: string;
  classSectionMapping?: Array<{classId: number, sectionId: number, amount?: number}>;
  academicYearId?: number;
  schoolId?: number;
  createdAt?: Date;
  updatedAt?: Date;
  createdBy?: string;
  updatedBy?: string;
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
    if (!feeData.feeId) {
      throw new Error('Fee ID is required for update');
    }

    const payload = this.mapFeeToApiRequest(feeData);

    return this.http.put<any>(`${this.baseUrl}/update/${feeData.feeId}`, payload).pipe(
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
    return this.http.patch<any>(`${this.baseUrl}/${id}/status`, { isActive }).pipe(
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
  private mapApiResponseToFee(apiData: any): FeeWithClassSections {
    // Normalize classSections returned by the API (controller returns grouped structure)
    const classSections = apiData.classSections || apiData.ClassSections || [];

    // classCount should reflect number of unique classes represented in mappings
    let classCount = 0;
    if (Array.isArray(classSections) && classSections.length > 0) {
      const uniqueClassIds = new Set<number>();
      classSections.forEach((cs: any) => {
        const cid = cs.classId ?? cs.ClassID ?? null;
        if (cid !== null && cid !== undefined) uniqueClassIds.add(Number(cid));
      });
      classCount = uniqueClassIds.size;
    } else if (typeof apiData.classCount === 'number') {
      classCount = apiData.classCount;
    }

    return {
      feeId: apiData.FeeID || apiData.feeId,
      feeName: apiData.FeeName || apiData.feeName || '',
      frequency: apiData.Frequency || apiData.frequency || '',
  startDate: new Date(apiData.StartDate || apiData.startDate || new Date()),
  lastDueDate: new Date(apiData.LastDueDate || apiData.lastDueDate || new Date()),
      amount: parseFloat(apiData.Amount || apiData.amount || apiData.BaseAmount || 0),
      isActive: Boolean(apiData.IsActive !== undefined ? apiData.IsActive : apiData.isActive),
      status: apiData.Status || apiData.status || 'Active',
      academicYearId: apiData.AcademicYearID || apiData.academicYearId,
      schoolId: apiData.SchoolID || apiData.schoolId,
      createdAt: apiData.FeeCreatedAt || apiData.CreatedAt ? new Date(apiData.FeeCreatedAt || apiData.CreatedAt) : undefined,
      updatedAt: apiData.FeeUpdatedAt || apiData.UpdatedAt ? new Date(apiData.FeeUpdatedAt || apiData.UpdatedAt) : undefined,
      createdBy: apiData.FeeCreatedBy || apiData.CreatedBy || apiData.createdBy,
      updatedBy: apiData.FeeUpdatedBy || apiData.UpdatedBy || apiData.updatedBy,
      classCount: classCount
    };
  }

  /**
   * Map fee object to API request format
   */
  private mapFeeToApiRequest(fee: FeeData): any {
    const mappings = fee.classSectionMapping || [];

    // Detect if any mapping provides its own amount (with actual value)
    const mappingsHaveAmount = mappings.some(m => 
      (m.hasOwnProperty('amount') && typeof m.amount === 'number' && m.amount > 0) ||
      (m.hasOwnProperty('Amount') && typeof (m as any).Amount === 'number' && (m as any).Amount > 0)
    );

    const payload: any = {
      feeName: fee.feeName,
      frequency: fee.frequency,
      startDate: fee.startDate.toISOString().split('T')[0], // Format as YYYY-MM-DD
      lastDueDate: fee.lastDueDate.toISOString().split('T')[0],
      isActive: fee.isActive,
      status: fee.status || 'Active',
      classSectionMapping: mappings
      // schoolId and academicYearId will be handled by backend from user session
    };

    // Only include top-level amount when mappings do NOT provide per-mapping amounts
    if (!mappingsHaveAmount && typeof fee.amount === 'number') {
      payload.amount = fee.amount;
    }

    return payload;
  }
}