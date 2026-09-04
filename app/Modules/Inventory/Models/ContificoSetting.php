<?php

namespace App\Modules\Inventory\Models;

use App\Modules\TenantModel;

class ContificoSetting extends TenantModel
{
    protected $fillable = [
        'is_active',
        'api_key',
        'automatic_sync_enabled',
        'sync_interval_minutes',
        'batch_size',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'api_key' => 'encrypted',
            'automatic_sync_enabled' => 'boolean',
            'sync_interval_minutes' => 'integer',
            'batch_size' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'is_active' => false,
            'automatic_sync_enabled' => false,
            'sync_interval_minutes' => 60,
            'batch_size' => 100,
        ]);
    }

    public function maskedApiKey(): ?string
    {
        if (! $this->api_key) {
            return null;
        }

        return '••••••••' . mb_substr($this->api_key, -4);
    }
}
