<?php

namespace App\Models\MongoDB\Passport;

use Laravel\Passport\Token as PassportToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Token extends PassportToken
{
    use DocumentModel;
}
