<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{

protected $fillable = ['order_id', 'operation_name', 'quantity_to_do', 'quantity_done', 'quantity_scrapped', 'status', 'operator_id'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function operator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    return $this->belongsTo(User::class, 'operator_id');
}

}

