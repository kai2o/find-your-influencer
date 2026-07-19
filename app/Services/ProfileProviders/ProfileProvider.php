<?php

namespace App\Services\ProfileProviders;

use App\DataTransferObjects\ProfileData;

interface ProfileProvider
{
    public function fetch(string $username): ProfileData;
}
