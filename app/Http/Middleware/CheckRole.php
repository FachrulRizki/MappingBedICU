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

        $user = Auth::user();

        // Admin full access
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Flatten — support 'admin,petugas_icu' dalam satu string
        $required = array_merge(...array_map(
            fn ($r) => array_map('trim', explode(',', $r)),
            $roles
        ));

        if (in_array($user->role, $required, true)) {
            return $next($request);
        }

        // Role tidak cocok — redirect ke login dengan pesan
        return redirect()->route('login')
            ->with('error', 'Akun Anda tidak memiliki akses ke halaman ini.');
    }
}
