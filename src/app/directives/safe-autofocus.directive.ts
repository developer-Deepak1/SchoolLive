import { Directive, ElementRef, AfterViewInit } from '@angular/core';

@Directive({
  selector: '[safeAutofocus]',
  standalone: true
})
export class SafeAutofocusDirective implements AfterViewInit {
  constructor(private host: ElementRef<HTMLElement>) {}

  ngAfterViewInit(): void {
    const doc = (this.host.nativeElement.ownerDocument as Document) || document;
    const active = doc.activeElement;
    const body = doc.body;

    // Only focus if nothing else is focused (avoid "Autofocus processing was blocked" warnings)
    if (!active || active === body) {
      // Defer to next tick so the inner input (if any) is present
      setTimeout(() => {
        const el = this.host.nativeElement.querySelector('input, textarea, [tabindex], [contenteditable=true]') as HTMLElement | null;
        if (el && typeof el.focus === 'function') {
          try { el.focus(); } catch (e) { /* best-effort focus */ }
        }
      }, 0);
    }
  }
}
