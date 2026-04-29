#!/usr/bin/env python3
"""Strip UTF-8 BOM (EF BB BF) from the start of every PHP file under app/.
PowerShell's `Set-Content -Encoding UTF8` adds a BOM by default which breaks
PHP's `<?php` tag detection. Run once after any bulk PowerShell edit."""

import os
import sys
from pathlib import Path

BOM = b'\xef\xbb\xbf'

def strip_bom_from_dir(root: Path) -> tuple[int, int]:
    fixed = 0
    scanned = 0
    for path in root.rglob('*.php'):
        scanned += 1
        try:
            data = path.read_bytes()
        except OSError as e:
            print(f"  ⚠ skip {path}: {e}", file=sys.stderr)
            continue
        if data.startswith(BOM):
            path.write_bytes(data[len(BOM):])
            fixed += 1
    return scanned, fixed

if __name__ == '__main__':
    root = Path(sys.argv[1] if len(sys.argv) > 1 else 'app')
    if not root.exists():
        print(f"ERROR: {root} not found", file=sys.stderr)
        sys.exit(1)
    scanned, fixed = strip_bom_from_dir(root)
    print(f"Scanned {scanned} files, stripped BOM from {fixed}")
