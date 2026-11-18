{{-- تحديد الكمية والسعر لطلب طباعة بالورقة. المتغيرات: $order, $action, $defaultPrice (اختياري) --}}
@php($cur = \App\Support\Money::currency())
<form class="price-form" method="post" action="{{ $action }}">
    @csrf
    <div class="price-grid">
        <div class="field"><label>أوراق النسخة</label><input type="number" name="pages" min="1" inputmode="numeric" required value="{{ old('pages', $order->pages) }}" aria-label="عدد الأوراق في النسخة"></div>
        <div class="field"><label>عدد النسخ</label><input type="number" name="copies" min="1" inputmode="numeric" required value="{{ old('copies', $order->copies) }}" aria-label="عدد النسخ"></div>
        <div class="field"><label>سعر الورقة ({{ $cur }})</label><input type="number" name="unit_price" min="0" step="0.001" inputmode="decimal" required value="{{ old('unit_price', $order->unit_price ?? ($defaultPrice ?? null)) }}" aria-label="سعر الورقة"></div>
    </div>
    <div class="price-out"><span data-out-formula></span><b><span data-out-total>—</span> {{ $cur }}</b></div>
    <button class="btn primary" type="submit">{{ $order->isPriced() ? 'تصحيح الكمية والسعر' : 'حفظ الكمية والسعر' }}</button>
</form>
@once
@push('scripts')
<script>
document.querySelectorAll('.price-form').forEach(f => {
    const g = (n) => f.querySelector('[name=' + n + ']');
    const run = () => {
        const p = +g('pages').value || 0, c = +g('copies').value || 0, u = +g('unit_price').value || 0;
        f.querySelector('[data-out-formula]').textContent = p + ' × ' + c + ' × ' + u + ' (' + (p * c) + ' ورقة)';
        f.querySelector('[data-out-total]').textContent = (p && c && g('unit_price').value !== '') ? (Math.round(p * c * u * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
    };
    f.addEventListener('input', run); run();
});
</script>
@endpush
@endonce
