{{--
    CKEditor 5 for email bodies: include once on any page with a
    <textarea data-email-editor>. The editor itself is assets/js/email-editor.js.

    jsDelivr serves the npm build, which CKEditor counts as self-hosted - the
    "GPL" licence key works there (not on CKEditor's own cloud CDN).
--}}
@once
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ckeditor5@48.5.2/dist/browser/ckeditor5.css">
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/ckeditor5@48.5.2/dist/browser/ckeditor5.umd.js"></script>
        <script src="{{ asset('assets/js/email-editor.js') }}?v={{ filemtime(public_path('assets/js/email-editor.js')) }}"></script>
    @endpush
@endonce
