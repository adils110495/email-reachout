<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $template->name ?? '') }}" placeholder="e.g. Cold Outreach v1" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
        <input type="text" name="subject" class="form-control @error('subject') is-invalid @enderror"
            value="{{ old('subject', $template->subject ?? '') }}" placeholder="Email subject line" required>
        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>

        {{-- Quill rich text editor --}}
        <div id="quill-editor" style="min-height:150px;height:150px;border:1px solid #dee2e6;border-radius:.375rem;background:#fff;"></div>

        {{-- Hidden textarea that holds the HTML for form submission --}}
        <textarea name="body" id="body-input" class="d-none @error('body') is-invalid @enderror">{{ old('body', $template->body ?? '') }}</textarea>
        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>
