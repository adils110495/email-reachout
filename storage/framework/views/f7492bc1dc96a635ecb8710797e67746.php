<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
            value="<?php echo e(old('name', $template->name ?? '')); ?>" placeholder="e.g. Cold Outreach v1" required>
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
        <input type="text" name="subject" class="form-control <?php $__errorArgs = ['subject'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
            value="<?php echo e(old('subject', $template->subject ?? '')); ?>" placeholder="Email subject line" required>
        <?php $__errorArgs = ['subject'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>

        
        <div id="quill-wrapper" style="border:1px solid #dee2e6;border-radius:.375rem;background:#fff;overflow:hidden;height:280px;">
            <div id="quill-editor" style="height:100%;"></div>
        </div>

        
        <textarea name="body" id="body-input" class="d-none <?php $__errorArgs = ['body'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"><?php echo e(old('body', $template->body ?? '')); ?></textarea>
        <?php $__errorArgs = ['body'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger small mt-1"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    
    <div class="col-12">
        <label class="form-label fw-semibold">Attachments <span class="text-muted fw-normal small">(optional, max 10 MB each)</span></label>

        
        <?php if(!empty($template->attachments)): ?>
            <div class="d-flex flex-wrap gap-2 mb-2" id="existingAttachments">
                <?php $__currentLoopData = $template->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $att): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="d-flex align-items-center gap-1 px-2 py-1 rounded border bg-white small" id="att-chip-<?php echo e($loop->index); ?>">
                        <i class="bi bi-paperclip text-muted"></i>
                        <span><?php echo e($att['name']); ?></span>
                        <span class="text-muted">(<?php echo e(number_format($att['size'] / 1024, 1)); ?> KB)</span>
                        <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.6rem;"
                            onclick="removeExistingAttachment('<?php echo e($att['path']); ?>', 'att-chip-<?php echo e($loop->index); ?>')"></button>
                        <input type="hidden" name="keep_attachments[]" value="<?php echo e($att['path']); ?>" id="keep-<?php echo e($loop->index); ?>">
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>

        
        <div class="d-flex align-items-center gap-2">
            <label for="tplAttachInput" class="btn btn-sm btn-outline-secondary mb-0" style="cursor:pointer;">
                <i class="bi bi-paperclip me-1"></i>Add Files
            </label>
            <input type="file" id="tplAttachInput" name="attachments[]" multiple class="d-none" accept="*/*">
        </div>

        
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
            chip.className = 'd-flex align-items-center gap-1 px-2 py-1 rounded border bg-white small';
            chip.innerHTML = `<i class="bi bi-paperclip text-muted"></i>
                <span>${f.name}</span>
                <span class="text-muted">(${formatSize(f.size)})</span>
                <button type="button" class="btn-close btn-close-sm ms-1" style="font-size:.6rem;" data-idx="${i}"></button>`;
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
<?php /**PATH /var/www/html/resources/views/templates/_form.blade.php ENDPATH**/ ?>