import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class SchoolsService {
  private base = environment.baseURL;

  constructor(private http: HttpClient) {}

  // GET /api/schools/{id}
  getSchoolById(id: number): Observable<any> {
    return this.http.get<any>(`${this.base}/api/schools/${id}`);
  }

  // GET /api/schools/by-user/{userId}
  getSchoolByUser(userId: number): Observable<any> {
    return this.http.get<any>(`${this.base}/api/schools/by-user/${userId}`);
  }
}
