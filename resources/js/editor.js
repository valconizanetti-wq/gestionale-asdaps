// Editor dei testi dei verbali (v0.44.0, analisi V3): Jodit 4.17.1 da npm,
// versione fissata in package.json, compilato con Vite e caricato solo
// dalle pagine che scrivono testi (verbale per punti, consultazione,
// rettifica, verbale registrato da fuori). Niente pulsante «sorgente»: il
// server ripulisce comunque ogni testo con App\Support\HtmlSanitizer.
//
// Uso: <x-editor name="..." /> crea una <textarea data-editor>; qui ogni
// textarea diventa un editor, che scrive nella textarea a ogni modifica.
import { Jodit } from 'jodit/esm/index.js';
import 'jodit/esm/plugins/all.js';
import it from 'jodit/esm/langs/it.js';
import 'jodit/es2021/jodit.min.css';

Jodit.lang.it = it;

const PULSANTI = [
    'paragraph', '|', 'bold', 'italic', 'underline', '|',
    'ul', 'ol', 'outdent', 'indent', '|',
    'link', 'table', 'hr', '|',
    'undo', 'redo', '|', 'eraser',
];

function crea(area) {
    if (area.dataset.editorPronto) {
        return;
    }
    area.dataset.editorPronto = '1';
    const editor = Jodit.make(area, {
        language: 'it',
        toolbarAdaptive: false,
        buttons: PULSANTI,
        buttonsMD: PULSANTI,
        buttonsSM: PULSANTI,
        buttonsXS: PULSANTI,
        disablePlugins: [
            'source', 'ai-assistant', 'speech-recognize', 'image', 'image-processor', 'image-properties',
            'video', 'file', 'media', 'iframe', 'print', 'preview', 'about', 'powered-by-jodit', 'drag-and-drop',
            'drag-and-drop-element', 'spellcheck', 'stat', 'xpath', 'fullsize', 'symbols', 'search', 'color', 'font',
        ],
        showCharsCounter: false,
        showWordsCounter: false,
        showXPathInStatusbar: false,
        statusbar: false,
        minHeight: Number(area.dataset.altezza || 180),
        height: 'auto',
        placeholder: area.getAttribute('placeholder') || '',
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_clear_html',
        link: { noFollowCheckbox: false, openInNewTabCheckbox: false },
        table: { allowCellResize: false },
        readonly: area.hasAttribute('readonly'),
        disabled: area.hasAttribute('disabled'),
    });
    editor.e.on('change', () => {
        area.value = editor.value;
        area.dispatchEvent(new Event('input', { bubbles: true }));
    });
    if (area.id) {
        // L'etichetta del campo porta il fuoco nell'editor
        document.querySelectorAll(`label[for="${area.id}"]`).forEach((l) => l.addEventListener('click', () => editor.s.focus()));
    }
}

function avvia() {
    document.querySelectorAll('textarea[data-editor]').forEach(crea);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', avvia);
} else {
    avvia();
}
