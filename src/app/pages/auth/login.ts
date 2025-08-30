import { Component } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { CheckboxModule } from 'primeng/checkbox';
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { RippleModule } from 'primeng/ripple';
import { AppFloatingConfigurator } from '../../layout/component/app.floatingconfigurator';
import { AuthService } from '../../services/auth.service';
import { Router } from '@angular/router';

@Component({
    selector: 'app-login',
    standalone: true,
    imports: [CommonModule, ButtonModule, CheckboxModule, InputTextModule, PasswordModule, FormsModule, RouterModule, RippleModule, AppFloatingConfigurator],
    templateUrl: './login.html'
})
export class Login {
    username: string = '';

    email: string = '';

    password: string = '';

    checked: boolean = false;

    error: string | null = null;

    constructor(private auth: AuthService, private router: Router) {}

    signIn() {
        this.error = null;
        const payload: any = { password: this.password };

        // Determine which identifier the user entered. The template exposes a
        // single "Username / Email" input bound to `username`. If that input
        // contains an @ we treat it as an email and send `email` to the API.
        // If a separate `email` field was used, prefer that.
        const identifier = (this.email && this.email.trim()) ? this.email.trim() : (this.username && this.username.trim()) ? this.username.trim() : '';

        if (identifier) {
            const isEmail = identifier.includes('@');
            if (isEmail) {
                payload.email = identifier;
            } else {
                payload.username = identifier;
            }
        }

        this.auth.login(payload).subscribe({
            next: (resp: any) => {
                this.auth.processLoginResponse(resp);
                const params = new URLSearchParams(window.location.search);
                const returnUrl = params.get('returnUrl') || '/';
                this.router.navigateByUrl(returnUrl);
            },
            error: (err: any) => {
                this.error = err?.error?.message || 'Login failed. Please check your credentials.';
            }
        });
    }
}
