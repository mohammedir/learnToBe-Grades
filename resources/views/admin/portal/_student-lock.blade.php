<form class="form student-lock" method="post" action="{{ $action }}" @isset($formId) id="{{ $formId }}" @endisset data-mode="{{ $current ? 'partial' : 'all' }}" style="margin-top:8px;min-width:270px">
    @csrf @if($method) @method($method) @endif
    <div class="lock-modes">
        <label><input type="radio" name="mode" value="partial" @checked($current)> مزايا محددة</label>
        <label><input type="radio" name="mode" value="all" @checked(! $current)> البوابة كاملة</label>
    </div>
    <div class="lock-feats">
        @foreach(\App\Support\PortalLock::FEATURES as $key => $label)
            <label class="check"><input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, $current))> {{ $label }}</label>
        @endforeach
    </div>
    <textarea name="reason" rows="2" required minlength="3" maxlength="500" placeholder="السبب كما سيظهر للطالب، مثل: عدم سداد الرسوم" aria-label="سبب التعطيل">{{ $reason }}</textarea>
    <div><button class="btn sm danger" type="submit">تعطيل</button></div>
</form>
