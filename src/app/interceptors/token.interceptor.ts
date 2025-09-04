import { Injectable } from '@angular/core';
import { HttpEvent, HttpHandler, HttpInterceptor, HttpRequest, HttpErrorResponse } from '@angular/common/http';
import { Observable, BehaviorSubject, throwError } from 'rxjs';
import { catchError, filter, switchMap, take } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';

@Injectable()
export class TokenInterceptor implements HttpInterceptor {
    private refreshing = false;
    private refreshSubject: BehaviorSubject<string | null> = new BehaviorSubject<string | null>(null);

    constructor(private auth: AuthService) {}

    intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
        const token = this.auth.getToken();
        let authReq = req;
        if (token) {
            authReq = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
        }

        return next.handle(authReq).pipe(
            catchError((err: any) => {
                if (err instanceof HttpErrorResponse && err.status === 401) {
                    // attempt refresh
                    return this.handle401Error(authReq, next);
                }
                return throwError(() => err);
            })
        );
    }

    private handle401Error(req: HttpRequest<any>, next: HttpHandler) {
        if (!this.refreshing) {
            this.refreshing = true;
            this.refreshSubject.next(null);

            return this.auth.refreshToken().pipe(
                switchMap((res: any) => {
                    this.refreshing = false;
                    // Persist tokens if needed
                    this.auth.processLoginResponse(res);
                    const newToken = this.auth.getToken();
                    this.refreshSubject.next(newToken);
                    const cloned = req.clone({ setHeaders: { Authorization: `Bearer ${newToken}` } });
                    return next.handle(cloned);
                }),
                catchError((err) => {
                    this.refreshing = false;
                    this.auth.logout();
                    return throwError(() => err);
                })
            );
        } else {
            return this.refreshSubject.pipe(
                filter((token) => token != null),
                take(1),
                switchMap((token) => {
                    const cloned = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
                    return next.handle(cloned as HttpRequest<any>);
                })
            );
        }
    }
}
