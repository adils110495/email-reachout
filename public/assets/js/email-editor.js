/**
 * Rich-text editor for email bodies (CKEditor 5): Email Templates, the Leads
 * compose modal and Bulk Send. Loaded by partials/_email-editor.blade.php,
 * after the CKEditor build.
 *
 * Every <textarea data-email-editor> becomes an editor on load. Read and write
 * it through EmailEditor - those also work on the plain textarea when the
 * editor could not load (CDN blocked), so sending never depends on it:
 *
 *   EmailEditor.getData(textarea)        -> HTML
 *   EmailEditor.setData(textarea, html)
 *   EmailEditor.isEmpty(textarea)
 *
 * Placeholder chips (partials/_placeholder-chips) insert {{key}} into the
 * field of their [data-placeholder-scope] that had the focus last - an
 * editor, or an <input data-placeholder-field> such as the subject.
 */
(function () {
    'use strict';

    const C       = window.CKEDITOR;
    const editors = new Map(); // textarea -> editor

    function create(textarea) {
        if (! C || editors.has(textarea)) return;

        C.ClassicEditor.create(textarea, {
            licenseKey: 'GPL',
            plugins: [
                C.Essentials, C.Paragraph, C.Heading, C.Bold, C.Italic, C.Underline, C.Strikethrough,
                C.Link, C.List, C.BlockQuote, C.Alignment, C.Table, C.TableToolbar,
                C.Autoformat, C.PasteFromOffice,
            ],
            toolbar: {
                items: [
                    'heading', '|', 'bold', 'italic', 'underline', 'strikethrough', '|',
                    'link', 'bulletedList', 'numberedList', 'blockQuote', 'alignment', 'insertTable', '|',
                    'undo', 'redo',
                ],
            },
            heading: {
                options: [
                    { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                    { model: 'heading2', view: 'h2', title: 'Heading', class: 'ck-heading_heading2' },
                    { model: 'heading3', view: 'h3', title: 'Subheading', class: 'ck-heading_heading3' },
                ],
            },
            link: { defaultProtocol: 'https://' },
            table: { contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'] },
            placeholder: textarea.getAttribute('placeholder') || '',
        }).then(function (editor) {
            editors.set(textarea, editor);

            // Lets a placeholder chip find this editor when it had the focus last.
            editor.ui.element.setAttribute('data-placeholder-field', '');
            editor.ui.element.__emailEditor = editor;

            // A normal form post (Email Templates) sends the textarea.
            if (textarea.form) {
                textarea.form.addEventListener('submit', function () { editor.updateSourceElement(); });
            }
        }).catch(function (error) {
            // The plain textarea stays usable.
            console.error('Email editor could not start:', error);
        });
    }

    function getData(textarea) {
        const editor = editors.get(textarea);
        return editor ? editor.getData() : textarea.value;
    }

    function setData(textarea, html) {
        const editor = editors.get(textarea);
        if (editor) {
            editor.setData(html || '');
        } else {
            textarea.value = html || '';
        }
    }

    function isEmpty(textarea) {
        const div = document.createElement('div');
        div.innerHTML = getData(textarea);
        return div.textContent.replace(/ /g, ' ').trim() === '';
    }

    // ── Placeholder chips ─────────────────────────────────────────────────

    /** Insert text at the cursor of an editor or an input. */
    function insert(field, text) {
        const editor = field.__emailEditor;

        if (editor) {
            editor.model.change(function (writer) {
                // Keeps the formatting at the cursor (a placeholder typed into bold text stays bold).
                editor.model.insertContent(writer.createText(text, editor.model.document.selection.getAttributes()));
            });
            editor.editing.view.focus();
            return;
        }

        const start = field.selectionStart === null ? field.value.length : field.selectionStart;
        const end   = field.selectionEnd === null ? field.value.length : field.selectionEnd;
        field.setRangeText(text, start, end, 'end');
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Remember, per scope, which field had the focus last.
    document.addEventListener('focusin', function (e) {
        const field = e.target.closest('[data-placeholder-field]');
        const scope = field && field.closest('[data-placeholder-scope]');
        if (scope) scope.__lastField = field;
    });

    document.addEventListener('click', function (e) {
        const chip = e.target.closest('[data-insert-placeholder]');
        if (! chip) return;
        e.preventDefault();

        const scope = chip.closest('[data-placeholder-scope]');
        if (! scope) return;

        // Until a field is clicked, the body: its editor, or the plain textarea.
        const field = (scope.__lastField && scope.contains(scope.__lastField) && scope.__lastField)
            || scope.querySelector('.ck-editor[data-placeholder-field]')
            || scope.querySelector('[data-placeholder-field]');

        if (field) insert(field, '{{' + chip.dataset.insertPlaceholder + '}}');
    });

    document.querySelectorAll('textarea[data-email-editor]').forEach(create);

    window.EmailEditor = { getData: getData, setData: setData, isEmpty: isEmpty };
})();
