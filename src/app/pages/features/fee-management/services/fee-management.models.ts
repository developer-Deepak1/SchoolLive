export interface ApiSuccessResponse<T> {
  success: true;
  message?: string;
  data: T;
}

export interface ApiErrorResponse {
  success: false;
  message?: string;
  data?: unknown;
}

export type ApiResponse<T> = ApiSuccessResponse<T> | ApiErrorResponse;

export type ScheduleType = 'OneTime' | 'OnDemand' | 'Recurring';

export interface FeeSchedule {
  scheduleId?: number;
  feeId?: number;
  scheduleType: ScheduleType;
  intervalMonths?: number | null;
  dayOfMonth?: number | null;
  startDate?: string | null;
  endDate?: string | null;
  nextDueDate?: string | null;
  reminderDaysBefore?: number | null;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface ClassSectionMapping {
  mappingId?: number;
  feeId?: number;
  classId: number;
  sectionId: number;
  amount?: number | null;
  isActive: boolean;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface Fee {
  feeId: number;
  feeName: string;
  isActive: boolean;
  schoolId: number;
  academicYearId: number;
  createdAt?: string | null;
  updatedAt?: string | null;
  createdBy?: string | null;
  updatedBy?: string | null;
  schedule?: FeeSchedule | null;
  classSectionMappings: ClassSectionMapping[];
  classCount?: number;
}

export interface FeeRequestPayload {
  feeName: string;
  isActive: boolean;
  schedule?: FeeSchedule | null;
  classSectionMappings: ClassSectionMapping[];
}

export interface FeeStatusRequest {
  isActive: boolean;
}

export interface FinePolicy {
  finePolicyId: number;
  schoolId: number;
  academicYearId: number;
  feeId: number | null;
  applyType: 'Fixed' | 'PerDay' | 'Percentage';
  amount: number;
  graceDays: number;
  maxAmount: number | null;
  isActive: boolean;
  createdAt?: string | null;
  updatedAt?: string | null;
  createdBy?: string | null;
  updatedBy?: string | null;
}

export interface FinePolicyRequest {
  feeId: number | null;
  applyType: 'Fixed' | 'PerDay' | 'Percentage';
  amount: number;
  graceDays: number;
  maxAmount: number | null;
  isActive: boolean;
}

export interface StudentFeeLedgerEntry {
  studentFeeId: number;
  studentId: number;
  feeId: number;
  feeName: string;
  mappingId: number | null;
  amount: number;
  fineAmount: number;
  discountAmount: number;
  amountPaid: number;
  dueDate: string | null;
  status: 'Pending' | 'Partial' | 'Paid' | 'Overdue';
  invoiceRef: string | null;
  remarks: string | null;
  computedFine: number | null;
  outstanding: number | null;
  collectedDate: string | null;
}

export interface StudentFeePlanMeta {
  referenceYear: number;
  referenceMonth: number;
  generatedAt: string;
  academicYearStart: string | null;
  academicYearEnd: string | null;
}

export interface StudentFeePlanSummary {
  currentCount: number;
  upcomingCount: number;
  currentOutstanding: number;
  upcomingOutstanding: number;
}

export interface StudentFeePlan {
  meta: StudentFeePlanMeta;
  summary: StudentFeePlanSummary;
  current: StudentFeeLedgerEntry[];
  upcoming: StudentFeeLedgerEntry[];
}

export interface PaymentRequest {
  studentFeeId: number;
  paidAmount: number;
  mode: 'Cash' | 'Online' | 'Cheque' | 'UPI';
  transactionRef?: string | null;
  paymentDate?: string | null;
  discountDelta?: number | null;
}

export interface PaymentResultItem {
  studentFeeId: number;
  ok: boolean;
}

export interface PaymentBatchResponse {
  items: PaymentResultItem[];
}

export interface EnsureMonthlyRequestItem {
  feeId: number;
  year: number;
  month: number;
}

export interface EnsureMonthlyResponseItem extends EnsureMonthlyRequestItem {
  studentFeeId: number;
}

export interface EnsureMonthlyResponse {
  items: EnsureMonthlyResponseItem[];
}

export class FeeMapper {
  static fromApi(raw: unknown): Fee {
    const source = this.normaliseRecord(raw);
    const schedule = pick(source, 'schedule', 'Schedule') ?? null;

    const mappings = this.mapMappings(pick(source, 'classSectionMappings', 'classSectionMapping', 'ClassSectionMapping') ?? []);

    // Attempt to read classCount from source (number or numeric string). If missing, derive from active mappings.
    const rawClassCount = pick(source, 'classCount');
    let classCountValue: number | undefined;
    if (typeof rawClassCount === 'number') {
      classCountValue = Number(rawClassCount);
    } else if (typeof rawClassCount === 'string' && rawClassCount.trim() !== '') {
      const parsed = Number(rawClassCount);
      classCountValue = Number.isNaN(parsed) ? undefined : parsed;
    } else {
      // Fallback: count active mappings (unique class ids) to present meaningful class count
      const activeMappings = mappings.filter(m => !!m.isActive);
      const uniqueClassIds = Array.from(new Set(activeMappings.map(m => m.classId)));
      classCountValue = uniqueClassIds.length;
    }

    return {
      feeId: Number(pick(source, 'feeId', 'FeeID') ?? 0),
      feeName: String(pick(source, 'feeName', 'FeeName') ?? ''),
      isActive: Boolean(pick(source, 'isActive', 'IsActive') ?? false),
      schoolId: Number(pick(source, 'schoolId', 'SchoolID') ?? 0),
      academicYearId: Number(pick(source, 'academicYearId', 'AcademicYearID') ?? 0),
      createdAt: pick(source, 'createdAt', 'CreatedAt') ?? null,
      updatedAt: pick(source, 'updatedAt', 'UpdatedAt') ?? null,
      createdBy: pick(source, 'createdBy', 'CreatedBy') ?? null,
      updatedBy: pick(source, 'updatedBy', 'UpdatedBy') ?? null,
      schedule: schedule ? this.mapSchedule(schedule) : null,
      classSectionMappings: mappings,
      classCount: classCountValue
    };
  }

  static toRequest(model: FeeRequestPayload): Record<string, unknown> {
    return {
      FeeName: model.feeName,
      IsActive: model.isActive,
      ClassSectionMapping: model.classSectionMappings.map(mapping => ({
        MappingID: mapping.mappingId ?? null,
        FeeID: mapping.feeId ?? null,
        ClassID: mapping.classId,
        SectionID: mapping.sectionId,
        Amount: mapping.amount ?? null,
        IsActive: mapping.isActive
      })),
      Schedule: model.schedule ? {
        ScheduleID: model.schedule.scheduleId ?? null,
        FeeID: model.schedule.feeId ?? null,
        ScheduleType: model.schedule.scheduleType,
        IntervalMonths: model.schedule.intervalMonths ?? null,
        DayOfMonth: model.schedule.dayOfMonth ?? null,
        StartDate: toIsoDate(model.schedule.startDate),
        EndDate: toIsoDate(model.schedule.endDate),
        NextDueDate: toIsoDate(model.schedule.nextDueDate),
        ReminderDaysBefore: model.schedule.reminderDaysBefore ?? null
      } : null
    };
  }

  private static normaliseRecord(raw: unknown): Record<string, any> {
    if (!raw || typeof raw !== 'object') return {};
    return raw as Record<string, any>;
  }

  private static mapSchedule(schedule: unknown): FeeSchedule {
    const src = this.normaliseRecord(schedule);
    return {
      scheduleId: pick(src, 'scheduleId', 'ScheduleID'),
      feeId: pick(src, 'feeId', 'FeeID'),
      scheduleType: (pick(src, 'scheduleType', 'ScheduleType') ?? 'OneTime') as ScheduleType,
      intervalMonths: this.toNumericOrNull(pick(src, 'intervalMonths', 'IntervalMonths')),
      dayOfMonth: this.toNumericOrNull(pick(src, 'dayOfMonth', 'DayOfMonth')),
      startDate: toIsoDate(pick(src, 'startDate', 'StartDate')),
      endDate: toIsoDate(pick(src, 'endDate', 'EndDate')),
      nextDueDate: toIsoDate(pick(src, 'nextDueDate', 'NextDueDate')),
      reminderDaysBefore: this.toNumericOrNull(pick(src, 'reminderDaysBefore', 'ReminderDaysBefore')),
      createdAt: pick(src, 'createdAt', 'CreatedAt') ?? null,
      updatedAt: pick(src, 'updatedAt', 'UpdatedAt') ?? null
    };
  }

  private static mapMappings(mappings: unknown): ClassSectionMapping[] {
    if (!Array.isArray(mappings)) return [];
    return mappings.map(item => {
      const src = this.normaliseRecord(item);
      return {
        mappingId: pick(src, 'mappingId', 'MappingID'),
        feeId: pick(src, 'feeId', 'FeeID'),
        classId: Number(pick(src, 'classId', 'ClassID') ?? 0),
        sectionId: Number(pick(src, 'sectionId', 'SectionID') ?? 0),
        amount: this.toNumericOrNull(pick(src, 'amount', 'Amount')),
        isActive: Boolean(pick(src, 'isActive', 'IsActive') ?? true),
        createdAt: pick(src, 'createdAt', 'CreatedAt') ?? null,
        updatedAt: pick(src, 'updatedAt', 'UpdatedAt') ?? null
      } satisfies ClassSectionMapping;
    });
  }

  private static toNumericOrNull(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
      return null;
    }
    const num = Number(value);
    return Number.isNaN(num) ? null : num;
  }
}

export class FinePolicyMapper {
  static fromApi(raw: unknown): FinePolicy {
    const src = this.normalise(raw);
    return {
      finePolicyId: Number(pick(src, 'finePolicyId', 'FinePolicyID') ?? 0),
      schoolId: Number(pick(src, 'schoolId', 'SchoolID') ?? 0),
      academicYearId: Number(pick(src, 'academicYearId', 'AcademicYearID') ?? 0),
      feeId: (() => {
        const value = pick(src, 'feeId', 'FeeID');
        if (value === null || value === undefined || value === '') return null;
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
      })(),
      applyType: (pick(src, 'applyType', 'ApplyType') ?? 'Fixed') as FinePolicy['applyType'],
      amount: Number(pick(src, 'amount', 'Amount') ?? 0),
      graceDays: Number(pick(src, 'graceDays', 'GraceDays') ?? 0),
      maxAmount: this.toNumericOrNull(pick(src, 'maxAmount', 'MaxAmount')),
      isActive: Boolean(pick(src, 'isActive', 'IsActive') ?? true),
      createdAt: pick(src, 'createdAt', 'CreatedAt') ?? null,
      updatedAt: pick(src, 'updatedAt', 'UpdatedAt') ?? null,
      createdBy: pick(src, 'createdBy', 'CreatedBy') ?? null,
      updatedBy: pick(src, 'updatedBy', 'UpdatedBy') ?? null
    };
  }

