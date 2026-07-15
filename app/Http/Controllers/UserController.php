<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::orderBy('role')->orderBy('name')->get()
            ->map(fn ($u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'username'      => $u->username,
                'email'         => $u->email,
                'role'          => $u->role,
                'role_label'    => $u->roleLabel(),
                'role_color'    => $u->roleColor(),
                'unit_kerja'    => $u->unit_kerja,
                'ward_ids'      => $u->getWardIdsArray(),
                'is_active'     => $u->is_active,
                'auth_provider' => $u->auth_provider ?? 'local',
                'created_at'    => $u->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Settings/Users', [
            'users' => $users,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    /** Update ward_ids dan status aktif — role & nama dikelola Keycloak. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->update($validated);

        return back()->with('success', "User {$user->name} berhasil diperbarui.");
    }
}
