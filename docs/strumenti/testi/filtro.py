import re
IT = set("""il lo la i gli le un una uno di da in con su per tra fra e o ma non che chi del della dei delle degli al alla ai alle dal dalla nel nella nei sul sulla è sono si ci ha hanno qui più già solo anche come quando se dopo prima poi questa questo quella quello ogni nessun nessuna tutti tutte serve scegli scrivi carica apri premi socio soci quota anno data utente email nota spesa verbale riunione consiglio presidente segretario tesoriere""".split())
CODE = re.compile(r"(::|->|=>|\bSELECT\b|\bWHERE\b|\bFROM\b|\bJOIN\b|\bIS NULL\b|^\s*[/\\]|^[a-z0-9_\-]+(\.[a-z0-9_\-]+)+$|\.blade|\.php|^#[0-9a-fA-F]{3,6}$|^(bg|btn|text|td|th|ut|pn|fa|form|badge|card)-)")
def testo(raw):
    """contenuto visibile di un letterale PHP"""
    if raw.startswith("'"):
        return raw[1:-1].replace("\\'", "'").replace("\\\\", "\\")
    if raw.startswith('"'):
        return raw[1:-1]
    return raw
def italiano(s, ctx=""):
    if "Log ::" in ctx or "Log::" in ctx: return False
    if re.fullmatch(r"[A-Za-z0-9_.\-]+", s) and ("_" in s or "." in s or "-" in s): return False
    if re.fullmatch(r"[A-ZÀ-Ý][a-zà-ÿ']+", s) and len(s) >= 3 and re.search(r"'(label|titolo|etichetta|title|nome)' =>\s*$", ctx):
        return True
    if not re.search(r"\s", s.strip()): return False
    t = re.sub(r"\{\$[^}]*\}|\$[a-zA-Z_][\w\->\[\]']*|\{\{.*?\}\}|\{!!.*?!!\}|:[a-z_]+\b", " ", s)
    if not re.search(r"[a-zàèéìòù]{2,}", t): return False
    if CODE.search(s): return False
    parole = re.findall(r"[A-Za-zÀ-ÿ']+", t)
    if len(parole) < 2:
        # una parola sola: solo se parola con maiuscola o accento (etichette) -> scarto: troppe chiavi
        return False
    if re.fullmatch(r"[a-z0-9_\-\s\.]+", t.strip()) and not (set(w.lower() for w in parole) & IT):
        return False
    low = [w.lower() for w in parole]
    if set(low) & IT or re.search(r"[àèéìòù«»]", t) or re.search(r"[.!?:;,]\s*$", t.strip()) or t.strip()[:1].isupper():
        return True
    return False
