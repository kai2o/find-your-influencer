<?php

namespace App\Enums;

enum ProfileStatus: string
{
    case Pending = 'pending';
    case Fetching = 'fetching';
    case Fetched = 'fetched';
    case Failed = 'failed';
}
