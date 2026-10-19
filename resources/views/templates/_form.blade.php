{{--
    Template fields, shared by create and edit. The body is a CKEditor
    (partials/_email-editor); the chips insert placeholders into the subject
    or the body, wherever the cursor was last.
--}}
@include('partials._email-editor')

<div class="row g-3" data-placeholder-scope>
    <div class="col-md-6">
        <label class="form-label">Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $template->name ?? '') }}" placeholder="e.g. Cold Outreach v1" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="tplSubject">Subject <span class="text-danger">*</span></label>
        <input type="text" name="subject" id="tplSubject" class="form-control @error('subject') is-invalid @enderror"
            value="{{ old('subject', $template->subject ?? '') }}" placeholder="Email subject line" required data-placeholder-field>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <label class="form-label mb-0" for="body-input">Body <span class="text-danger">*</span></label>
            @include('partials._placeholder-chips')
        </div>

        <div class="email-editor">
            <textarea name="body" id="body-input" rows="12" class="form-control @error('body') is-invalid @enderror"
                      data-email-editor data-placeholder-field placeholder="Write your email body here…">{{ old('body', $template->body ?? '') }}</textarea>
        </div>
        @error('body')<div class="text-danger fs-13 mt-1">{{ $message }}</div>@enderror
        <div class="form-text">
            Placeholders are filled in for every email: <code>&#123;&#123;client_name&#125;&#125;</code> becomes the lead's or deal's name,
            <code>&#123;&#123;sender_name&#125;&#125;</code> and <code>&#123;&#123;sender_company&#125;&#125;</code> become yours.
            Older <code>[Client Name]</code> style placeholders still work.
        </div>
    </div>

    {{-- Attachments --}}
    <div class="col-12">
        <label class="form-label">Attachments <span class="text-muted fw-normal fs-13">(optional, max 10 MB each)</span></label>

        {{-- Saved attachments (edit mode): open, download or remove --}}
        @if (! empty($template->attachments))
            @php $disk = \Illuminate\Support\Facades\Storage::disk('local'); @endphp
            <div class="template-attachments mb-2" id="existingAttachments">
                @foreach ($template->attachments as $att)
                    @php
                        $exists = $disk->exists($att['path']);
                        $mime   = $exists ? (string) $disk->mimeType($att['path']) : '';
                        $url    = route('templates.attachment', ['id' => $template->id, 'file' => basename($att['path'])]);
                        $icon   = match (true) {
                            str_starts_with($mime, 'image/') => 'bi-file-earmark-image text-primary',
                            $mime === 'application/pdf'      => 'bi-file-earmark-pdf text-danger',
                            default                          => 'bi-file-earmark text-secondary',
                        };
                    @endphp
                    <div class="template-attachment {{ $exists ? '' : 'is-missing' }}" id="att-chip-{{ $loop->index }}">
                        @if ($exists && str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml')
                            <a href="{{ $url }}" target="_blank" rel="noopener" class="template-attachment-thumb" title="Open {{ $att['name'] }}">
                                <img src="{{ $url }}" alt="{{ $att['name'] }}" loading="lazy">
                            </a>
                        @else
                            <span class="template-attachment-thumb"><i class="bi {{ $icon }}"></i></span>
                        @endif

                        <div class="template-attachment-info">
                            @if ($exists)
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="template-attachment-name" title="Open {{ $att['name'] }}">{{ $att['name'] }}</a>
                            @else
                                <span class="template-attachment-name">{{ $att['name'] }}</span>
                            @endif
                            <span class="fs-12 text-muted">
                                {{ number_format(($att['size'] ?? 0) / 1024, 1) }} KB
                                @unless ($exists)
                                    · <span class="text-danger">file missing on the server - upload it again</span>
                                @endunless
                            </span>
                        </div>

                        <div class="template-attachment-actions">
                            @if ($exists)
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-light btn-xs" title="Open">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ $url }}?download=1" class="btn btn-light btn-xs" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                            @endif
                            <button type="button" class="btn btn-light btn-xs text-danger" title="Remove"
                                    onclick="removeExistingAttachment('{{ $att['path'] }}', 'att-chip-{{ $loop->index }}')">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- New file picker --}}
        <div class="d-flex align-items-center gap-2">
            <label for="tplAttachInput" class="btn btn-light btn-sm mb-0" style="cursor:pointer;">
                <i class="bi bi-paperclip me-1"></i>Add Files
            </label>
            <input type="file" id="tplAttachInput" name="attachments[]" multiple class="d-none" accept="*/*">
        </div>

        {{-- New file chips preview: a click opens the picked file before it is saved --}}
        <div id="tplAttachList" class="d-flex flex-wrap gap-2 mt-2"></div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('tplAttachInput');
    const list  = document.getElementById('tplAttachList');
    let   files = [];
    let   urls  = [];

    function formatSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    function render() {
        urls.forEach(URL.revokeObjectURL);
        urls = [];
        list.innerHTML = '';
        files.forEach(function (f, i) {
            const url  = URL.createObjectURL(f);
            const chip = document.createElement('div');
            urls.push(url);
            chip.className = 'attach-chip';
            chip.title     = f.name;
            chip.innerHTML = `<i class="bi bi-paperclip attach-icon"></i>
                <a class="attach-name" target="_blank" rel="noopener" title="Open"></a>
                <span class="attach-size">${formatSize(f.size)}</span>
                <span class="attach-remove" role="button" data-idx="${i}" title="Remove">&#x2715;</span>`;
            const link = chip.querySelector('a');
            link.href        = url;
            link.textContent = f.name;
            chip.querySelector('[data-idx]').addEventListener('click', function () {
                files.splice(parseInt(this.dataset.idx), 1);
                render();
                // Sync the file input with the remaining files
                syncFileInput();
            });
            list.appendChild(chip);
        });
    }

    function syncFileInput() {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        input.files = dt.files;
    }

    input.addEventListener('change', function () {
        Array.from(this.files).forEach(function (f) {
            if (!files.some(x => x.name === f.name && x.size === f.size)) {
                files.push(f);
            }
        });
        syncFileInput();
        render();
    });
}());

function removeExistingAttachment(path, chipId) {
    const chip = document.getElementById(chipId);

    // Flag it for removal on save
    const rmInput = document.createElement('input');
    rmInput.type  = 'hidden';
    rmInput.name  = 'remove_attachments[]';
    rmInput.value = path;
    chip.parentElement.appendChild(rmInput);

    chip.remove();
}
</script>
