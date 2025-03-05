<?php

namespace App\Models\MongoDB\Passport;

use Laravel\Passport\AuthCode as PassportAuthCode;
use MongoDB\Laravel\Eloquent\DocumentModel;

class AuthCode extends PassportAuthCode
{
    use DocumentModel;

    protected $primaryKey = '_id';

    protected $keyType = 'string';
}
