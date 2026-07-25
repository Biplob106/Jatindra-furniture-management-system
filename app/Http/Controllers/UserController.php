<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateUser;
use App\Actions\Users\ToggleUserActive;
use App\Actions\Users\UpdateUser;
use App\Enums\Role;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->with('shop:id,name')
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role' => $user->getRoleNames()->first(),
                'shop' => $user->shop?->only(['id', 'name']),
                'last_login_at' => $user->last_login_at?->toDateTimeString(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'roles' => $this->roleOptions(),
            'shops' => $this->shopOptions(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): RedirectResponse
    {
        $createUser->handle(
            name: $request->string('name')->toString(),
            phone: $request->string('phone')->toString(),
            password: $request->string('password')->toString(),
            role: Role::from($request->string('role')->toString()),
            email: $request->input('email') ?: null,
            shopId: $request->integer('shop_id') ?: null,
        );

        return to_route('users.index')->with('success', 'ব্যবহারকারী যোগ করা হয়েছে।');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
                'shop_id' => $user->shop_id,
                'is_active' => $user->is_active,
                'role' => $user->getRoleNames()->first(),
            ],
            'roles' => $this->roleOptions(),
            'shops' => $this->shopOptions(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): RedirectResponse
    {
        $updateUser->handle(
            user: $user,
            name: $request->string('name')->toString(),
            phone: $request->string('phone')->toString(),
            role: Role::from($request->string('role')->toString()),
            email: $request->input('email') ?: null,
            shopId: $request->integer('shop_id') ?: null,
            isActive: $request->boolean('is_active'),
            password: $request->input('password') ?: null,
        );

        return to_route('users.index')->with('success', 'ব্যবহারকারীর তথ্য বদলানো হয়েছে।');
    }

    /**
     * Deactivate rather than delete. Users are referenced by operational rows.
     */
    public function destroy(Request $request, User $user, ToggleUserActive $toggle): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'নিজের অ্যাকাউন্ট বন্ধ করা যাবে না।');
        }

        if ($user->hasRole(Role::Owner->value) && User::role(Role::Owner->value)->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'শেষ মালিকের অ্যাকাউন্ট বন্ধ করা যাবে না।');
        }

        $toggle->handle($user, ! $user->is_active);

        return back()->with('success', $user->is_active ? 'অ্যাকাউন্ট চালু করা হয়েছে।' : 'অ্যাকাউন্ট বন্ধ করা হয়েছে।');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return array_map(
            fn (Role $role) => ['value' => $role->value, 'label' => $role->label()],
            Role::cases()
        );
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function shopOptions(): array
    {
        return Shop::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
            ->all();
    }
}
