#!/usr/bin/env python3
"""Check every translation against its en-GB file.

For each language folder next to an en-GB folder under packages/, every
en-GB .ini must have a same-named file with the same keys, and each value
must keep the en-GB value's placeholders (%s, %1$s, %d ...) and HTML tags.
Values may not contain a bare double quote (Joomla's INI parser stops
there); use \\" like en-GB does, or the language's own quote marks.
Files must be UTF-8 without a BOM. Run from the repo root:

    python3 tools/check-translations.py [lang ...]
"""

import collections
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent / "packages"
LINE = re.compile(r'^([A-Z0-9_]+)="(.*)"\s*$')
PLACEHOLDER = re.compile(r"%(?:\d+\$)?[sd]")
TAG = re.compile(r"</?([a-zA-Z][a-zA-Z0-9]*)\b")


def parse(path: pathlib.Path) -> tuple[dict[str, str], list[str]]:
    raw = path.read_bytes()
    errors = []

    if raw.startswith(b"\xef\xbb\xbf"):
        errors.append("has a UTF-8 BOM")

    try:
        text = raw.decode("utf-8")
    except UnicodeDecodeError as e:
        return {}, [f"not UTF-8 ({e})"]

    values = {}

    for n, line in enumerate(text.splitlines(), 1):
        if not line.strip() or line.lstrip().startswith(";"):
            continue

        m = LINE.match(line)

        if not m:
            errors.append(f"line {n}: not KEY=\"value\": {line[:80]}")
            continue

        key, value = m.groups()

        if re.search(r'(?<!\\)"', value):
            errors.append(f"line {n}: {key} has a bare double quote")

        if re.search(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]", value):
            errors.append(f"line {n}: {key} has a control character")

        if key in values:
            errors.append(f"line {n}: {key} defined twice")

        values[key] = value

    return values, errors


def check(lang: str) -> int:
    problems = 0

    for source in sorted(ROOT.rglob("en-GB/*.ini")):
        target = source.parent.parent / lang / source.name
        rel = target.relative_to(ROOT)

        if not target.exists():
            print(f"{rel}: missing")
            problems += 1
            continue

        en, _ = parse(source)
        tr, errors = parse(target)

        for e in errors:
            print(f"{rel}: {e}")
        problems += len(errors)

        for key in sorted(set(en) - set(tr)):
            print(f"{rel}: missing key {key}")
            problems += 1

        for key in sorted(set(tr) - set(en)):
            print(f"{rel}: extra key {key}")
            problems += 1

        for key in sorted(set(en) & set(tr)):
            if collections.Counter(PLACEHOLDER.findall(en[key])) != collections.Counter(PLACEHOLDER.findall(tr[key])):
                print(f"{rel}: {key} placeholders differ: {PLACEHOLDER.findall(en[key])} vs {PLACEHOLDER.findall(tr[key])}")
                problems += 1

            if collections.Counter(TAG.findall(en[key])) != collections.Counter(TAG.findall(tr[key])):
                print(f"{rel}: {key} HTML tags differ")
                problems += 1

    return problems


langs = sys.argv[1:] or sorted({p.parent.name for p in ROOT.rglob("*.ini")} - {"en-GB"})
total = 0

for lang in langs:
    found = check(lang)
    total += found
    print(f"{lang}: {'OK' if found == 0 else f'{found} problem(s)'}")

sys.exit(1 if total else 0)
