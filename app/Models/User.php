<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    // Hanya Admin yang bisa akses Web Panel Filament
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'Admin';
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
        'role',
        'is_inval_piket',
    ];

    protected $appends = [
        'wali_kelas',
    ];

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
    ];

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

    public function journals()
    {
        return $this->hasMany(Journal::class, 'nip_guru', 'nip');
    }
}