  static toRequest(model: FinePolicyRequest): Record<string, unknown> {
    return {
      feeId: model.feeId,
      applyType: model.applyType,
      amount: model.amount,
      graceDays: model.graceDays,
      maxAmount: model.maxAmount,
      isActive: model.isActive
    };
  }

  private static normalise(raw: unknown): Record<string, any> {
    if (!raw || typeof raw !== 'object') return {};
    return raw as Record<string, any>;
  }

  private static toNumericOrNull(value: unknown): number | null {
    if (value === null || value === undefined || value === '') return null;
    const num = Number(value);
    return Number.isNaN(num) ? null : num;
  }
}

export class LedgerMapper {
  static fromApi(raw: unknown): StudentFeeLedgerEntry {
    const src = this.normalise(raw);
    return {
      studentFeeId: Number(pick(src, 'studentFeeId', 'StudentFeeID') ?? 0),
      studentId: Number(pick(src, 'studentId', 'StudentID') ?? 0),
      feeId: Number(pick(src, 'feeId', 'FeeID') ?? 0),
      feeName: String(pick(src, 'feeName', 'FeeName') ?? ''),
      mappingId: (() => {
        const value = pick(src, 'mappingId', 'MappingID');
        if (value === null || value === undefined || value === '') return null;
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
      })(),
      amount: Number(pick(src, 'amount', 'Amount') ?? 0),
      fineAmount: Number(pick(src, 'fineAmount', 'FineAmount') ?? 0),
      discountAmount: Number(pick(src, 'discountAmount', 'DiscountAmount') ?? 0),
      amountPaid: Number(pick(src, 'amountPaid', 'AmountPaid') ?? 0),
      dueDate: toIsoDate(pick(src, 'dueDate', 'DueDate')),
      status: (pick(src, 'status', 'Status') ?? 'Pending') as StudentFeeLedgerEntry['status'],
      invoiceRef: pick(src, 'invoiceRef', 'InvoiceRef') ?? null,
      remarks: pick(src, 'remarks', 'Remarks') ?? null,
      computedFine: (() => {
        const value = pick(src, 'computedFine', 'ComputedFine');
        if (value === null || value === undefined || value === '') return null;
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
      })(),
      outstanding: (() => {
        const value = pick(src, 'outstanding', 'Outstanding');
        if (value === null || value === undefined || value === '') return null;
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
      })(),
      collectedDate: toIsoDate(pick(src, 'collectedDate', 'CollectedDate'))
    };
  }

