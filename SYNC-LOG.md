# Sync Log — Gestionale Core ↔ Gestionale APS Dante Alighieri

Traccia, modulo per modulo, l'ultimo allineamento tra questo core e il
progetto originale (Gestionale APS — Società Dante Alighieri, Comitato
di Orvieto). La sincronizzazione è manuale: questo file è l'unica fonte
di verità su "cosa è aggiornato a cosa", non il log git.

Ogni riga: quando è stato portato, da quale sessione/versione del
progetto Dante, cosa è stato fatto, quale tag del core ne è risultato.

| Data | Modulo | Origine (sessione/versione Dante) | Cosa è stato portato | Tag core |
|---|---|---|---|---|
| — | Impostazione iniziale | v102 | Estrazione iniziale del modello da schema e codebase Dante | v1 (in lavorazione) |

## Derivati esistenti e loro stato di allineamento

| Derivato | Creato da tag core | Ultimo aggiornamento ricevuto |
|---|---|---|
| _(nessuno ancora)_ | — | — |

## Come aggiungere una riga

Quando porti un miglioramento dal progetto Dante al core: aggiungi una
riga alla prima tabella con la data odierna, il modulo interessato, un
riferimento alla sessione/versione Dante in cui è stato sviluppato, una
frase su cosa è cambiato, e il nuovo tag git creato nel core. Se poi
applichi quell'aggiornamento anche a un derivato, aggiorna la seconda
tabella per quel derivato.
