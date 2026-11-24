<?php

namespace App;

enum TotemStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Revoked = 'revoked';
}
