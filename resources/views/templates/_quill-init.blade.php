<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<style>
    #quill-wrapper .ql-toolbar { border: none; border-bottom: 1px solid #dee2e6; border-radius: 0; }
    #quill-wrapper .ql-container { border: none; font-size: .92rem; }
    #quill-wrapper .ql-editor { min-height: 160px; }
</style>
<script>
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: 'Write your email body here…',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link'],
                ['clean']
            ]
        }
    });

    // Keep Quill editor filling the resizable wrapper
    const quillWrapper = document.getElementById('quill-wrapper');
    new ResizeObserver(function () {
        document.getElementById('quill-editor').style.height =
            (quillWrapper.offsetHeight - (quillWrapper.querySelector('.ql-toolbar').offsetHeight || 42)) + 'px';
    }).observe(quillWrapper);

    // Pre-load existing content on edit
    @isset($existingBody)
        quill.root.innerHTML = {!! json_encode($existingBody) !!};
    @endisset

    // Sync Quill HTML → hidden textarea before form submit
    document.getElementById('templateForm').addEventListener('submit', function () {
        document.getElementById('body-input').value = quill.root.innerHTML;
    });
</script>
