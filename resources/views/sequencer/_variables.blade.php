<div class="card"><div class="card-header"><h5 class="card-title mb-0">Variables</h5></div>
    <div class="card-body fs-13">
        <p class="text-muted">Click to copy. Add a fallback with a pipe: <code>@{{first_name|there}}</code>.</p>
        @foreach ($variables as $v)
            @php $tag = '{'.'{'.$v.'}'.'}'; @endphp
            <button type="button" class="btn btn-sm btn-light mb-1" data-copy="{{ $tag }}" onclick="navigator.clipboard?.writeText(this.dataset.copy)">{{ $tag }}</button>
        @endforeach
        <hr>
        <p class="mb-1"><code>@{{custom.key}}</code> reads a lead's custom field.</p>
        <p class="mb-0"><code>@{{unsubscribe_url}}</code> places the unsubscribe link yourself; otherwise one is added automatically to every email.</p>
    </div></div>
