<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $template->name ?? '') }}" placeholder="e.g. Cold Outreach v1" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Subject <span class="text-danger">*</span></label>
        <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
            value="{{ old('subject', $template->subject ?? '') }}" placeholder="Email subject line" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">Body <span class="text-danger">*</span></label>

        {{-- Quill rich text editor --}}
        <div id="quill-wrapper">
            <div id="quill-editor" style="height:100%;"></div>
        </div>

        {{-- Hidden textarea that holds the HTML for form submission --}}
        <textarea name="body" id="body-input" class="d-none @error('body') is-invalid @enderror">{{ old('body', $template->body ?? '') }}</textarea>
        @error('body')<div class="text-danger fs-13 mt-1">{{ $message }}</div>@enderror
        {{-- Variables are filled in per lead when the template is sent from Leads or used in a sequence step. --}}
        <div class="form-text">
            Variables (subject and body):
            @foreach(\App\Sequencer\Services\TemplateRendererService::VARIABLES as $variable)
                @php $tag = '{'.'{'.$variable.'}'.'}'; @endphp
                <code>{{ $tag }}</code>
            @endforeach
            · fallback: <code>@{{first_name|there}}</code> · custom field: <code>@{{custom.key}}</code>
        </div>
    </div>

    {{-- Attachments --}}
    <div class="col-12">
        <label class="form-label">Attachments <span class="text-muted fw-normal fs-13">(optional, max 10 MB each)</span></label>

        {{-- Existing attachments (edit mode) --}}
        @if (!empty($template->attachments))
            <div class="d-flex flex-wrap gap-2 mb-2" id="existingAttachments">
                @foreach ($template->attachments as $att)
                    <div class="attach-chip" id="att-chip-{{ $loop->index }}" title="{{ $att['name'] }}">
                        <i class="bi bi-paperclip attach-icon"></i>
                        <span class="attach-name">{{ $att['name'] }}</span>
                        <span class="attach-size">{{ number_format($att['size'] / 1024, 1) }} KB</span>
                        <span class="attach-remove" role="button" title="Remove"
                            onclick="removeExistingAttachment('{{ $att['path'] }}', 'att-chip-{{ $loop->index }}')">&#x2715;</span>
                        <input type="hidden" name="keep_attachments[]" value="{{ $att['path'] }}" id="keep-{{ $loop->index }}">
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

        {{-- New file chips preview --}}
        <div id="tplAttachList" class="d-flex flex-wrap gap-2 mt-2"></div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('tplAttachInput');
    const list  = document.getElementById('tplAttachList');
    let   files = [];

    function formatSize(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    function render() {
        list.innerHTML = '';
        files.forEach(function (f, i) {
            const chip = document.createElement('div');
            chip.className = 'attach-chip';
            chip.title     = f.name;
            chip.innerHTML = `<i class="bi bi-paperclip attach-icon"></i>
                <span class="attach-name">${f.name}</span>
                <span class="attach-size">${formatSize(f.size)}</span>
                <span class="attach-remove" role="button" data-idx="${i}" title="Remove">&#x2715;</span>`;
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
    const chip  = document.getElementById(chipId);
    const keep  = chip.querySelector('input[name="keep_attachments[]"]');

    // Replace keep input with remove input
    const rmInput = document.createElement('input');
    rmInput.type  = 'hidden';
    rmInput.name  = 'remove_attachments[]';
    rmInput.value = path;
    chip.parentElement.appendChild(rmInput);

    chip.remove();
}
</script>
