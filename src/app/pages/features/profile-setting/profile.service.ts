import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  constructor(private http: HttpClient) {}

  changePassword(userId: number | string, oldPassword: string, newPassword: string): Observable<any> {
    const payload = { userId,oldPassword, newPassword };
    return this.http.post(`${environment.baseURL}/api/users/changepassword`, payload);
  }

  getProfile(userId: number | string) {
    return this.http.get(`${environment.baseURL}/api/users/${userId}`);
  }

  updateProfile(userId: number | string, payload: any) {
    return this.http.put(`${environment.baseURL}/api/users/${userId}`, payload);
  }
}
