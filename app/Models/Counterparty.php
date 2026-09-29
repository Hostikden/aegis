<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counterparty extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'inn',
        'contact_person',
        'phone',
        'email',
        'code_1c',
        'notes',
    ];
}
