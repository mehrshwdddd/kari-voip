<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class CampaignWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'name',
        'nodes',
        'edges',
        'is_template',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'edges' => 'array',
            'is_template' => 'boolean',
        ];
    }
}
