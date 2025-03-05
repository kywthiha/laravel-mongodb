<?php

namespace App\Models\MongoDB\Passport;

use Laravel\Passport\PersonalAccessClient as PassportPersonalAccessClient;
use MongoDB\Laravel\Eloquent\DocumentModel;

class PersonalAccessClient extends PassportPersonalAccessClient
{
    use DocumentModel;
}
