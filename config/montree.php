<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Super admin host
    |--------------------------------------------------------------------------
    |
    | Hostname (without scheme) the super admin panel responds to. All
    | super_admin routes are bound to this host via Route::domain().
    |
    */
    'super_admin_host' => env('MONTREE_SUPER_ADMIN_HOST', 'admin.montree.test'),
];
