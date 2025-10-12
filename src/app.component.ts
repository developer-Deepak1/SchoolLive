import { Component } from '@angular/core';
import { RouterModule } from '@angular/router';
import { CommonModule } from '@angular/common';
import { SpinnerService } from './app/services/spinner.service';
import { ProgressSpinnerModule } from 'primeng/progressspinner';

@Component({
        selector: 'app-root',
        standalone: true,
        imports: [RouterModule, CommonModule, ProgressSpinnerModule],
        template: `
            <div class="global-spinner" *ngIf="(spinner.loading$ | async)">
                <div class="overlay"></div>
                <div class="spinner-wrap">
                    <p-progressSpinner></p-progressSpinner>
                </div>
            </div>
            <router-outlet></router-outlet>
        `
})
export class AppComponent { 
    constructor(public spinner: SpinnerService) {}
}
