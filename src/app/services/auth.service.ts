import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { throwError } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
    providedIn: 'root'
})
export class AuthService {
    // The storage key(s) your app might use. Adjust as needed.
    // Keep 'user' last because it's often an object rather than a token.
    private tokenKeys = ['authToken', 'token', 'jwt'];
    private userKey = 'user';

    constructor(private router: Router, private http: HttpClient) {}

    getToken(): string | null {
        for (const k of this.tokenKeys) {
            const v = localStorage.getItem(k);
            if (v) return v;
        }

        // If no token-like keys, return the user object (string) if present
        const userVal = localStorage.getItem(this.userKey);
        if (userVal) return userVal;
        return null;
    }

    setToken(token: string, key: string = 'token') {
        localStorage.setItem(key, token);
    }

    setUser(user: object | string) {
        const v = typeof user === 'string' ? user : JSON.stringify(user);
        localStorage.setItem(this.userKey, v);
    }

    logout(redirect: string = '/auth/login') {
        // Remove known token keys and user data
        for (const k of this.tokenKeys) {
            localStorage.removeItem(k);
        }
        localStorage.removeItem(this.userKey);

        // Optionally, you can preserve other storage items if needed.

        // Navigate to login
        try {
            this.router.navigateByUrl(redirect);
        } catch (e) {
            // ignore navigation errors in contexts where Router may not be ready
        }
    }

    isLoggedIn(): boolean {
        const token = this.getToken();
        if (!token) return false;

        // If the token is a JWT, perform a basic expiry check.
        const isJwt = token.split('.').length === 3;
        if (isJwt) {
            try {
                const payload = JSON.parse(atob(token.split('.')[1]));
                if (payload && payload.exp) {
                    const now = Math.floor(Date.now() / 1000);
                    return payload.exp > now;
                }
                // If no exp claim, consider token valid (but you can change this)
                return true;
            } catch (e) {
                // bad JWT format -> not logged in
                return false;
            }
        }

        // If value came from userKey, try to parse and verify it contains meaningful data.
        if (token && token === localStorage.getItem(this.userKey)) {
            try {
                const obj = JSON.parse(token);
                // Consider logged in if object contains id, email or token property
                if (obj && (obj.id || obj.email || obj.token)) return true;
                return false;
            } catch (e) {
                // Not JSON — treat non-empty string as a token
                return token.trim().length > 0;
            }
        }

        // Non-JWT token string present
        return token.trim().length > 0;
    }

    /**
     * Process API login response and persist tokens/user.
     * Expected shape matches the sample you provided.
     */
    processLoginResponse(resp: any) {
        if (!resp) return false;

        // Some APIs wrap tokens in `data`, others return tokens at root.
        const data = resp.data || resp;
        if (!data) return false;

        const access = data.access_token || data.token || data.authToken || data.accessToken;
        const refresh = data.refresh_token || data.refreshToken;

        if (access) {
            this.setToken(access, 'token');
            this.setToken(access, 'authToken');
        }

        if (refresh) {
            this.setToken(refresh, 'refresh_token');
        }

        if (data.user) {
            this.setUser(data.user);
        }

        return true;
    }

    login(payload: { username?: string; email?: string; password?: string }) {
        const url = `${environment.baseURL}/api/auth/login`;
        localStorage.clear();
        return this.http.post(url, payload);
    }

    /**
     * Return the stored refresh token (if any).
     */
    getRefreshToken(): string | null {
        return localStorage.getItem('refresh_token');
    }

    /**
     * Call the refresh endpoint and update stored tokens on success.
     * Returns the observable so callers can subscribe and react to errors.
     */
    refreshToken() {
        const refresh = this.getRefreshToken();
        if (!refresh) {
            // No refresh token available — return an observable that errors.
            return throwError(() => new Error('No refresh token available'));
        }

        // Send the refresh token in the request body as { refresh_token: string }
        return this.http.post(`${environment.baseURL}/api/auth/refresh`, { refresh_token: refresh });
    }
}
