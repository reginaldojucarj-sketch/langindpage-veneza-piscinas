<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (config('database.default') === 'mysql' && ! config('database.connections.mysql.host')) {
            return back()->withErrors(['email' => 'O acesso ainda não está configurado.'])->onlyInput('email');
        }

        try {
            $authenticated = Auth::attempt($credentials);
        } catch (QueryException $error) {
            Log::error('Falha ao consultar os administradores.', ['exception' => $error]);

            return back()->withErrors(['email' => 'Acesso temporariamente indisponível.'])->onlyInput('email');
        }

        if (! $authenticated) {
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
