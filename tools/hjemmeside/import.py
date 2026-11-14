#!/usr/bin/env python3
"""
Importerer HTTrack-kopien af surfhorsens.dk (Squarespace) til klub-systemet.

    python3 -I tools/hjemmeside/import.py /var/www/surfhorsens /var/www/klub

Resultat:
  resources/site/*.html     siderne (serveres af SiteController på /, /kalender osv.)
  public/hjemmeside/...     CSS, JS, skrifttyper og billeder, kun dem siderne bruger

Alle adresser gøres lokale, så siden ikke henter noget fra Squarespace.
Kalenderen og prislisten erstattes af pladser, som public/hjemmeside/klub-widget.js
fylder med data fra /api/public/*. Samme script kan senere bruges på Squarespace-siden.
Kan køres igen efter en ny HTTrack-kopi; outputtet overskrives.
"""
import os, re, shutil, subprocess, sys, urllib.parse, urllib.request

SRC, DEST = sys.argv[1], sys.argv[2]
SITE = os.path.join(SRC, 'www.surfhorsens.dk')
OUT_PAGES = os.path.join(DEST, 'resources', 'site')
OUT_ASSETS = os.path.join(DEST, 'public', 'hjemmeside')
PREFIX = '/hjemmeside/'

PAGES = {  # fil i kopien -> adresse i klub-systemet (samme som på Squarespace)
    'index.html': '/',
    'priser-og-medlemskab.html': '/priser-og-medlemskab',
    'kalender.html': '/kalender',
    'kontakt.html': '/kontakt',
    'read-me.html': '/read-me',
}
OUT_NAMES = {'index.html': 'forside.html'}
# Mapper med Squarespaces CSS/JS/skrifttyper – kopieres hele (ca. 12 MB)
ASSET_HOSTS = ['assets.squarespace.com', 'static1.squarespace.com', 'definitions.sqspcdn.com', 'file.squarespace-cdn.com']
IMG_HOST = 'images.squarespace-cdn.com'
LOCAL_HOSTS = ASSET_HOSTS + [IMG_HOST]

copied = set()
missing = set()


def copy_file(rel):
    """rel = sti under SRC (uden query). Returnerer True hvis filen findes lokalt."""
    for cand in (rel, urllib.parse.unquote(rel)):
        src = os.path.join(SRC, cand)
        if os.path.isfile(src):
            dst = os.path.join(OUT_ASSETS, cand)
            if cand not in copied:
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                shutil.copy2(src, dst)
                copied.add(cand)
            return cand
    return None


images = {}  # mappe på CDN'et -> lokal .webp


def image_local(path):
    """path = content/[v1/]<site>/<id>/<navn> på images.squarespace-cdn.com.
    Hvert billede gemmes én gang som WebP (højst 2000 px bredt) i stedet for Squarespaces mange størrelser."""
    parts = urllib.parse.unquote(path).split('/')
    if len(parts) < 4 or parts[0] != 'content':
        return None
    folder, name = '/'.join(parts[:-1]), parts[-1]
    if folder in images:
        return images[folder]
    d = os.path.join(SRC, IMG_HOST, folder)
    files = [f for f in os.listdir(d) if not f.endswith('.tmp')] if os.path.isdir(d) else []
    src = os.path.join(d, max(files, key=lambda f: os.path.getsize(os.path.join(d, f)))) if files else None
    stem = re.sub(r'[^A-Za-z0-9_-]+', '-', os.path.splitext(name)[0]).strip('-')[:60] or 'billede'
    rel = f'{IMG_HOST}/{folder}/{stem}.webp'
    dst = os.path.join(OUT_ASSETS, rel)
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    tmp = None
    try:
        if not src:  # ikke med i kopien: hent én gang i god opløsning
            tmp = src = dst + '.download'
            url = f'https://{IMG_HOST}/{urllib.parse.quote(path.split("?")[0], safe="/%+")}?format=2500w'
            with urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'klub-import'}), timeout=60) as r:
                open(tmp, 'wb').write(r.read())
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', src, '-vf', "scale='min(2000,iw)':-2", '-c:v', 'libwebp',
                        '-quality', '80', '-pix_fmt', 'yuva420p', '-frames:v', '1', dst], check=True)
    except Exception as e:
        missing.add(f'{path} ({e})')
        images[folder] = None
        return None
    finally:
        if tmp and os.path.exists(tmp):
            os.remove(tmp)
    copied.add(rel)
    images[folder] = rel
    return rel


def web(rel):
    return PREFIX + urllib.parse.quote(rel, safe="/+%@-_.~()!*,;=:'")


def absolute(m):
    """https://<squarespace-vært>/sti -> lokal fil, hvis vi har den."""
    host, path = m.group(1), m.group(2)
    clean = path.split('?')[0].split('#')[0]
    if host == IMG_HOST:
        rel = image_local(clean)
    elif clean.endswith('/') and os.path.isdir(os.path.join(SRC, host, clean)):
        return web(f'{host}/{clean}')  # fx mappen, Squarespace henter sine script-dele fra
    else:
        rel = copy_file(f'{host}/{clean}')
    return web(rel) if rel else m.group(0)


