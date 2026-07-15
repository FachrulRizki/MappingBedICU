<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $userRole = Auth::user()->role;

        $required = array_merge(...array_map(
            fn ($r) => array_map('trim', explode(',', $r)),
            $roles
        ));

        if ($userRole && in_array($userRole, $required, true)) {
            return $next($request);
        }

        return redirect()->route('login')
            ->with('error', 'Akun Anda tidak memiliki akses ke halaman ini.');
    }
}
