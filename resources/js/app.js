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

// Date «aaaa-mm-gg» delle tessere CSEN: scadenza un anno dopo l'emissione,
// meno un giorno, senza sforare il mese (29/02 → 28/02), come
// Tesseramento::scadenzaDa (addYearNoOverflow()->subDay()).
function scadenzaTessera(iso) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    if (!m) return '';
    const anno = +m[1] + 1, mese = +m[2] - 1;
    const ultimo = new Date(anno, mese + 1, 0).getDate();
    const d = new Date(anno, mese, Math.min(+m[3], ultimo));
    d.setDate(d.getDate() - 1);
    return [String(d.getDate()).padStart(2, '0'), String(d.getMonth() + 1).padStart(2, '0'), d.getFullYear()].join('/');
}

// Finestra «Emetti / Rinnova la tessera» (tavola TS-3). soci: dati
// proposti per socio (tipo, data, quota); liberi: primo numero libero per
// tipo; vecchi: valori dopo un errore di validazione.
Alpine.data('emissioneTessera', (soci = {}, liberi = {}, vecchi = null) => ({
    soci, liberi,
    socio: vecchi?.socio_id ? String(vecchi.socio_id) : '',
    tipo: vecchi?.tipo_id ? String(vecchi.tipo_id) : '',
    numero: vecchi?.numero ?? '',
    data: vecchi?.data_rilascio ?? '',
    forza: vecchi?.forza ?? false,
    get dati() { return this.soci[this.socio] ?? null; },
    get serveConferma() { return this.forza || !!this.dati?.quota.conferma; },
    get libero() { return this.liberi[this.tipo] ?? null; },
    get scade() { return scadenzaTessera(this.data); },
    get titolo() {
        if (!this.dati) return 'Emetti tessera';
        return (this.dati.rinnovo ? 'Rinnova la tessera di ' : 'Emetti la tessera a ') + this.dati.nome;
    },
    apri(id) {
        this.forza = false;
        this.socio = id ? String(id) : '';
        this.proponi();
        this.$dispatch('apri-modale', 'emetti-tessera');
    },
    proponi() {
        this.forza = false;
        const d = this.dati;
        if (d) {
            this.tipo = d.tipo ? String(d.tipo) : this.tipo;
            this.data = d.data;
        }
        this.proponiNumero();
    },
    proponiNumero() { this.numero = this.libero?.numero ?? ''; },
}));

// Nuovo pacchetto (tavola TS-2): quantità e totale dai numeri e dal prezzo.
Alpine.data('pacchettoTessere', (prezzi = {}, iniziale = {}) => ({
    prezzi,
    tipo: iniziale.tipo ?? '',
    prezzo: iniziale.prezzo ?? '',
    dal: iniziale.dal ?? '',
    al: iniziale.al ?? '',
    movimento: iniziale.movimento ?? true,
    progressivo(v) { const m = /(\d+)\s*$/.exec(v || ''); return m ? parseInt(m[1], 10) : null; },
    get quantita() {
        const a = this.progressivo(this.dal), b = this.progressivo(this.al);
        return a !== null && b !== null && b >= a ? b - a + 1 : null;
    },
    get totale() {
        const p = parseFloat(String(this.prezzo).replace(/\./g, '').replace(',', '.'));
        if (this.quantita === null || isNaN(p)) return '';
        return (p * this.quantita).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    },
    cambiaTipo() {
        const p = this.prezzi[this.tipo];
        if (p !== undefined && p !== null) this.prezzo = p;
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

// Pulsanti con la sola icona: la descrizione (aria-label) compare anche al
// passaggio del mouse, come «title» (collaudo K). Vale per tutto il
// gestionale, anche per le etichette che Alpine cambia: si aggiorna al
// passaggio del mouse.
function titoloIcona(el) {
    const etichetta = el?.getAttribute('aria-label');
    if (etichetta && !el.textContent.trim() && el.getAttribute('title') !== etichetta) {
        el.setAttribute('title', etichetta);
    }
}
document.addEventListener('mouseover', (e) => titoloIcona(e.target.closest?.('a[aria-label], button[aria-label]')));
document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('a[aria-label], button[aria-label]').forEach(titoloIcona));
