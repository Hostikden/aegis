<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOperation extends Model
{
    protected $fillable = ['product_id', 'operation_number', 'operation_name', 'description'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
