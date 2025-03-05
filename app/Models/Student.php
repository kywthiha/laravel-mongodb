<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Student extends Model
{
    protected $connection = 'mongodb';

    protected $fillable = [
        'name',
        'address',
        'major',
        'year',
        'hello',
    ];
}
