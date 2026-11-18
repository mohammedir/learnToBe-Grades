<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\GradeLevel;
use App\Models\PurchaseOrder;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseFlow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** المالية ← المشتريات: طلبات الشراء/التوريد من المطبعة */
class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->only(['supplier', 'status', 'grade', 'recipient', 'creator', 'from', 'to', 'q']);
        $q = PurchaseOrder::with(['supplier', 'section.gradeLevel', 'gradeLevel', 'subject', 'recipient', 'creator'])
            ->when($f['supplier'] ?? null, fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $v === 'open' ? $q->whereIn('status', ['pending', 'preparing', 'ready']) : $q->where('status', $v))
            ->when($f['grade'] ?? null, fn ($q, $v) => $q->where('grade_level_id', $v))
            ->when($f['recipient'] ?? null, fn ($q, $v) => $q->where('recipient_id', $v))
            ->when($f['creator'] ?? null, fn ($q, $v) => $q->where('created_by', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%{$v}%")->orWhere('details', 'like', "%{$v}%")->orWhere('recipient_name', 'like', "%{$v}%")
                ->orWhere('id', (int) preg_replace('/\D/', '', $v) ?: -1)));

        $sum = (clone $q)->where('status', '!=', 'cancelled')->sum('amount');
        $counts = PurchaseOrder::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        // عرض «اللوحة»: أعمدة حسب الحالة لمتابعة الطلبات المفتوحة بسهولة
        $view = $request->query('view', empty($f['status']) ? 'board' : 'list');
        $board = null;
        if ($view === 'board') {
            $board = collect(PurchaseOrder::FLOW)->mapWithKeys(fn ($s) => [$s => (clone $q)->where('status', $s)
                ->when($s === 'received', fn ($w) => $w->latest('received_at')->limit(12), fn ($w) => $w->orderByRaw('needed_by IS NULL')->orderBy('needed_by')->orderBy('id'))
                ->get()]);
        }

        return view('admin.finance.purchases.index', [
            'view' => $view,
            'board' => $board,
            'orders' => $view === 'board' ? null : $q->latest('id')->paginate(30)->withQueryString(),
            'sum' => (float) $sum,
            'counts' => $counts,
            'f' => $f,
            'suppliers' => Supplier::orderBy('name')->get(),
            'grades' => GradeLevel::orderBy('sort_order')->get(),
            'people' => $this->people(),
            'creators' => User::whereIn('role', ['admin', 'staff'])->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        if ($suppliers->isEmpty()) {
            return redirect()->route('admin.finance.suppliers.create')->with('warning', 'أضف المطبعة (المورد) أولاً، ثم أنشئ طلبات الشراء.');
        }
        $order = new PurchaseOrder(['pricing' => 'per_page', 'supplier_id' => $suppliers->first()->id, 'copies' => null,
            'unit_price' => $suppliers->first()->default_page_price]);
        // نسخ طلب سابق (مثلاً نفس الاختبار لشعبة أخرى)
        if ($request->filled('copy') && $src = PurchaseOrder::find($request->integer('copy'))) {
            $order->fill($src->only(['supplier_id', 'title', 'details', 'grade_level_id', 'section_id', 'subject_id', 'recipient_id', 'recipient_name', 'pricing', 'pages', 'copies', 'unit_price', 'amount']));
        }

        return view('admin.finance.purchases.form', $this->formData($order, $suppliers));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'pending';
        $data['status_at'] = now();
        $order = PurchaseOrder::create($data);
        $order->log($request->user(), 'created');

        return redirect()->route('admin.finance.purchases.show', $order)->with('status', "أُرسل الطلب {$order->number} للمطبعة. يمكنك إلغاؤه أو تعديله ما دام «بانتظار المطبعة».");
    }

    public function show(PurchaseOrder $purchase)
    {
        $purchase->load(['supplier', 'section.gradeLevel', 'gradeLevel', 'subject', 'recipient', 'creator', 'statusUser', 'events.user']);

        return view('admin.finance.purchases.show', ['order' => $purchase, 'targets' => PurchaseFlow::allowedTargets($purchase)]);
    }

    public function edit(PurchaseOrder $purchase)
    {
        if (! $purchase->isEditable()) {
            return redirect()->route('admin.finance.purchases.show', $purchase)->with('warning', 'لا يمكن تعديل الطلب بعد أن بدأت المطبعة العمل عليه.');
        }

        return view('admin.finance.purchases.form', $this->formData($purchase, Supplier::where('is_active', true)->orWhere('id', $purchase->supplier_id)->orderBy('name')->get()));
    }

    public function update(Request $request, PurchaseOrder $purchase)
    {
        $error = PurchaseFlow::update($purchase, $this->validated($request), $request->user());

        return redirect()->route('admin.finance.purchases.show', $purchase)->with($error ? 'warning' : 'status', $error ?: 'تم حفظ تعديلات الطلب.');
    }

    public function status(Request $request, PurchaseOrder $purchase)
    {
        $data = $request->validate(['status' => ['required', Rule::in(PurchaseOrder::FLOW)], 'note' => ['nullable', 'string', 'max:500']]);
        $error = PurchaseFlow::advance($purchase, $data['status'], $request->user(), $data['note'] ?? null);

        return back()->with($error ? 'warning' : 'status', $error ?: 'تم تغيير حالة الطلب إلى «'.$purchase->status_label.'».');
    }

    /** تحديد/تصحيح الكمية والسعر من الإدارة (اختياري؛ الأصل أن تحددها المطبعة) */
    public function price(Request $request, PurchaseOrder $purchase)
    {
        $d = self::pricingInput($request);
        $error = PurchaseFlow::price($purchase, $d['pages'], $d['copies'], (float) $d['unit_price'], $request->user());

        return back()->with($error ? 'warning' : 'status', $error ?: 'تم حفظ الكمية والسعر: '.$purchase->amount_label.'.');
    }

    public static function pricingInput(Request $request): array
    {
        return $request->validate([
            'pages' => ['required', 'integer', 'min:1', 'max:100000'],
            'copies' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [], ['pages' => 'عدد الأوراق في النسخة', 'copies' => 'عدد النسخ', 'unit_price' => 'سعر الورقة']);
    }

    public function cancel(Request $request, PurchaseOrder $purchase)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']], [], ['reason' => 'سبب الإلغاء']);
        $error = PurchaseFlow::cancel($purchase, $request->user(), $data['reason']);

        return back()->with($error ? 'warning' : 'status', $error ?: "أُلغي الطلب {$purchase->number} ولن يُحسب على المدرسة.");
    }

    /** نموذج طلب مطبوع يحمله المستلم إلى المطبعة */
    public function print(PurchaseOrder $purchase)
    {
        $purchase->load(['supplier', 'section.gradeLevel', 'gradeLevel', 'subject', 'recipient', 'creator']);

        return view('admin.finance.purchases.print', ['order' => $purchase]);
    }

    private function formData(PurchaseOrder $order, $suppliers): array
    {
        return [
            'order' => $order,
            'suppliers' => $suppliers,
            'grades' => GradeLevel::orderBy('sort_order')->with(['sections' => fn ($q) => $q->orderBy('name')->withCount('students')])->get(),
            'subjects' => Subject::orderBy('sort_order')->get(),
            'people' => $this->people(),
        ];
    }

    /** المستلمون المحتملون: المدرسون وموظفو الإدارة */
    private function people()
    {
        return User::whereIn('role', ['teacher', 'staff', 'admin'])->where('is_active', true)
            ->orderByRaw("CASE role WHEN 'teacher' THEN 0 ELSE 1 END")->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $pricing = $request->input('pricing');
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'grade_level_id' => ['nullable', 'exists:grade_levels,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'recipient_id' => ['nullable', 'exists:users,id', 'required_without:recipient_name'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'pricing' => ['required', Rule::in(array_keys(PurchaseOrder::PRICING))],
            // بالورقة: الكمية والسعر اختيارية هنا، تحددها المطبعة إن تُركت فارغة
            'pages' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'copies' => ['nullable', 'required_if:pricing,per_unit', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['nullable', 'required_if:pricing,per_unit', 'numeric', 'min:0', 'max:100000'],
            'amount' => ['nullable', 'required_if:pricing,fixed', 'numeric', 'min:0', 'max:10000000'],
            'needed_by' => ['nullable', 'date'],
        ], [
            'recipient_id.required_without' => 'حدّد من سيستلم الطلبية من المطبعة (اختر من القائمة أو اكتب الاسم).',
            'pages.required_if' => 'اكتب عدد الأوراق في النسخة الواحدة.',
            'copies.required_if' => 'اكتب عدد النسخ / الكمية.',
            'unit_price.required_if' => 'اكتب سعر الورقة / القطعة.',
            'amount.required_if' => 'اكتب سعر الطلبية.',
        ], [
            'supplier_id' => 'المطبعة', 'title' => 'عنوان الطلب', 'details' => 'التفاصيل', 'section_id' => 'الشعبة', 'grade_level_id' => 'الصف',
            'subject_id' => 'المادة', 'recipient_id' => 'المستلم', 'pages' => 'عدد الأوراق', 'copies' => 'عدد النسخ',
            'unit_price' => 'السعر', 'amount' => 'السعر', 'needed_by' => 'موعد التسليم',
        ]);

        if (! empty($data['section_id'])) {
            $data['grade_level_id'] = Section::find($data['section_id'])->grade_level_id;
        }
        if (! empty($data['recipient_id'])) {
            $data['recipient_name'] = null;
        }
        if ($pricing === 'fixed') {
            $data['pages'] = $data['copies'] = $data['unit_price'] = null;
        }
        if ($pricing === 'per_unit') {
            $data['pages'] = null;
        }
        $data['amount'] = PurchaseOrder::computeAmount($pricing, isset($data['pages']) ? (int) $data['pages'] : null, isset($data['copies']) ? (int) $data['copies'] : null,
            isset($data['unit_price']) ? (float) $data['unit_price'] : null, isset($data['amount']) ? (float) $data['amount'] : null);
        $data['priced_by'] = $data['amount'] !== null ? $request->user()->id : null;
        $data['priced_at'] = $data['amount'] !== null ? now() : null;

        return $data;
    }
}
