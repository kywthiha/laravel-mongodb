<?php

namespace App\Models\MongoDB\Passport;

use Laravel\Passport\RefreshToken as PassportRefreshToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

class RefreshToken extends PassportRefreshToken
{
    use DocumentModel;
}
