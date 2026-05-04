<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use App\Models\AmoAccount;
use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class HasDomain extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $validated = $request->validate(['domain' => ['required', 'string']]);
        $account = AmoAccount::query()->where(['domain' => $validated['domain']])->first();

        if (empty($account)) {
            return Controller::error('Invalid account');
        }

        $request->merge(['amoAccount' => $account]);

        return $next($request);
    }

}
