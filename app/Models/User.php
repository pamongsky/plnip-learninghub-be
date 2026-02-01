<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'moodle_user_id',
        'phone',
        'department',
        'position',
        'avatar',
        'is_active',
        'source',
        'access_group',
        'role_override',
        'synced_at',
        'role_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['role'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
            'role_changed_at' => 'datetime',
        ];
    }

    /**
     * Get the user's primary role name
     * This accessor provides compatibility with code that expects $user->role
     */
    public function getRoleAttribute(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * Get effective role: use role_override if exists, else resolve from access_group
     */
    public function getEffectiveRole(): ?string
    {
        // If role_override is set, use it
        if ($this->role_override) {
            return $this->role_override;
        }

        // Otherwise resolve from access_group
        if ($this->access_group) {
            return $this->mapAccessGroupToRole($this->access_group);
        }

        // Fallback to first assigned role
        return $this->roles->first()?->name;
    }

    /**
     * Map access_group from ERP to portal role
     */
    private function mapAccessGroupToRole(string $accessGroup): ?string
    {
        $mapping = [
            'SUPERADMIN' => 'super-admin',
            'ADMIN_UNIT' => 'admin',
            'INSTRUCTOR' => 'instructor',
            'USER' => 'user',
        ];

        return $mapping[strtoupper($accessGroup)] ?? 'user';
    }

    /**
     * Get the courses enrolled by the user
     */
    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_enrollments')
            ->withPivot('status', 'enrolled_at', 'moodle_role_id')
            ->withTimestamps();
    }
}
