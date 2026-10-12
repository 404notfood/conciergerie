<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->disabled_at) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ce compte a été suspendu par l’administrateur.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'Ce compte a été suspendu par l’administrateur.']);
        }

        return $next($request);
    }
}
