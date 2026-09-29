<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'counterparty_id',
        'receipt_date',
        'document_number',
        'document_date',
        'status',
        'posted_at',
        'posted_by',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'document_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceiptLine::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Следующий свободный номер документа — простая сквозная нумерация вида
     * "ПОСТ-000001". Это лишь предзаполненное значение поля при создании —
     * пользователь может отредактировать номер вручную (как и order_number
     * у заказов), уникальность проверяется на уровне формы.
     */
    public static function generateNextNumber(): string
    {
        return 'ПОСТ-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
    }
}
