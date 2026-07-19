<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ProfileNotFoundException extends Exception
{
    public function toRequestException(): RequestException
    {
        return new RequestException(Http::response(['message' => $this->getMessage()], 404));
    }
}
