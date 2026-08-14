<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Expired = 'expired';
}