  private static normalise(raw: unknown): Record<string, any> {
    if (!raw || typeof raw !== 'object') return {};
    return raw as Record<string, any>;
  }
}

export class StudentFeePlanMapper {
  static fromApi(raw: unknown): StudentFeePlan {
    const src = (!raw || typeof raw !== 'object') ? {} : (raw as Record<string, unknown>);
    const metaRaw = (pick<Record<string, unknown>>(src, 'meta') ?? {}) as Record<string, unknown>;
    const summaryRaw = (pick<Record<string, unknown>>(src, 'summary') ?? {}) as Record<string, unknown>;
    const currentRaw = pick<unknown[]>(src, 'current') ?? [];
    const upcomingRaw = pick<unknown[]>(src, 'upcoming') ?? [];

    const referenceYear = this.toNumber(metaRaw['referenceYear'], new Date().getFullYear());
    const referenceMonth = this.toNumber(metaRaw['referenceMonth'], new Date().getMonth() + 1);
    const generatedAt = typeof metaRaw['generatedAt'] === 'string' ? metaRaw['generatedAt'] as string : new Date().toISOString();
    const academicYearStart = typeof metaRaw['academicYearStart'] === 'string' ? metaRaw['academicYearStart'] as string : null;
    const academicYearEnd = typeof metaRaw['academicYearEnd'] === 'string' ? metaRaw['academicYearEnd'] as string : null;

    const current = Array.isArray(currentRaw) ? currentRaw.map(item => LedgerMapper.fromApi(item)) : [];
    const upcoming = Array.isArray(upcomingRaw) ? upcomingRaw.map(item => LedgerMapper.fromApi(item)) : [];

    const summary: StudentFeePlanSummary = {
      currentCount: this.toNumber(summaryRaw['currentCount'], current.length),
      upcomingCount: this.toNumber(summaryRaw['upcomingCount'], upcoming.length),
      currentOutstanding: this.toNumber(summaryRaw['currentOutstanding'], current.reduce((sum, row) => sum + (row.outstanding ?? 0), 0)),
      upcomingOutstanding: this.toNumber(summaryRaw['upcomingOutstanding'], upcoming.reduce((sum, row) => sum + (row.outstanding ?? 0), 0))
    } satisfies StudentFeePlanSummary;

    return {
      meta: {
        referenceYear,
        referenceMonth,
        generatedAt,
        academicYearStart,
        academicYearEnd
      },
      summary,
      current,
      upcoming
    } satisfies StudentFeePlan;
  }

