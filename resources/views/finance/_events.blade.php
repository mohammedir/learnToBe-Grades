<ul class="timeline">
    @foreach($order->events as $e)
        <li><b>{{ $e->user?->name ?? 'النظام' }}</b> @if($e->user?->isSupplier())<span class="pill none">المطبعة</span>@endif {{ $e->description }}
            @if($e->note)<div class="muted">«{{ $e->note }}»</div>@endif
            <time class="muted">{{ $e->created_at->format('Y/m/d H:i') }}</time></li>
    @endforeach
</ul>
