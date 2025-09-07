import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router } from '@angular/router';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { TagModule } from 'primeng/tag';
import { DividerModule } from 'primeng/divider';
import { AvatarModule } from 'primeng/avatar';
import { SkeletonModule } from 'primeng/skeleton';
import { RippleModule } from 'primeng/ripple';
import { ProfileService } from '../profile-setting/profile.service';
import { UserService } from '@/services/user.service';
import { SchoolsService } from '../services/schools.service';

@Component({
  selector: 'app-school-admin-profile',
  standalone: true,
  imports: [CommonModule, CardModule, ButtonModule, ToastModule, TagModule, DividerModule, AvatarModule, SkeletonModule, RippleModule],
  providers: [ProfileService, MessageService],
  templateUrl: './school-admin-profile.html',
  styleUrls: ['./school-admin-profile.scss']
})
export class SchoolAdminProfile implements OnInit {
  loading = signal<boolean>(true);
  user = signal<any | null>(null);
  school = signal<any | null>(null);
  // when used as profileSetting (own profile) the parent may set this to true
  profileSetting: boolean = false;

  constructor(private route: ActivatedRoute, private router: Router, private profileService: ProfileService, private userService: UserService, private schoolsService: SchoolsService, private msg: MessageService) {}

  ngOnInit(): void {
    const idParam = this.route.snapshot.queryParamMap.get('id') || this.route.snapshot.paramMap.get('id');
    const id = idParam ? Number(idParam) : NaN;
    if (this.profileSetting) {
      const uid = this.userService.getUserId();
      if (uid) this.loadProfile(uid);
      else { this.loading.set(false); this.msg.add({ severity: 'error', summary: 'Error', detail: 'User not found' }); }
    } else if (id && !isNaN(id) && id > 0) {
      this.loadProfile(id);
    } else {
      // fallback to current user if no id provided
      const uid = this.userService.getUserId();
      if (uid) this.loadProfile(uid);
      else { this.loading.set(false); }
    }
  }

  private loadProfile(id: number) {
    this.loading.set(true);
    this.profileService.getProfile(id).subscribe({
      next: (res: any) => {
        const data = res?.data || res?.user || res || null;
        this.user.set(data);
        // once user is set, try to load authoritative school data
        const schoolId = data?.SchoolID || data?.school_id || data?.SchoolId || null;
        if (schoolId) {
          this.schoolsService.getSchoolById(Number(schoolId)).subscribe({
            next: (sres: any) => {
              const sData = sres?.data || sres || null;
              this.school.set(sData);
              this.loading.set(false);
            },
            error: () => { this.loading.set(false); }
          });
        } else {
          // fallback: try get by user id
          const uid = data?.UserID || data?.UserId || data?.userId || data?.User || data?.UserID || this.userService.getUserId();
          if (uid) {
            this.schoolsService.getSchoolByUser(Number(uid)).subscribe({
              next: (sres: any) => { this.school.set(sres?.data || sres || null); this.loading.set(false); },
              error: () => { this.loading.set(false); }
            });
          } else {
            this.loading.set(false);
          }
        }
      },
      error: () => { this.loading.set(false); this.msg.add({ severity: 'error', summary: 'Error', detail: 'Failed to load user' }); }
    });
  }

  back() { this.router.navigate(['/']); }

  initials(name: string) {
    return (name || '').split(/\s+/).filter(Boolean).slice(0,2).map(p=>p[0]?.toUpperCase()).join('');
  }

  displayName(): string {
    const u = this.user();
    if (!u) return '';
    const parts: string[] = [];
    if (u.FirstName) parts.push(u.FirstName);
    if (u.MiddleName) parts.push(u.MiddleName);
    if (u.LastName) parts.push(u.LastName);
    const joined = parts.join(' ').trim();
    return joined || (u.Username || u.username || u.FullName || u.full_name || '');
  }

  statusSeverity(status?: string) {
    switch ((status || '').toLowerCase()) {
      case 'active': return 'success';
      case 'inactive': return 'danger';
      case 'pending': return 'warning';
      default: return 'info';
    }
  }
}
