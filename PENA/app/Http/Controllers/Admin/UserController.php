<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', [
            'users' => AdminUser::query()->orderBy('name')->get(['id', 'name', 'email', 'created_at']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $email]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('pena_admin_users', 'email')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        AdminUser::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Usuário cadastrado.');
    }
}
