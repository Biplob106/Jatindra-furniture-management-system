<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\DeleteAccount;
use App\Actions\Accounts\DepositToAccount;
use App\Actions\Accounts\SaveAccount;
use App\Enums\AccountType;
use App\Http\Requests\MasterData\AccountRequest;
use App\Http\Requests\MasterData\DepositRequest;
use App\Models\Account;
use App\Models\Shop;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('accounts/index', [
            'accounts' => Account::query()
                ->with('shop:id,name')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('accounts.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounts/create', [
            'types' => $this->typeOptions(),
            'shops' => $this->shopOptions(),
        ]);
    }

    public function store(AccountRequest $request, SaveAccount $saveAccount): RedirectResponse
    {
        $saveAccount->handle($request->validated());

        return to_route('accounts.index')->with('success', 'হিসাব যোগ করা হয়েছে।');
    }

    public function edit(Request $request, Account $account): Response
    {
        return Inertia::render('accounts/edit', [
            'account' => $account,
            'types' => $this->typeOptions(),
            'shops' => $this->shopOptions(),
            'canDeposit' => $request->user()->can('transactions.record'),
        ]);
    }

    /**
     * Records the shop's own cash going into the account, so there is money to
     * pay wages, advances and tiffin from.
     */
    public function deposit(DepositRequest $request, Account $account, DepositToAccount $deposit): RedirectResponse
    {
        $validated = $request->validated();

        $deposit->handle(
            account: $account,
            amount: number_format((float) $validated['amount'], 2, '.', ''),
            txnDate: $validated['txn_date'],
            note: $validated['note'] ?? null,
            createdBy: $request->user()->id,
        );

        return to_route('accounts.edit', $account)->with('success', 'টাকা জমা হয়েছে।');
    }

    public function update(AccountRequest $request, Account $account, SaveAccount $saveAccount): RedirectResponse
    {
        $saveAccount->handle($request->validated(), $account);

        return to_route('accounts.index')->with('success', 'হিসাবের তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Account $account, DeleteAccount $deleteAccount): RedirectResponse
    {
        try {
            $deleteAccount->handle($account);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'হিসাব মুছে ফেলা হয়েছে।');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (AccountType $type) => ['value' => $type->value, 'label' => $type->label()],
            AccountType::cases()
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