ABS_RE = re.compile(r'(?:https?:)?(?://|\\/\\/)(' + '|'.join(re.escape(h) for h in LOCAL_HOSTS) + r')(?:/|\\/)([^"\'\s)<>&,\\]*)')


def relative(u):
    """Relative adresser fra HTTrack (sider ligger i www.surfhorsens.dk/)."""
    if not u or u.startswith(('#', 'data:', 'mailto:', 'tel:', 'javascript:', 'http:', 'https:', '/')):
        return u
    path, _, frag = u.partition('#')
    path, _, query = path.partition('?')
    if path in PAGES:
        return PAGES[path] + (f'#{frag}' if frag else '')
    if path == 'cart.html':
        return '/'
    if path.startswith(('kalender/', 'kelender/')):
        return '/kalender'
    if path.startswith('../'):
        rest = path[3:]
        host = rest.split('/')[0]
        if host in LOCAL_HOSTS:
            if host == IMG_HOST:
                rel = image_local(rest[len(host) + 1:])
            else:
                rel = copy_file(rest)
            if rel:
                return web(rel)
            if rest.endswith('index.html'):  # fx et preconnect-link til CDN'et
                return f'https://{host}/'
            missing.add(rest)
            return u
        # Ekstern side (Facebook, designeren, Google Maps …): tilbage til den rigtige adresse
        ext = re.sub(r'(^|/)index[0-9a-f]{0,4}\.html$', r'\1', rest.replace('www.google.com_443', 'www.google.com'))
        return 'https://' + ext + (f'?{query}' if query else '') + (f'#{frag}' if frag else '')
    return u


ATTR_RE = re.compile(r'\b(href|src|data-src|data-image|poster|content)="([^"]*)"')
SRCSET_RE = re.compile(r'\b(srcset|data-srcset)="([^"]*)"')
URL_RE = re.compile(r'url\(\s*([\'"]?)([^\'")]+)\1\s*\)')


def rewrite(html):
    html = re.sub(r'<!-- Mirrored from.*?-->\s*', '', html)
    html = re.sub(r'<!-- Added by HTTrack -->.*?<!-- /Added by HTTrack -->\s*', '', html, flags=re.S)
    html = ATTR_RE.sub(lambda m: f'{m.group(1)}="{relative(m.group(2))}"', html)
    html = SRCSET_RE.sub(lambda m: f'{m.group(1)}="' + ', '.join(
        ' '.join([relative(p.strip().split(' ')[0])] + p.strip().split(' ')[1:]) for p in m.group(2).split(',') if p.strip()) + '"', html)
    html = URL_RE.sub(lambda m: f'url({m.group(1)}{relative(m.group(2))}{m.group(1)})', html)
    html = ABS_RE.sub(absolute, html)
    return html


def block_end(html, start, tag='div'):
    """Index lige efter det </tag>, der lukker elementet, som starter ved start."""
    depth, i = 0, start
    pat = re.compile(rf'<{tag}\b|</{tag}>')
    for m in pat.finditer(html, start):
        depth += 1 if m.group(0) != f'</{tag}>' else -1
        if depth == 0:
            return m.end()
    raise ValueError('ingen slut-tag')


def replace_block(html, opening, new):
    i = html.find(opening)
    if i < 0:
        raise ValueError(f'fandt ikke {opening[:60]}')
    return html[:i] + new + html[block_end(html, i):]


LOGIN_DESKTOP = '''<div class="header-nav-item header-nav-item--collection klub-login-item">
      <a href="/login" data-klub="login" data-animation-role="header-element">Log ind</a>
    </div>
'''
LOGIN_MOBILE = '''<div class="container header-menu-nav-item header-menu-nav-item--collection klub-login-item">
              <a href="/login" data-klub="login"><div class="header-menu-nav-item-content">Log ind</div></a>
            </div>
'''
WIDGET = '<script src="/hjemmeside/klub-widget.js" defer></script>\n'


