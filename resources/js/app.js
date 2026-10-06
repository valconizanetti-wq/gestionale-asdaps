import './bootstrap';

// Font ospitati in locale (niente Google Fonts: evita di trasmettere
// l'IP dei visitatori a terzi, vedi ui.md §2).
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css'; // etichette dei campi in grassetto
import '@fontsource/barlow-semi-condensed/600.css';
import '@fontsource/barlow-semi-condensed/700.css';

// Poppins: carattere del sito della Compagnia, usato solo dalle pagine
// pubbliche con il tema del sito (ui.md §3, "Tema del sito").
import '@fontsource/poppins/400.css';
import '@fontsource/poppins/500.css';
import '@fontsource/poppins/600.css';
import '@fontsource/poppins/700.css';
import '@fontsource/poppins/800.css';

// Alpine installato via npm: versione fissata da package-lock.json,
// niente CDN esterno.
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Campo dei giustificativi (x-campo-giustificativi, tavola PN-3): i file
// scelti si accumulano e si possono togliere; il campo vero, nascosto,
// viene riempito con DataTransfer a ogni modifica.
Alpine.data('campoFile', (tenuti = 0) => ({
    tenuti,
    scelti: [],
    // Browser senza DataTransfer (iOS prima della 14.1): il campo visibile
    // invia da sé i file dell'ultima scelta, senza accumulare.
    semplice: typeof DataTransfer === 'undefined',
    init() {
        if (this.semplice) {
            this.$refs.scegli.name = 'documenti[]';
            this.$refs.vero.disabled = true;
        }
    },
    get totale() { return this.tenuti + this.scelti.length; },
    aggiungi(evento) {
        if (this.semplice) {
            this.scelti = [...evento.target.files];
            return;
        }
        this.scelti.push(...evento.target.files);
        evento.target.value = '';
        this.aggiorna();
    },
    togli(i) {
        this.scelti.splice(i, 1);
        if (this.semplice) {
            this.$refs.scegli.value = '';
            this.scelti = [];
            return;
        }
        this.aggiorna();
    },
    aggiorna() {
        const dt = new DataTransfer();
        this.scelti.forEach((f) => dt.items.add(f));
        this.$refs.vero.files = dt.files;
    },
    peso(b) {
        return b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
    },
}));

Alpine.start();

// Righe di tabella cliccabili (data-href): tutta la riga apre la scheda,
// non solo il link della descrizione (collaudo J4). I link e i pulsanti
// dentro la riga funzionano come sempre; Ctrl/Cmd o il tasto centrale
// aprono in una nuova scheda. Da tastiera resta il link nella riga.
function apriRiga(evento) {
    const riga = evento.target.closest('tr[data-href]');
    if (!riga || evento.target.closest('a, button, input, select, textarea, label, form')) {
        return;
    }
    if (window.getSelection && String(window.getSelection()).length > 0) {
        return; // si stava selezionando del testo
    }
    if (evento.ctrlKey || evento.metaKey || evento.button === 1) {
        window.open(riga.dataset.href, '_blank', 'noopener');
    } else if (evento.button === 0) {
        window.location.href = riga.dataset.href;
    }
}
document.addEventListener('click', apriRiga);
document.addEventListener('auxclick', apriRiga);
