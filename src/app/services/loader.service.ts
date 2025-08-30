import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class LoaderService {
    private _loading = new BehaviorSubject<boolean>(false);
    readonly loading$ = this._loading.asObservable();

    private count = 0;

    show() {
        this.count++;
        this._loading.next(true);
    }

    hide() {
        this.count = Math.max(0, this.count - 1);
        if (this.count === 0) this._loading.next(false);
    }

    reset() {
        this.count = 0;
        this._loading.next(false);
    }
}
