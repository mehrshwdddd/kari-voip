<?php

namespace App\Models;

use App\Enums\UserStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

final class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'status',
        'last_login_at',
        'last_login_ip',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'last_login_at' => 'datetime',
            'settings' => 'array',
            'password' => 'hashed',
            'status' => UserStatusEnum::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function assignedContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'assigned_user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class);
    }
}