def klub_changes(name, html):
    # "Meld mig ind!" (knappen i toppen og i mobilmenuen) går til indmeldelsen i klub-systemet
    html = re.sub(r'(<a\s+class="[^"]*sqs-button-element--primary[^"]*"\s+href=")/priser-og-medlemskab("\s*>\s*Meld mig ind!)', r'\1/bliv-medlem\2', html)
    html = re.sub(r'(&quot;href&quot;: &quot;)/priser-og-medlemskab(&quot;,\s*&quot;buttonText&quot;: &quot;Meld mig ind!)', r'\1/bliv-medlem\2', html)
    # "Log ind" sidst i menuen (computer og mobil)
    desk = re.search(r'<div class="header-nav-item header-nav-item--collection[^"]*">\s*<a\s+href="/kontakt"[\s\S]*?</div>\n', html)
    mob = re.search(r'<div class="container header-menu-nav-item header-menu-nav-item--collection[^"]*">\s*<a\s+href="/kontakt"[\s\S]*?</a>\s*</div>\n', html)
    if not desk or not mob:
        raise ValueError(f'{name}: fandt ikke menuen')
    html = html[:mob.end()] + LOGIN_MOBILE + html[mob.end():]
    html = html[:desk.end()] + LOGIN_DESKTOP + html[desk.end():]

    if name == 'kalender.html':
        # De gamle begivenheders billeder genbruges som billeder til aktiviteterne
        i = html.find('<div class="eventlist eventlist--past">')
        imgs = list(dict.fromkeys(re.findall(r'<img data-src="(/hjemmeside/[^"]+\.webp)"', html[i:block_end(html, i)])))
        # Squarespaces månedskalender ("Upcoming Events") henter fra Squarespace og er tom her: fjern hele sektionen
        c = html.find('sqs-block-calendar')
        if c >= 0:
            sec = html.rfind('<section', 0, c)
            if 'Upcoming Events' in html[sec:c] and 'eventlist' not in html[sec:c]:
                html = html[:sec] + html[block_end(html, sec, 'section'):]
        html = replace_block(html, '<div class="eventlist eventlist--past">',
                             f'<div class="eventlist eventlist--upcoming" data-klub="kalender" data-klub-billeder="{"|".join(imgs)}">'
                             '<p class="klub-loading">Henter kalenderen …</p></div>')
    if name == 'priser-og-medlemskab.html':
        # Prislisten i den orange boks "Kontingenter & priser"
        start = html.find('<div class="sqs-html-content" data-sqsp-text-block-content><p style="white-space:pre-wrap;" data-rte-preserve-empty="true"><strong>Fuld træningssæson')
        if start < 0:
            raise ValueError('fandt ikke prislisten')
        html = html[:start] + '<div class="sqs-html-content" data-sqsp-text-block-content data-klub="priser"><p>Henter priserne …</p></div>' + html[block_end(html, start):]
        # Kursuspriser i teksten følger pakkerne (tilbud) med samme navn
        html = html.replace('Pris: kr. 499,-', 'Pris: <span data-klub-pris="SUP-kursus">kr. 499,-</span>', 1)
    return html.replace('</body>', WIDGET + '</body>', 1)


# Filer, som Squarespaces scripts henter undervejs (fx webpack-dele), men som HTTrack ikke fik med.
# Listen findes ved at åbne siderne med alle eksterne kald blokeret (se README.md).
EXTRA = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'extra-files.txt')


def fetch_extra():
    if not os.path.isfile(EXTRA):
        return
    for line in open(EXTRA, encoding='utf-8'):
        rel = line.strip()
        if not rel or rel.startswith('#'):
            continue
        dst = os.path.join(OUT_ASSETS, rel)
        cache = os.path.join(SRC, rel)  # hentes én gang ind i kopien, så næste import ikke skal på nettet
        if not os.path.isfile(cache):
            try:
                os.makedirs(os.path.dirname(cache), exist_ok=True)
                with urllib.request.urlopen(urllib.request.Request('https://' + rel, headers={'User-Agent': 'klub-import'}), timeout=60) as r:
                    open(cache, 'wb').write(r.read())
            except Exception as e:
                missing.add(f'{rel} ({e})')
                continue
        copy_file(rel)


def main():
    os.makedirs(OUT_PAGES, exist_ok=True)
    if os.path.isdir(OUT_ASSETS):
        for entry in os.listdir(OUT_ASSETS):  # behold klub-widget.js
            p = os.path.join(OUT_ASSETS, entry)
            if os.path.isdir(p):
                shutil.rmtree(p)
    # Squarespaces CSS/JS: hele mapperne, så scripts kan finde deres egne filer
    for host in ASSET_HOSTS:
        for root, _, files in os.walk(os.path.join(SRC, host)):
            for f in files:
                if f.endswith(('.tmp', '.html')):
                    continue
                copy_file(os.path.relpath(os.path.join(root, f), SRC))
    fetch_extra()  # før siderne, så adresserne til de hentede filer også gøres lokale
    for name in PAGES:
        html = open(os.path.join(SITE, name), encoding='utf-8').read()
        html = klub_changes(name, rewrite(html))
        open(os.path.join(OUT_PAGES, OUT_NAMES.get(name, name)), 'w', encoding='utf-8').write(html)
    # CSS kan pege på skrifttyper/billeder med fulde adresser
    for root, _, files in os.walk(OUT_ASSETS):
        for f in files:
            if f.endswith('.js'):
                # webpack henter sine dele fra en fast adresse (d.p="https://assets.squarespace.com/…")
                p = os.path.join(root, f)
                s = open(p, encoding='utf-8', errors='surrogateescape').read()
                n = s.replace('"https://assets.squarespace.com/universal/', '"' + PREFIX + 'assets.squarespace.com/universal/')
                if n != s:
                    open(p, 'w', encoding='utf-8', errors='surrogateescape').write(n)
            if f.endswith('.css'):
                p = os.path.join(root, f)
                s = open(p, encoding='utf-8', errors='surrogateescape').read()
                n = ABS_RE.sub(absolute, s)
                if n != s:
                    open(p, 'w', encoding='utf-8', errors='surrogateescape').write(n)
    print(f'{len(PAGES)} sider, {len(copied)} filer kopieret')
    for m in sorted(missing):
        print('MANGLER', m)


main()
