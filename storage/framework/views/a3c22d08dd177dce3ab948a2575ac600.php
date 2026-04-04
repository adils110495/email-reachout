<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
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

    // Pre-load existing content on edit
    <?php if(isset($existingBody)): ?>
        quill.root.innerHTML = <?php echo json_encode($existingBody); ?>;
    <?php endif; ?>

    // Sync Quill HTML → hidden textarea before form submit
    document.getElementById('templateForm').addEventListener('submit', function () {
        document.getElementById('body-input').value = quill.root.innerHTML;
    });
</script>
<?php /**PATH /var/www/html/resources/views/templates/_quill-init.blade.php ENDPATH**/ ?>