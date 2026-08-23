<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialHistory extends Model
{
    protected $fillable = ['material_id', 'type', 'quantity', 'description'];

}
