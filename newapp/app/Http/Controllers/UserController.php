<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()?->isSuperadmin(), 403);

        $users = User::query()->orderBy('name')->paginate(20);

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isSuperadmin(), 403);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user->update(['role' => $validated['role']]);

        return back()->with('success', 'Role pengguna berhasil diperbarui.');
    }
}
