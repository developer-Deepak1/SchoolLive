import { Injectable } from '@angular/core';
import { BehaviorSubject } from 'rxjs';

@Injectable({ providedIn: 'root' })
export class SpinnerService {
  private counter = 0;
  private _loading = new BehaviorSubject<boolean>(false);
  readonly loading$ = this._loading.asObservable();

  // Flicker prevention timings (ms)
  private showDelay = 150; // wait before showing spinner
  private minVisible = 250; // ensure spinner remains visible at least this long

  private showTimer: any = null;
  private hideTimer: any = null;
  private visibleSince = 0;

  show(): void {
    this.counter++;
    // If already visible, nothing to do
    if (this._loading.value) return;

    // Clear any pending hide timers
    if (this.hideTimer) {
      clearTimeout(this.hideTimer);
      this.hideTimer = null;
    }

    // Start show delay timer
    if (!this.showTimer) {
      this.showTimer = setTimeout(() => {
        this.showTimer = null;
        this.visibleSince = Date.now();
        this._loading.next(true);
      }, this.showDelay);
    }
  }

  hide(): void {
    if (this.counter > 0) this.counter--;

    // If there are still pending requests, don't hide
    if (this.counter > 0) return;

    // If spinner not visible yet but show timer pending, cancel show
    if (!this._loading.value && this.showTimer) {
      clearTimeout(this.showTimer);
      this.showTimer = null;
      return;
    }

    // If visible, ensure minimum visible duration
    if (this._loading.value) {
      const elapsed = Date.now() - this.visibleSince;
      const remaining = Math.max(0, this.minVisible - elapsed);
      if (remaining > 0) {
        if (this.hideTimer) clearTimeout(this.hideTimer);
        this.hideTimer = setTimeout(() => {
          this.hideTimer = null;
          this._loading.next(false);
        }, remaining);
        return;
      }
      this._loading.next(false);
      return;
    }
  }
}
