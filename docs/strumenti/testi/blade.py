import re
ATTR_TESTO = {"label","titolo","aiuto","placeholder","title","evidenza","descrizione","testo","messaggio","conferma",
              "sottotitolo","indietro-label","aria-label","alt","vuoto","etichetta","nota","azione","pulsante","value-label"}
INLINE = {"strong","em","b","i","u","a","span","br","code","small","abbr","sup","sub","mark","x-norma","x-icona","kbd","s"}

def fine_quote(s, i):
    q = s[i]; j = i + 1
    while j < len(s):
        if s[j] == "\\": j += 2; continue
        if s[j] == q: return j
        j += 1
    return len(s) - 1

def parentesi(s, i):
    """s[i]=='(' -> indice della ')' corrispondente, rispettando le virgolette"""
    d = 0; j = i
    while j < len(s):
        c = s[j]
        if c in "'\"": j = fine_quote(s, j)
        elif c == "(": d += 1
        elif c == ")":
            d -= 1
            if d == 0: return j
        j += 1
    return len(s) - 1

def stringhe(s, a, b, solo_singole=False):
    """letterali tra a e b: (inizio, fine_esclusa)"""
    out = []; j = a
    while j < b:
        c = s[j]
        if c == "'" or (c == '"' and not solo_singole):
            k = fine_quote(s, j)
            out.append((j, k + 1)); j = k + 1
        else: j += 1
    return out

def estrai(src):
    n = len(src)
    tipo = [" "] * n          # ' ' testo, 'c' codice/maschera, '1' echo, 'I' tag inline, 'B' tag blocco
    out = []                  # (start, end, tipo)
    def segna(a, b, ch):
        for k in range(a, min(b, n)): tipo[k] = ch
    def codice(a, b, solo_singole=False):
        for (x, y) in stringhe(src, a, b, solo_singole): out.append((x, y, "php"))
    # commenti e stili
    for pat in [r"\{\{--.*?--\}\}", r"<!--.*?-->", r"<style\b.*?</style>", r"@section\('stili'\).*?@endsection"]:
        for m in re.finditer(pat, src, re.S | re.I):
            if all(t == " " for t in tipo[m.start():m.start()+1]): segna(m.start(), m.end(), "c")
    for pat in [r"@php\b(?!\s*\().*?@endphp", r"<script\b.*?</script>"]:
        for m in re.finditer(pat, src, re.S | re.I):
            if tipo[m.start()] == " ":
                codice(m.start(), m.end()); segna(m.start(), m.end(), "c")
    for m in re.finditer(r"\{\{.*?\}\}|\{!!.*?!!\}", src, re.S):
        if tipo[m.start()] == " ":
            codice(m.start(), m.end()); segna(m.start(), m.end(), "1")
    # direttive
    for m in re.finditer(r"(?<![\w@.\-])@[a-zA-Z]+\b", src):
        if tipo[m.start()] != " ": continue
        a, b = m.start(), m.end()
        k = b
        while k < n and src[k] in " \t": k += 1
        if k < n and src[k] == "(":
            b = parentesi(src, k) + 1
            codice(k, b)
        segna(a, b, "c")
    # tag
    i = 0
    while i < n:
        if src[i] == "<" and tipo[i] == " " and i + 1 < n and re.match(r"[a-zA-Z/!]", src[i+1]):
            j = i + 1
            while j < n and src[j] != ">":
                if src[j] in "'\"": j = fine_quote(src, j)
                j += 1
            nome = re.match(r"</?([a-zA-Z][\w\-:.]*)", src[i:j+1])
            nome = nome.group(1).lower() if nome else ""
            # attributi
            for a in re.finditer(r"([:@]?[\w\-:.]+)=\"([^\"]*)\"", src[i:j+1]):
                k, v = a.group(1), a.group(2); st = i + a.start(2)
                if k in ATTR_TESTO and "{{" not in v[:2]:
                    if re.search(r"[A-Za-zÀ-ÿ]{2,}", v):
                        out.append((st, st + len(v), "attr:" + k))
                elif k.startswith((":", "x-", "@", "wire:")):
                    codice(st, st + len(v), solo_singole=True)
            segna(i, j + 1, "I" if nome in INLINE else "B")
            i = j + 1
        else:
            i += 1
    # testo visibile per riga
    pos = 0
    for riga in src.split("\n"):
        L = len(riga)
        t = "".join(tipo[pos:pos+L])
        # segmenti separati da tag di blocco o codice
        for seg in re.finditer(r"[^Bc]+", t):
            a0, b0 = seg.start(), seg.end()
            pieni = [k for k in range(a0, b0) if tipo[pos+k] == " " and not riga[k].isspace()]
            if not pieni or not any(riga[k].isalpha() for k in pieni): continue
            if sum(riga[k].isalpha() for k in pieni) < 2: continue
            a, b = pieni[0], pieni[-1] + 1
            out.append((pos + a, pos + b, "testo"))
        pos += L + 1
    # in righe
    res = []
    for (a, b, k) in sorted(set(out)):
        res.append((src.count("\n", 0, a) + 1, src[a:b], k))
    return res
