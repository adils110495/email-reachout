<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsBack;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings > Users. Super admin only (`can:manage-users` on the routes).
 *
 * role / is_active / modules are not fillable on User, so they are written
 * here with forceFill and nowhere else - no other form can grant them.
 */
class UserController extends Controller
{
    use RedirectsBack;

    public function index(Request $request): View
    {
        $statusOptions = ['active' => 'Active', 'inactive' => 'Inactive'];
        $activeStatus  = array_key_exists((string) $request->input('status'), $statusOptions) ? (string) $request->input('status') : null;

        $users = User::query()
            ->withCount('leads')
            ->when($activeStatus, fn ($q) => $q->where('is_active', $activeStatus === 'active'))
            ->orderBy('id')
            ->get();

        $data = [
            'users'         => $users,
            'statusOptions' => $statusOptions,
            'activeStatus'  => $activeStatus,
            'roles'         => User::ROLES,
            'modules'       => User::MODULES,
        ];

        if ($request->ajax()) {
            return view('users._table', $data);
        }

        return view('users.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = new User;
        $user->forceFill($this->attributes($request))->save();

        return redirect()->route('users.index', $this->redirectQuery($request))
            ->with('success', "User \"{$user->name}\" created.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user  = User::findOrFail($id);
        $attrs = $this->attributes($request, $user);

        // Never lock yourself out: your own role and status stay as they are.
        if ($user->is($request->user()) && ($attrs['role'] !== User::ROLE_SUPER_ADMIN || ! $attrs['is_active'])) {
            return back()->with('error', 'You cannot remove your own super admin access or deactivate yourself.');
        }

        $user->forceFill($attrs)->save();

        return redirect()->route('users.index', $this->redirectQuery($request))
            ->with('success', "User \"{$user->name}\" updated.");
    }

    /** Deletes the account and, through the foreign keys, its whole workspace. */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index', $this->redirectQuery($request))
            ->with('success', "User \"{$user->name}\" and all of their data deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            // An admin edit is not limited to once every 30 days (that is the
            // Profile page's rule) and does not use up the user's change.
            'username'  => User::usernameRules($user),
            // Required like at signup, so every account can be reached by email.
            'email'     => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            // Blank on edit keeps the current password.
            'password'  => [$user ? 'nullable' : 'required', 'string', 'min:6'],
            'role'      => ['required', Rule::in(array_keys(User::ROLES))],
            'modules'   => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(User::MODULES))],
        ]);

        $modules = array_values(array_unique($data['modules'] ?? []));

        $attrs = [
            'name'      => $data['name'],
            'username'  => $data['username'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'is_active' => $request->boolean('is_active'),
            // Super admin has everything anyway, and "all ticked" is stored as
            // null so modules added to the app later are on for them too.
            'modules'   => $data['role'] === User::ROLE_SUPER_ADMIN || count($modules) === count(User::MODULES)
                ? null
                : $modules,
        ];

        if (filled($data['password'] ?? null)) {
            $attrs['password'] = $data['password']; // hashed by the cast
        }

        return $attrs;
    }
}
