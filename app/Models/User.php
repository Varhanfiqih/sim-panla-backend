<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // ─── Role Constants ───────────────────────────────────────────────────────
    const ROLE_SUPER_ADMIN   = 'Super Admin';
    const ROLE_ADMIN_IT      = 'Admin IT';
    const ROLE_KEPALA_SEKOLAH = 'Kepala Sekolah';
    const ROLE_GURU          = 'Guru';
    const ROLE_GURU_BK       = 'Guru BK';

    /**
     * Super Admin, Admin IT, dan Kepala Sekolah boleh masuk ke Web Panel.
     * Kepala Sekolah hanya bisa view (Read-Only) sesuai policy resource.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_IT, self::ROLE_KEPALA_SEKOLAH]);
    }

    // ─── Role Helper Methods ──────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdminIT(): bool
    {
        return $this->role === self::ROLE_ADMIN_IT;
    }

    public function isKepsek(): bool
    {
        return $this->role === self::ROLE_KEPALA_SEKOLAH;
    }

    public function isGuru(): bool
    {
        return $this->role === self::ROLE_GURU;
    }

    public function isGuroBK(): bool
    {
        return $this->role === self::ROLE_GURU_BK;
    }

    /** Apakah user termasuk staf admin (Super Admin atau Admin IT) */
    public function isStaff(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN_IT]);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nip',
        'name',
        'password',
        'password_changed_at',
        'role',
        'is_inval_piket',
        'profile_photo_path',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('password')) {
                $user->password_changed_at = now();
            }
        });
    }

    public function getWaliKelasAttribute()
    {
        return $this->homeroomClass ? (string) $this->homeroomClass->id : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
    ];

    protected $appends = [
        'wali_kelas',
        'profile_photo_url',
    ];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? url(Storage::disk('public')->url($this->profile_photo_path))
            : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'is_inval_piket' => 'boolean',
        ];
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'subject_user', 'user_nip', 'subject_id', 'nip', 'id');
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_user', 'user_nip', 'class_id', 'nip', 'id');
    }

    public function homeroomClass()
    {
        return $this->hasOne(SchoolClass::class, 'homeroom_teacher_id', 'nip');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'teacher_id', 'nip');
    }

    public function mobileNotifications()
    {
        return $this->hasMany(MobileNotification::class);
    }

    public function mobileDeviceTokens()
    {
        return $this->hasMany(MobileDeviceToken::class);
    }

    public function journals()
    {
        return $this->hasMany(Journal::class, 'nip_guru', 'nip');
    }
}
