#!/usr/bin/env python3
"""Fail when code uses a language key that no en-GB .ini file defines.

Scans PHP, XML and JS under packages/ for COM_/PKG_/PLG_ keys of this
package, plus the keys the code builds at runtime (listed below), and checks
each one against every en-GB .ini in the tree. Run from the repo root:

    python3 tools/check-language.py
"""

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent / "packages"
KEY = re.compile(r"\b((?:COM|PKG|PLG_[A-Z]+)_CSEXTENSIONMANAGER(?:_[A-Z0-9_]+)?)\b")

# Keys assembled at runtime: prefix + one of the suffixes.
DYNAMIC = {
    "COM_CSEXTENSIONMANAGER_ACCOUNT_STATE_": [
        "ACTIVE", "LAPSED", "NOT_A_MEMBER", "BLACKLISTED", "INSTALLATION_NOT_FOUND",
        "PACKAGE_NOT_FOUND", "EMAIL_MISMATCH", "UNLINKED", "INVALID_REQUEST",
    ],
    "COM_CSEXTENSIONMANAGER_ACTION_": [
        "INSTALL", "UPDATE", "DOWNGRADE", "REINSTALL", "UNINSTALL", "ENABLE",
        "DISABLE", "REFRESH", "FIX", "ACCOUNT", "NOTIFY",
    ],
    "COM_CSEXTENSIONMANAGER_ACTIONLOG_": ["INSTALL", "UPDATE", "DOWNGRADE", "REINSTALL", "UNINSTALL"],
    "COM_CSEXTENSIONMANAGER_FIX_": [
        "ADD_UPDATE_SITE", "ENABLE_UPDATE_SITE", "REPAIR_UPDATE_SITE", "DEDUPE_UPDATE_SITES",
        "SYNC_DLID", "DELETE_UPDATE_SITE", "PURGE_UPDATES", "ENABLE_EXTENSION", "REINSTALL",
        "INSTALL_PARENT",
    ],
    "COM_CSEXTENSIONMANAGER_INSTALL_OK_": ["INSTALL", "UPDATE", "DOWNGRADE", "REINSTALL"],
    "COM_CSEXTENSIONMANAGER_SOURCE_": ["PACKAGES", "FEEDS", "CACHE"],
    "COM_CSEXTENSIONMANAGER_STATUS_": ["SUCCESS", "WARNING", "ERROR", "SKIP"],
    "COM_CSEXTENSIONMANAGER_TRIGGER_": ["MANUAL", "TASK", "SYSTEM"],
    "COM_CSEXTENSIONMANAGER_TYPE_": [
        "PACKAGE", "COMPONENT", "PLUGIN", "MODULE", "LIBRARY", "TEMPLATE", "FILE", "LANGUAGE",
    ],
    # TaskPluginTrait reads <prefix>_TITLE and <prefix>_DESC.
    "PLG_TASK_CSEXTENSIONMANAGER_CHECK_": ["TITLE", "DESC"],
}

defined = set()
for ini in ROOT.rglob("en-GB/*.ini"):
    for line in ini.read_text(encoding="utf-8").splitlines():
        m = re.match(r"^([A-Z0-9_]+)=", line)
        if m:
            defined.add(m.group(1))

used = set()
for path in list(ROOT.rglob("*.php")) + list(ROOT.rglob("*.xml")) + list(ROOT.rglob("*.js")):
    for key in KEY.findall(path.read_text(encoding="utf-8")):
        if not key.endswith("_"):
            used.add(key)

for prefix, suffixes in DYNAMIC.items():
    used.update(prefix + s for s in suffixes)

# Prefixes written out in full in code but never looked up on their own.
used.discard("PLG_TASK_CSEXTENSIONMANAGER_CHECK")

missing = sorted(k for k in used if k not in defined)

if missing:
    print("Language keys used but not defined in any en-GB .ini:")
    for key in missing:
        print("  " + key)
    sys.exit(1)

print(f"OK: {len(used)} keys used, all defined.")
