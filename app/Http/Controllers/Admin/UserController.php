<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const ROLES = ['admin', 'manager', 'customer'];

    /**
     * Query params: search (name/email), role, per_page.
     */
    public function index(Request $request)
    {
        $query = $this->withStats(User::query());

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        $users = $query->latest()->orderByDesc('id')->paginate(min((int) $request->query('per_page', 15), 100));

        $counts = User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        return response()->json([...$users->toArray(), 'counts' => $counts]);
    }

    public function show(User $user)
    {
        $user = $this->withStats(User::whereKey($user->id))->firstOrFail();
        $user->load(['orders' => fn ($q) => $q->withCount('items')->latest()]);

        return response()->json($user);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
            'role' => ['sometimes', Rule::in(self::ROLES)],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($user->is($request->user()) && isset($data['role']) && $data['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot remove your own admin role.'], 422);
        }

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted']);
    }

    private function withStats($query)
    {
        return $query
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->billable()], 'total')
            ->withMax('orders as last_order_at', 'created_at');
    }
}
