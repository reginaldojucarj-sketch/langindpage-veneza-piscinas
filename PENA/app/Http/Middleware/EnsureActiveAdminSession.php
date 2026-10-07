<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = AdminUser::find($request->user()?->getAuthIdentifier());
        if (! $user || ! $user->is_active || ! in_array($user->role, ['admin', 'editor'], true) ||
            $request->session()->get('pena_auth_version') !== $user->auth_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sessão expirada. Entre novamente.'], 401);
            }

            return redirect()->route('admin.login')->withErrors(['email' => 'Sessão expirada. Entre novamente.']);
        }

        Auth::setUser($user);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
