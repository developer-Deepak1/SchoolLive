// Utility helpers for working with dates in India Standard Time (Asia/Kolkata)
// Small, safe helpers that the app can import when formatting or serializing dates

export function toLocalYMDIST(d: any): string | null {
  if (!d) return null;
  const date = d instanceof Date ? d : new Date(d);
  if (Number.isNaN(date.getTime())) return null;
  // Use en-CA to produce ISO-like YYYY-MM-DD but in the Asia/Kolkata timezone
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
}

// Return an ISO timestamp representing 12:00 (noon) on the given date in IST.
// This is useful to avoid day-shift issues when backend expects an ISO datetime for a date.
export function toISOStringNoonIST(d: any): string | null {
  if (!d) return null;
  const date = d instanceof Date ? d : new Date(d);
  if (Number.isNaN(date.getTime())) return null;
  const ymd = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata', year: 'numeric', month: '2-digit', day: '2-digit' }).format(date);
  const [yy, mm, dd] = ymd.split('-').map(Number);
  // 12:00 IST is 06:30 UTC -> construct UTC time for the ISO string
  return new Date(Date.UTC(yy, mm - 1, dd, 6, 30, 0)).toISOString();
}
