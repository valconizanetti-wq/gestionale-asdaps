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
Alpine.start();
