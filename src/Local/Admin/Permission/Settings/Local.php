<?php

namespace Nails\Cdn\Driver\Local\Admin\Permission\Settings;

use Nails\Admin\Interfaces\Permission;

class Local implements Permission
{
    public function label(): string
    {
        return 'Can manage CDN: Local Filesystem settings';
    }

    public function group(): string
    {
        return 'Settings';
    }
}
