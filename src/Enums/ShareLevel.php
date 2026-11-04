<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Enums;

enum ShareLevel: string
{
    case Read = 'read';
    case Edit = 'edit';
}
