<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
    'user_id',
    'action',
    'table_name',
    'data',
    'ip_address',
];
}
