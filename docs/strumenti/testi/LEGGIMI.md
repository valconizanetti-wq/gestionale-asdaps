# Esportazione dei testi (24/10/2026)

Richiesta di Paolo del 24/10/2026: correggere in una volta sola i testi visti
nei collaudi. Questi script estraggono i testi visibili del gestionale (pagine
Blade, email, PDF, messaggi ed errori nel PHP, menu, messaggi di validazione)
in un file Excel da compilare.

```
python3 -I docs/strumenti/testi/esporta.py . /tmp/righe.json
python3 -I docs/strumenti/testi/xlsx.py /tmp/righe.json testi-gestionale-asd.xlsx "v0.44.1"
```

- `php_strings.php`: letterali PHP con riga e contesto (tokenizer di PHP).
- `blade.py`: testo visibile, attributi testuali (label, titolo, aiuto…) e
  stringhe PHP dentro `{{ }}`, direttive e attributi legati.
- `filtro.py`: distingue il testo italiano dal codice (chiavi, rotte, CSS, SQL).
- `esporta.py`: modulo, tipo, collaudi che citano il testo; un testo per riga
  per ogni modulo, con tutti i punti in cui compare.
- `xlsx.py`: il file con i fogli «Istruzioni», «Testi» (colonna gialla «Testo
  corretto», «Note per Claude») e «Posizioni» (nascosto: ID, file, riga, tipo,
  originale esatto), che serve per riapplicare le correzioni.

Per applicare: per ogni riga con «Testo corretto», in ogni posizione si
sostituisce l'originale nella riga indicata (letterali PHP con gli apici
rimessi e l'escape degli apici), poi si aggiornano collaudi e prove
automatiche che citano il testo vecchio.
