<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Cheque extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'contact_id',
        'cheque_number',
        'bank_name',
        'branch_name',
        'account_number',
        'amount',
        'due_date',
        'status',
        'deposited_at',
        'cleared_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'deposited_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
