<?php

namespace App\Http\Controllers;

use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\SaveCustomer;
use App\Enums\CustomerType;
use App\Http\Requests\MasterData\CustomerRequest;
use App\Models\Customer;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('customers/index', [
            'customers' => Customer::query()
                // Phone first: staff look a customer up by the number on the slip.
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($q) => $q->where('phone', 'like', "%{$search}%")
                        ->orWhere('alt_phone', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('area', 'like', "%{$search}%")
                ))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('customers.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/create', ['types' => $this->typeOptions()]);
    }

    public function store(CustomerRequest $request, SaveCustomer $save): RedirectResponse
    {
        $save->handle($request->validated(), createdBy: $request->user()->id);

        return to_route('customers.index')->with('success', 'কাস্টমার যোগ করা হয়েছে।');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/edit', [
            'customer' => $customer,
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer, SaveCustomer $save): RedirectResponse
    {
        $save->handle($request->validated(), $customer);

        return to_route('customers.index')->with('success', 'কাস্টমারের তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Customer $customer, DeleteCustomer $delete): RedirectResponse
    {
        try {
            $delete->handle($customer);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কাস্টমার মুছে ফেলা হয়েছে।');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (CustomerType $type) => ['value' => $type->value, 'label' => $type->label()],
            CustomerType::cases()
        );
    }
}
