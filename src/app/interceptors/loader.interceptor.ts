import { Injectable } from '@angular/core';
import { HttpEvent, HttpHandler, HttpInterceptor, HttpRequest } from '@angular/common/http';
import { Observable } from 'rxjs';
import { finalize } from 'rxjs/operators';
import { LoaderService } from '../services/loader.service';

@Injectable()
export class LoaderInterceptor implements HttpInterceptor {
    constructor(private loader: LoaderService) {}

    intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
        // Do not show loader for refresh token calls to avoid UI flicker if desired
        const hideFor = ['/api/auth/refresh'];
        const shouldShow = !hideFor.some((p) => req.url.includes(p));

        if (shouldShow) this.loader.show();

        return next.handle(req).pipe(
            finalize(() => {
                if (shouldShow) this.loader.hide();
            })
        );
    }
}
