{{--
    "Insert" chips for email placeholders. A click puts {{key}} into the field
    of the surrounding [data-placeholder-scope] that had the focus last - the
    subject or the body (assets/js/email-editor.js). Braces are written as
    entities: Blade would read them as an echo.
--}}
<div class="placeholder-chips">
    <span class="fs-13 text-muted">Insert:</span>
    @foreach(\App\Support\EmailPlaceholders::TOKENS as $key => $label)
        <button type="button" class="placeholder-chip" data-insert-placeholder="{{ $key }}" title="{{ $label }}">&#123;&#123;{{ $key }}&#125;&#125;</button>
    @endforeach
</div>
