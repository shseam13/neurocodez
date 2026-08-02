<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Laravel 11+ ships this base class bare. Both traits are pulled back in
    // because every admin controller authorises through policies —
    // authorizeResource() and Gate::authorize() live here.
    use AuthorizesRequests, ValidatesRequests;
}
