import json, re, glob, os, sys, collections, subprocess
D = os.path.dirname(os.path.abspath(__file__))
exec(open(os.path.join(D, "filtro.py")).read())
exec(open(os.path.join(D, "blade.py")).read())
REPO = sys.argv[1]; OUT = sys.argv[2]
os.chdir(REPO)
# ---- PHP
files = [f for f in glob.glob("app/**/*.php", recursive=True) + glob.glob("config/*.php") + glob.glob("routes/*.php") + glob.glob("lang/**/*.php", recursive=True)]
php = json.loads(subprocess.check_output(["php", os.path.join(D, "php_strings.php")] + files))
voci = []   # dict: file, riga, raw, kind, testo, tipo
def tipo_php(f, ctx):
    if f.startswith("lang/"): return "Messaggio di errore"
    if "navigazione" in f: return "Menu"
    if "Console/Commands" in f: return "Comando (terminale)"
    if re.search(r"'dettaglio' =>\s*$|'dettaglio' => .*\.\s*$", ctx): return "Audit Log (dettaglio)"
    if re.search(r"with \( '(success|error|warning|info|ok)' ,", ctx): return "Messaggio (fascia)"
    if re.search(r"subject|oggettoPer|Envelope", ctx): return "Email (oggetto)"
    if re.search(r"withMessages|withErrors|fail|Exception|\.(required|min|max|regex|in|date|email|unique|mimes|extensions|before|after|different)[\w_]*' =>|errori \[ \] =|abort", ctx): return "Messaggio di errore"
    return "Testo"
for f, riga, raw, ctx in php:
    t = testo(raw)
    if raw.startswith(("'#", "'/", "'~")) and re.search(r"\\[bdsw]|\[|\(\?", raw): continue
    if not italiano(t, ctx): continue
    if f in ("routes/web.php", "routes/iscrizione.php") or "Display an inspiring" in t: continue
    if (" " not in t.strip() and "/" in t) or "= ?" in t or "base64" in t or re.fullmatch(r"[\w\-]+(/[\w\-{}$]+)+/?", t): continue
    if f.startswith("config/") and not re.search(r"navigazione|stati|audit", f): continue
    voci.append(dict(file=f, riga=riga, raw=raw, kind="php-singola" if raw.startswith("'") else "php-doppia", testo=t, tipo=tipo_php(f, ctx)))
# ---- Blade
TIPO_ATTR = {"label": "Etichetta di campo", "aiuto": "Aiuto del campo", "placeholder": "Aiuto del campo",
             "titolo": "Titolo", "evidenza": "Titolo", "sottotitolo": "Titolo"}
for f in sorted(glob.glob("resources/views/**/*.blade.php", recursive=True)):
    if "/vendor/" in f or f.endswith("welcome.blade.php"): continue
    src = open(f, encoding="utf-8").read()
    ris = estrai(src)
    # intervalli dei testi (per scartare le stringhe PHP già dentro un testo)
    righe = src.split("\n")
    testi = [(r, raw) for (r, raw, k) in ris if k == "testo"]
    base = "Email" if "/emails/" in f else "PDF" if "/pdf/" in f else None
    for (r, raw, k) in ris:
        if k == "php":
            t = testo(raw)
            if not italiano(t): continue
            if any(rr == r and raw in tr for (rr, tr) in testi): continue
            voci.append(dict(file=f, riga=r, raw=raw, kind="blade-php", testo=t, tipo=base or "Testo della pagina"))
        elif k.startswith("attr:"):
            voci.append(dict(file=f, riga=r, raw=raw, kind="blade-attr", testo=raw, tipo=base or TIPO_ATTR.get(k[5:], "Etichetta")))
        else:
            voci.append(dict(file=f, riga=r, raw=raw, kind="blade-testo", testo=raw, tipo=base or "Testo della pagina"))
