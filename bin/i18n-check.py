#!/usr/bin/env python3
"""Lists texts that a language file is missing.

    php-free, run from an app's folder:
      python3 vendor/plainshow/kit/bin/i18n-check.py lang/nl.json src lib templates
      python3 vendor/plainshow/kit/bin/i18n-check.py lang/sv.json --json extensions/core.json:label,description,title resources/js app

    It finds t('..'), tn(n, '..', '..'), __('..'), ps_t('..'), ps_h('..'), psT('..')
    and Lang::t('..') in the given files and folders, and prints each text the
    dictionary does not have (add them there; a text equal to the source can be
    left out). Exit code 1 when something is missing.
"""
import json, os, re, sys

out = sys.argv[1]
args = sys.argv[2:]
keys = []
seen = set()

def add(text):
    if text and text not in seen and re.search(r'[A-Za-zÅÄÖåäö]', text):
        seen.add(text)
        keys.append(text)

STR = r"""'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)"|`((?:[^`\\$]|\\.)*)`"""
CALL = re.compile(r"(?<![\w$.])(?:t|__|ps_t|ps_h|psT|PlainShow\.t)\(\s*(?:" + STR + r")")
PLURAL = re.compile(r"(?<![\w$.])(?:tn|ps_tn|plural|Lang::plural)\([^,()]*(?:\([^()]*\))?[^,()]*,\s*(?:" + STR + r")\s*,\s*(?:" + STR + r")")
LANG_T = re.compile(r"Lang::t\(\s*(?:" + STR + r")")

def unescape(m, quote):
    s = m.encode('utf-8').decode('unicode_escape').encode('latin-1').decode('utf-8') if '\\' in m else m
    return s

def scan(path):
    try:
        src = open(path, encoding='utf-8').read()
    except Exception:
        return
    for rx in (CALL, LANG_T):
        for m in rx.finditer(src):
            g = next(x for x in m.groups() if x is not None)
            add(unescape(g, None))
    for m in PLURAL.finditer(src):
        groups = [x for x in m.groups()]
        one = next((x for x in groups[0:3] if x is not None), None)
        many = next((x for x in groups[3:6] if x is not None), None)
        add(unescape(one or '', None)); add(unescape(many or '', None))

i = 0
while i < len(args):
    a = args[i]
    if a == '--json':
        spec = args[i + 1]; i += 2
        file, fields = spec.split(':')
        wanted = fields.split(',')
        def walk(node):
            if isinstance(node, dict):
                for k, v in node.items():
                    if k in wanted and isinstance(v, str): add(v)
                    else: walk(v)
            elif isinstance(node, list):
                for v in node: walk(v)
        walk(json.load(open(file)))
        continue
    if a == '--values':  # PHP array values of a const map: --values file
        file = args[i + 1]; i += 2
        for m in re.finditer(r"=>\s*'((?:[^'\\]|\\.)*)'", open(file).read()):
            add(m.group(1))
        continue
    i += 1
    if os.path.isdir(a):
        for root, dirs, files in os.walk(a):
            dirs[:] = [d for d in dirs if d not in ('node_modules', 'vendor', '.git', 'build', 'dist', 'js') or root.endswith('apps/plainshow')]
            for f in files:
                if f.endswith(('.ts', '.tsx', '.php', '.js', '.cjs')) and not f.endswith('.min.js'):
                    scan(os.path.join(root, f))
    else:
        scan(a)

have = json.load(open(out)) if os.path.exists(out) else {}
missing = [k for k in keys if k not in have]
for k in missing:
    print(json.dumps(k, ensure_ascii=False))
print(f'{len(missing)} of {len(keys)} texts missing from {out}', file=sys.stderr)
sys.exit(1 if missing else 0)
