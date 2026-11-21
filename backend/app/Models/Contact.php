<?php

namespace App\Models;

use App\Enums\ContactSourceEnum;
use App\Enums\ContactStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'first_name',
        'last_name',
        'national_id',
        'phone_primary',
        'phone_secondary',
        'email',
        'gender',
        'birth_date',
        'avatar',
        'status',
        'source',
        'assigned_user_id',
        'tags',
        'custom_fields',
        'credit_balance',
        'loyalty_points',
        'total_debt',
        'last_contact_at',
        'notes',
        'is_vip',
        'created_by',
    ];

    protected $casts = [
        'phone_secondary' => 'array',
        'tags' => 'array',
        'custom_fields' => 'array',
        'credit_balance' => 'decimal:2',
        'loyalty_points' => 'integer',
        'total_debt' => 'decimal:2',
        'birth_date' => 'date',
        'last_contact_at' => 'datetime',
        'is_vip' => 'boolean',
        'status' => ContactStatusEnum::class,
        'source' => ContactSourceEnum::class,
    ];

    protected $appends = ['full_name'];

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}"),
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tagPivots(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_tag_pivot', 'contact_id', 'tag_id');
    }

    public function notesList(): HasMany
    {
        return $this->hasMany(ContactNote::class)->orderByDesc('is_pinned')->latest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ContactFile::class)->latest();
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class)->latest('started_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->latest('starts_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('issue_date');
    }

    public function accountingLogs(): HasMany
    {
        return $this->hasMany(AccountingLog::class)->latest('created_at');
    }
}
