<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** الموردون (المطبعة/المكتبة) وحسابات دخولهم */
class SupplierController extends Controller
{
    public function index()
    {
        return view('admin.finance.suppliers.index', [
            'suppliers' => Supplier::with('accounts')->withCount('orders')->orderByDesc('is_active')->orderBy('name')->get()
                ->each(fn ($s) => $s->setAttribute('totals', $s->balance())),
        ]);
    }

    public function create()
    {
        return view('admin.finance.suppliers.form', ['supplier' => new Supplier(['is_active' => true]), 'account' => null]);
    }

    public function store(Request $request)
    {
        [$data, $acc] = $this->validated($request);
        $supplier = Supplier::create($data);
        $this->saveAccount($supplier, null, $acc);

        return redirect()->route('admin.finance.suppliers.index')->with('status', 'تمت إضافة '.$supplier->name.'.');
    }

    public function edit(Supplier $supplier)
    {
        return view('admin.finance.suppliers.form', ['supplier' => $supplier, 'account' => $supplier->accounts()->first()]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $account = $supplier->accounts()->first();
        [$data, $acc] = $this->validated($request, $account);
        $supplier->update($data);
        $this->saveAccount($supplier, $account, $acc);

        return redirect()->route('admin.finance.suppliers.index')->with('status', 'تم حفظ بيانات '.$supplier->name.'.');
    }

    private function saveAccount(Supplier $supplier, ?User $account, array $acc): void
    {
        if (empty($acc['username'])) {
            if ($account && ! empty($acc['remove'])) {
                $account->delete();
            }

            return;
        }
        $fields = ['name' => $supplier->name, 'username' => $acc['username'], 'phone' => $supplier->phone, 'role' => 'supplier',
            'supplier_id' => $supplier->id, 'is_active' => $supplier->is_active && ! empty($acc['active'])];
        if (! empty($acc['password'])) {
            $fields['password'] = $acc['password'];
        }
        $account ? $account->update($fields) : User::create($fields);
    }

    private function validated(Request $request, ?User $account = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'default_page_price' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('users', 'username')->ignore($account?->id)],
            'password' => [$account ? 'nullable' : 'required_with:username', 'nullable', 'string', 'min:6', 'max:100'],
            'account_active' => ['boolean'],
            'remove_account' => ['boolean'],
        ], ['username.regex' => 'اسم المستخدم بأحرف إنجليزية وأرقام فقط.'], [
            'name' => 'اسم المطبعة', 'phone' => 'الجوال', 'default_page_price' => 'سعر الورقة الافتراضي', 'username' => 'اسم المستخدم', 'password' => 'كلمة المرور',
        ]);

        return [[
            'name' => $v['name'],
            'contact_name' => $v['contact_name'] ?? null,
            'phone' => Phone::normalize($v['phone'] ?? null) ?? ($v['phone'] ?? null),
            'default_page_price' => $v['default_page_price'] ?? null,
            'notes' => $v['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ], [
            'username' => $v['username'] ?? null,
            'password' => $v['password'] ?? null,
            'active' => $request->boolean('account_active', true),
            'remove' => $request->boolean('remove_account'),
        ]];
    }
}
