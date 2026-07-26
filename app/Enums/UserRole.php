<?php

namespace App\Enums;

enum UserRole: string
{
    case CUSTOMER = 'customer';
    case PROVIDER = 'provider';
    case ADMIN = 'admin';
}
