<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Staff can see paused categories and their products. Works on public routes too,
     * where the auth middleware does not run, by checking the Sanctum token directly.
     */
    protected function isStaff(Request $request): bool
    {
        return in_array($request->user('sanctum')?->role, ['admin', 'manager'], true);
    }
}
