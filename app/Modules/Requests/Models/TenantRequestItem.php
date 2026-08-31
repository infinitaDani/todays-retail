<?php

namespace App\Modules\Requests\Models;

use App\Modules\Products\Models\Product;
use App\Modules\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRequestItem extends TenantModel
{
    protected $fillable = ['tenant_request_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(TenantRequest::class, 'tenant_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
