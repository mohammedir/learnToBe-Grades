{{-- رسالة إضافة التطبيق إلى الشاشة الرئيسية (تظهر على الجوال فقط) --}}
<div class="install" id="install-prompt" role="dialog" aria-labelledby="install-title" hidden>
    <img src="{{ asset('icons/icon-192.png') }}" alt="">
    <div class="install-body">
        <b id="install-title">ثبّت تطبيق مدرسة LearnToBe تعلم لتكون على جوالك</b>

        <div data-mode="android" hidden>
            <p>افتح التطبيق من أيقونة على الشاشة الرئيسية مثل أي تطبيق، بدون كتابة الرابط كل مرة.</p>
            <div class="install-actions">
                <button class="btn primary" type="button" data-action="install">تثبيت التطبيق</button>
                <button class="btn" type="button" data-action="later">لاحقاً</button>
            </div>
        </div>

        <div data-mode="ios" hidden>
            <ol>
                <li>اضغط زر المشاركة
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-label="زر المشاركة" style="vertical-align:-3px"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 15V3m0 0L8 7m4-4 4 4M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>
                    أسفل الشاشة في Safari.</li>
                <li>اختر <b>«إضافة إلى الشاشة الرئيسية»</b>.</li>
                <li>اضغط <b>«إضافة»</b>.</li>
            </ol>
            <div class="install-actions"><button class="btn" type="button" data-action="later">فهمت</button></div>
        </div>

        <div data-mode="other" hidden>
            <p>من قائمة المتصفح <b>⋮</b> اختر <b>«إضافة إلى الشاشة الرئيسية»</b> أو <b>«تثبيت التطبيق»</b>.</p>
            <div class="install-actions"><button class="btn" type="button" data-action="later">فهمت</button></div>
        </div>
    </div>
</div>
<script src="{{ asset('js/pwa.js') }}?v=2" defer></script>
