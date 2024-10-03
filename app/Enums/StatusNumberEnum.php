<?php

namespace App\Enums;

enum StatusNumberEnum: string
{
    case active = 'active';
    case pending = 'pending';
    case failed = 'failed';
    case payable = 'payable';
}
