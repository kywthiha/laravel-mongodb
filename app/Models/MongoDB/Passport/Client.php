<?php

namespace App\Models\MongoDB\Passport;

use Laravel\Passport\Client as PassportClient;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Client extends PassportClient
{
    use DocumentModel;
}