# ---- moduli
MODULI = [
 (r"soci/importa|ImportazioneSoci|SociImporta|modelli", "Soci · importazione"),
 (r"iscrizione|domande|Domand|Iscrizion|VotoCd|voto-cd|VotazioneCd|VerbaleAmmissione|ammissione|ricevuta-domanda", "Domande di iscrizione"),
 (r"privacy|Privacy|informativa", "Privacy"),
 (r"verbal|Verbal|Riunion|riunion|Consultazion|consultazion|Seduta|organi|DocumentiOrgani|Votazioni|rettifica", "Verbali e riunioni"),
 (r"governance|ConsiglioDirettivo|GovernanceCd", "Consiglio Direttivo"),
 (r"utenti|Utenti|User\.php|Password|password|/auth|Auth|AccessoSocio|EmailAccesso|LinkPassword|Avvisi|avviso-operazione|OperazioneAvviso", "Utenti e accesso"),
 (r"anticipi|NoteRimborso|NotaRimborso|nota-rimborso|Anticipi", "Anticipi e note di rimborso"),
 (r"scontrin|Scontrin|Fornitor|fornitor", "Scontrini e fornitori"),
 (r"tesser|Tesser|Pacchett", "Tessere CSEN"),
 (r"rinnovi|Rinnov|Quota|quote|ricevut|Ricevut|QuoteNonVersate", "Quote e ricevute"),
 (r"prima-nota|PrimaNota|Categorie|AnniContabil|Giroconto|Importo", "Prima Nota"),
 (r"scadenz|Scadenz", "Scadenzario"),
 (r"promemoria|Promemoria", "Promemoria"),
 (r"audit|Audit", "Audit Log"),
 (r"soci|Soci|Socio", "Soci"),
 (r"impostazioni|Impostazion|ProfiloEnte|Associazione|periodo|affiliazione|Ente", "Impostazioni ed ente"),
 (r"Azzera", "Azzeramento (comando)"),
 (r"^lang/", "Messaggi standard di Laravel"),
 (r"Giustificativi", "Prima Nota"),
 (r"CodiceFiscale", "Soci"),
 (r"LettoreXlsx", "Soci · importazione"),
 (r"Middleware", "Utenti e accesso"),
 (r"console\.php|pdf/(layout|carta-intestata)|Documenti\.php", "Generale (menu, pagine comuni)"),
 (r"navigazione|layouts|components|dashboard|errors|stati", "Generale (menu, pagine comuni)"),
]
def modulo(f):
    for p, m in MODULI:
        if re.search(p, f): return m
    return "Altro"
for v in voci: v["modulo"] = modulo(v["file"])
# ---- collaudi che citano il testo
coll = {}
for f in sorted(glob.glob("docs/test/collaudo-*.md")):
    blocco = re.match(r"collaudo-([A-Za-z\-]+?)-", os.path.basename(f)).group(1)
    coll[blocco] = open(f, encoding="utf-8").read()
def citato(t):
    pezzi = re.split(r"\{\$[^}]*\}|\$\w+|\{\{.*?\}\}|\{!!.*?!!\}|<[^>]+>|:\w+\b|\{\w+\}", t)
    pezzi = sorted((p.strip(" .,:;") for p in pezzi), key=len, reverse=True)
    if not pezzi or len(pezzi[0]) < 12: return ""
    return ", ".join(b for b, s in coll.items() if pezzi[0] in s)
# ---- gruppi per testo uguale
gruppi = collections.OrderedDict()
ORD = [m for _, m in MODULI] + ["Altro"]
voci.sort(key=lambda v: (ORD.index(v["modulo"]), v["file"], v["riga"]))
for v in voci:
    gruppi.setdefault((v["modulo"], v["testo"].strip()), []).append(v)
righe = []
for i, ((_, t), vs) in enumerate(gruppi.items(), 1):
    righe.append(dict(id=f"T{i:04d}", moduli=sorted({v['modulo'] for v in vs}, key=ORD.index), tipi=sorted({v['tipo'] for v in vs}),
                      testo=t, dove=[f"{v['file']}:{v['riga']}" for v in vs], collaudo=citato(t), voci=vs))
json.dump(righe, open(OUT, "w"), ensure_ascii=False)
print(len(voci), "posizioni,", len(righe), "testi")
print(collections.Counter(r["moduli"][0] for r in righe).most_common())
