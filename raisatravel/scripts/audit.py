#!/usr/bin/env python3
"""
Static QA for the built site.

Runs against dist/ after `npm run build` and fails loudly, so broken links,
undefined classes/variables and accessibility regressions cannot reach a deploy.

    npm run build && python3 scripts/audit.py
"""

import glob
import os
import re
import sys

DIST = "dist"
pages = sorted(glob.glob(os.path.join(DIST, "*.html")))
css = "\n".join(open(f).read() for f in glob.glob(os.path.join(DIST, "assets", "*.css")))
js = "\n".join(open(f).read() for f in glob.glob("js/*.js"))

problems = []
ids = {os.path.basename(p): set(re.findall(r'id="([\w-]+)"', open(p).read())) for p in pages}

if not pages:
    print("no built pages found - run `npm run build` first")
    sys.exit(1)

# ---- links, assets and fragments -------------------------------------------------
for p in pages:
    name = os.path.basename(p)
    html = open(p).read()

    for attr, val in re.findall(r'\b(href|src)="([^"]+)"', html):
        if not val or val.startswith(("http://", "https://", "mailto:", "tel:", "data:")):
            continue

        if val.startswith("#"):
            if val[1:] and val[1:] not in ids[name]:
                problems.append(f"{name}: dead in-page anchor {val}")
            continue

        path, _, frag = val.partition("#")

        # clean URLs (vercel.json) let a page be referenced without its .html
        # suffix, so fall back to that before calling a link dead
        rel = path.lstrip("/")
        if not os.path.exists(os.path.join(DIST, rel)) and rel:
            if not os.path.exists(os.path.join(DIST, rel + ".html")):
                problems.append(f"{name}: dead {attr} {val}")
                continue
            target = rel + ".html"
        else:
            target = rel
        if frag and target in ids and frag not in ids[target]:
            problems.append(f"{name}: {val} -> #{frag} not present on {target}")

# ---- CSS classes -----------------------------------------------------------------
defined_classes = set(re.findall(r"\.([a-zA-Z][\w-]*)", css))
used_classes = set()
for p in pages:
    for group in re.findall(r'class="([^"]+)"', open(p).read()):
        used_classes.update(c for c in group.split() if c)

missing_classes = used_classes - defined_classes
if missing_classes:
    problems.append("undefined CSS classes: " + ", ".join(sorted(missing_classes)))

# ---- CSS custom properties -------------------------------------------------------
inline_vars = set(re.findall(r'(--[\w-]+)\s*:', " ".join(open(p).read() for p in pages)))
defined_vars = set(re.findall(r"(--[\w-]+)\s*:", css)) | inline_vars
source = css + js + "\n".join(open(p).read() for p in pages)

for name in set(re.findall(r"var\(\s*(--[\w-]+)", source)):
    has_fallback = re.search(r"var\(\s*" + re.escape(name) + r"\s*,", source)
    if name not in defined_vars and not has_fallback:
        problems.append(f"undefined CSS var with no fallback: {name}")

# ---- per page basics -------------------------------------------------------------
for p in pages:
    name = os.path.basename(p)
    html = open(p).read()

    if "utilities.css" in html or "pages.css" in html:
        problems.append(f"{name}: references a deleted stylesheet")

    for img in re.findall(r"<img[^>]*>", html):
        if "alt=" not in img:
            problems.append(f"{name}: <img> without alt")

    if "<h1" not in html:
        problems.append(f"{name}: no <h1>")

    if 'name="description"' not in html:
        problems.append(f"{name}: no meta description")

    title = re.findall(r"<title>([^<]*)</title>", html)
    if not title or len(title[0].strip()) < 15:
        problems.append(f"{name}: missing or too-short <title>")

    # clean URLs are enabled in vercel.json, so the home link is "/" not "/index.html"
    if 'href="/"' not in html and 'href="/index.html"' not in html:
        problems.append(f"{name}: no link back to the home page")

    for tag, attrs, inner in re.findall(r"<(button|a)\b([^>]*)>(.*?)</\1>", html, re.S):
        if not inner.strip() and "aria-label" not in attrs:
            problems.append(f"{name}: empty <{tag}> with no aria-label")

    # every form control needs a label or an aria-label
    for attrs in re.findall(r"<(?:input|select|textarea)\b([^>]*)>", html):
        if 'type="hidden"' in attrs or "aria-label" in attrs:
            continue
        m = re.search(r'id="([\w-]+)"', attrs)
        if not m:
            problems.append(f"{name}: form control without id (cannot be labelled)")
        elif f'for="{m.group(1)}"' not in html:
            problems.append(f"{name}: no <label for={m.group(1)}>")

# ---- report ----------------------------------------------------------------------
print(f"pages: {len(pages)}")
print(f"classes: {len(used_classes)} used / {len(defined_classes)} defined")
print(f"custom properties: {len(defined_vars)} defined")
print()

if problems:
    print(f"{len(problems)} problem(s):")
    for x in dict.fromkeys(problems):
        print("  -", x)
    sys.exit(1)

print("static audit clean")