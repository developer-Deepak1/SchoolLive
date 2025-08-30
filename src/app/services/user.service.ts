import { Injectable, OnDestroy } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { map } from 'rxjs/operators';

export interface AppUser {
    // common camelCase and snake_case variants included for convenience
    id?: number;
    user_id?: number;
    username?: string;
    email?: string;
    email_address?: string;

    firstName?: string;
    first_name?: string;
    middleName?: string | null;
    middle_name?: string | null;
    lastName?: string;
    last_name?: string;

    role?: string;
    role_id?: number;

    school_id?: number;
    schoolName?: string;
    school_name?: string;

    AcademicYearID?: number;
    academic_year_id?: number;

    is_first_login?: number | boolean;

    // allow other properties returned by the API
    [key: string]: any;
}

@Injectable({
    providedIn: 'root'
})
export class UserService implements OnDestroy {
    private storageKey = 'user';
    private userSubject = new BehaviorSubject<AppUser | null>(this.readUserFromStorage());

    constructor() {
        // Listen for storage events from other tabs/windows and update local subject
        window.addEventListener('storage', this.handleStorageEvent);
    }

    ngOnDestroy(): void {
        window.removeEventListener('storage', this.handleStorageEvent);
    }

    private handleStorageEvent = (ev: StorageEvent) => {
        if (!ev.key || ev.key !== this.storageKey) return;
        this.userSubject.next(this.readUserFromStorage());
    };

    private readUserFromStorage(): AppUser | null {
        const raw = localStorage.getItem(this.storageKey);
        if (!raw) return null;
        try {
            return JSON.parse(raw) as AppUser;
        } catch (e) {
            // If it's not JSON, treat the raw string as a simple token-bearing user object
            return { value: raw } as AppUser;
        }
    }

    /** Returns the current user or null (sync) */
    getUser(): AppUser | null {
        return this.userSubject.value;
    }

    /** Observable that emits whenever the stored user changes */
    getUser$(): Observable<AppUser | null> {
        return this.userSubject.asObservable();
    }

    /** Replace stored user (also updates subscribers) */
    setUser(user: AppUser | string): void {
        const raw = typeof user === 'string' ? user : JSON.stringify(user);
        localStorage.setItem(this.storageKey, raw);
        this.userSubject.next(this.readUserFromStorage());
    }

    /** Remove stored user and notify subscribers */
    clearUser(): void {
        localStorage.removeItem(this.storageKey);
        this.userSubject.next(null);
    }

    /** Convenience helpers */
    getUserId(): number | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.id as number) || (u.user_id as number) || null;
    }

    getUserEmail(): string | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.email as string) || (u.email_address as string) || null;
    }

    isLoggedIn(): boolean {
        return this.getUser() !== null;
    }

    getFirstName(): string | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.firstName as string) || (u.first_name as string) || null;
    }

    getMiddleName(): string | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.middleName as string) || (u.middle_name as string) || null;
    }

    getLastName(): string | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.lastName as string) || (u.last_name as string) || null;
    }

    getFullName(): string | null {
        const parts: string[] = [];
        const first = this.getFirstName();
        const middle = this.getMiddleName();
        const last = this.getLastName();
        if (first) parts.push(first);
        if (middle) parts.push(middle);
        if (last) parts.push(last);
        return parts.length ? parts.join(' ') : null;
    }

    getRole(): string | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.role as string) || null;
    }

    getRoleId(): number | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.role_id as number) || null;
    }

    getSchoolId(): number | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.school_id as number) || null;
    }

    getSchoolName(): string | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.schoolName as string) || (u.school_name as string) || null;
    }

    getAcademicYearId(): number | null {
        const u = this.getUser();
        if (!u) return null;
        return (u.AcademicYearID as number) || (u.academic_year_id as number) || null;
    }

    getUsername(): string | null {
        const u = this.getUser();
        if (!u) return null;
        // some APIs may return user_name instead of username
        const alt = (u as any)['user_name'];
        return (u.username as string) || (alt as string) || null;
    }

    getIsFirstLogin(): boolean {
        const u = this.getUser();
        if (!u) return false;
    const v = u.is_first_login;
    if (v === undefined || v === null) return false;
    // normalize to string for safe comparisons
    const s = String(v).toLowerCase();
    return s === '1' || s === 'true';
    }
}
