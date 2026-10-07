<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\AdminAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly AdminAccounts $accounts) {}

    public function index(): View
    {
        Gate::authorize('manage-users');

        return view('admin.users', ['users' => AdminUser::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-users');
        $input = $request->only(['name', 'email', 'password', 'password_confirmation', 'role', 'legacy_person_id']);
        $input['role'] ??= 'editor';
        $input['is_active'] = true;
        $this->accounts->create($request->user(), $input);

        return redirect()->route('admin.users.index')->with('status', 'Usuário cadastrado. Entregue a senha por canal privado.');
    }

    public function edit(AdminUser $user): View
    {
        Gate::authorize('manage-users');

        return view('admin.user-edit', compact('user'));
    }

    public function update(Request $request, AdminUser $user): RedirectResponse
    {
        Gate::authorize('manage-users');
        $this->accounts->update($request->user(), $user->id,
            $request->only(['name', 'email', 'role', 'is_active', 'legacy_person_id', 'expected_version']));

        return redirect()->route('admin.users.index')->with('status', 'Usuário atualizado. Sessões anteriores serão encerradas.');
    }

    public function resetPassword(Request $request, AdminUser $user): RedirectResponse
    {
        Gate::authorize('manage-users');
        $this->accounts->changePassword($request->user(), $user->id,
            $request->only(['current_password', 'password', 'password_confirmation']));

        return redirect()->route('admin.users.edit', $user)->with('status', 'Senha redefinida. Entregue-a por canal privado; as sessões anteriores foram revogadas.');
    }

    public function passwordForm(): View
    {
        return view('admin.password');
    }

    public function passwordUpdate(Request $request): RedirectResponse
    {
        $this->accounts->changePassword($request->user(), $request->user()->id,
            $request->only(['current_password', 'password', 'password_confirmation']));

        return redirect()->route('admin.dashboard');
    }
}