  private static toNumber(value: unknown, fallback: number): number {
    if (typeof value === 'number' && Number.isFinite(value)) {
      return value;
    }
    const parsed = Number(value);
    return Number.isNaN(parsed) ? fallback : parsed;
  }
}

export class PaymentMapper {
  static toRequest(model: PaymentRequest): Record<string, unknown> {
    return {
      studentFeeId: model.studentFeeId,
      paidAmount: model.paidAmount,
      mode: model.mode,
      transactionRef: model.transactionRef ?? null,
      paymentDate: toIsoDate(model.paymentDate),
      discountDelta: model.discountDelta ?? null
    };
  }

  static fromBatchResponse(raw: unknown): PaymentBatchResponse {
    const src = (!raw || typeof raw !== 'object') ? {} : (raw as Record<string, any>);
    const rawItems = pick(src, 'items') ?? [];
    const items = Array.isArray(rawItems) ? rawItems.map(item => {
      if (!item || typeof item !== 'object') {
        return { studentFeeId: 0, ok: false };
      }
      const record = item as Record<string, any>;
      return {
        studentFeeId: Number(pick(record, 'studentFeeId', 'StudentFeeID') ?? 0),
        ok: Boolean(pick(record, 'ok') ?? false)
      } satisfies PaymentResultItem;
    }) : [];

    return { items };
  }
}

function toIsoDate(value: unknown): string | null {
  if (!value && value !== 0) {
    return null;
  }

  if (typeof value === 'string') {
    if (!value.trim()) {
      return null;
    }
    // Already ISO string
    if (/^\d{4}-\d{2}-\d{2}$/.test(value.trim())) {
      return value.trim();
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return toIsoDate(date);
  }

  if (value instanceof Date) {
    if (Number.isNaN(value.getTime())) {
      return null;
    }
    const year = value.getFullYear();
    const month = `${value.getMonth() + 1}`.padStart(2, '0');
    const day = `${value.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  return null;
}

function pick<T>(record: Record<string, any>, ...keys: string[]): T | undefined {
  for (const key of keys) {
    if (key && Object.prototype.hasOwnProperty.call(record, key)) {
      const value = record[key];
      if (value !== undefined) {
        return value as T;
      }
    }
  }
  return undefined;
}
