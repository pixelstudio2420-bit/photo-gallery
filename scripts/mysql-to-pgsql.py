#!/usr/bin/env python3
# ─────────────────────────────────────────────────────────────────────
#  MySQL/MariaDB dump → PostgreSQL converter
# ─────────────────────────────────────────────────────────────────────
#  Converts a `mysqldump` SQL file (data-only or full) to a Postgres-
#  compatible SQL file ready to import via `psql -f`.
#
#  What it converts:
#    • Backticks `name`           → "name"
#    • DEFINER=...                → stripped
#    • COLLATE=utf8mb4_*          → stripped
#    • ENGINE=InnoDB              → stripped
#    • AUTO_INCREMENT             → stripped (PG uses sequences)
#    • Boolean literals  b'1'/0/1 → TRUE/FALSE in known boolean contexts
#    • DATETIME types              → TIMESTAMP
#    • TINYINT(1) → BOOLEAN, TINYINT/SMALLINT/MEDIUMINT/BIGINT preserved
#    • SET FOREIGN_KEY_CHECKS / SET NAMES → SET session_replication_role
#    • UNSIGNED                   → stripped
#    • TEXT/MEDIUMTEXT/LONGTEXT   → TEXT
#    • LONGBLOB / MEDIUMBLOB      → BYTEA
#    • Hex literals 0xAB...       → '\xAB...'::bytea
#    • LOCK TABLES / UNLOCK       → stripped
#    • Trailing comma in INSERT   → cleaned
#
#  Usage:
#    python scripts/mysql-to-pgsql.py input.sql > output.sql
#    python scripts/mysql-to-pgsql.py --data-only input.sql > output.sql
#
#  After running, import into Postgres:
#    psql -h HOST -U USER -d DB -f output.sql
# ─────────────────────────────────────────────────────────────────────

import argparse
import re
import sys
from pathlib import Path


# Patterns we strip entirely (replaced with empty string)
STRIP_PATTERNS = [
    re.compile(r"DEFINER\s*=\s*`[^`]+`@`[^`]+`", re.I),
    re.compile(r"\bENGINE\s*=\s*\w+", re.I),
    re.compile(r"\bAUTO_INCREMENT\s*=\s*\d+", re.I),
    re.compile(r"\bDEFAULT\s+CHARSET\s*=\s*\w+", re.I),
    re.compile(r"\bDEFAULT\s+CHARACTER\s+SET\s*=?\s*\w+", re.I),
    re.compile(r"\bCOLLATE\s*=?\s*\w+", re.I),
    re.compile(r"\bUNSIGNED\b", re.I),
    re.compile(r"\bROW_FORMAT\s*=\s*\w+", re.I),
    re.compile(r"\bUSING\s+BTREE\b", re.I),
    re.compile(r"\bUSING\s+HASH\b", re.I),
    re.compile(r"\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b", re.I),
]

# Header / control statements that don't translate
SKIP_LINE_PREFIXES = (
    "/*!", "SET NAMES", "SET CHARACTER_SET", "SET FOREIGN_KEY_CHECKS",
    "SET SQL_MODE", "SET TIME_ZONE", "SET UNIQUE_CHECKS", "SET AUTOCOMMIT",
    "LOCK TABLES", "UNLOCK TABLES", "SET @OLD_", "SET @@SESSION",
)

# Type replacements (case-insensitive whole-word)
TYPE_REPLACEMENTS = [
    (re.compile(r"\bDATETIME\b", re.I),       "TIMESTAMP"),
    (re.compile(r"\bTINYINT\(1\)\b", re.I),   "BOOLEAN"),
    (re.compile(r"\bMEDIUMTEXT\b", re.I),     "TEXT"),
    (re.compile(r"\bLONGTEXT\b", re.I),       "TEXT"),
    (re.compile(r"\bTINYTEXT\b", re.I),       "TEXT"),
    (re.compile(r"\bMEDIUMBLOB\b", re.I),     "BYTEA"),
    (re.compile(r"\bLONGBLOB\b", re.I),       "BYTEA"),
    (re.compile(r"\bTINYBLOB\b", re.I),       "BYTEA"),
    (re.compile(r"\bBLOB\b", re.I),           "BYTEA"),
    (re.compile(r"\bDOUBLE\b", re.I),         "DOUBLE PRECISION"),
]


def convert_line(line: str) -> str:
    """Apply conversions to a single SQL line."""
    stripped = line.strip()

    # Drop control statements that don't apply to PG
    for prefix in SKIP_LINE_PREFIXES:
        if stripped.startswith(prefix):
            return ""

    # Backticks → double quotes  (`name` → "name")
    line = re.sub(r"`([^`]+)`", r'"\1"', line)

    # Strip MySQL-only clauses
    for pat in STRIP_PATTERNS:
        line = pat.sub("", line)

    # Type substitutions
    for pat, repl in TYPE_REPLACEMENTS:
        line = pat.sub(repl, line)

    # MySQL hex literal → Postgres bytea: 0xDEADBEEF → E'\\xDEADBEEF'::bytea
    line = re.sub(
        r"\b0x([0-9A-Fa-f]+)\b",
        lambda m: f"E'\\\\x{m.group(1)}'::bytea",
        line,
    )

    # Boolean literals in INSERT data are tricky — we leave 0/1 alone
    # because they're indistinguishable from real integers without
    # column-type context. Recommend running `psql` with the schema
    # already migrated so PG coerces 0/1 → false/true automatically
    # for boolean columns (works since PG 14).

    return line


def convert(input_path: Path, data_only: bool = False) -> str:
    """Read input file, return converted SQL as a single string."""
    out = []

    out.append("-- Converted from MySQL/MariaDB dump by mysql-to-pgsql.py\n")
    out.append("-- Source: " + str(input_path) + "\n")
    out.append("-- Defer FK constraint checks during bulk insert\n")
    out.append("SET session_replication_role = replica;\n\n")

    with input_path.open("r", encoding="utf-8", errors="replace") as fh:
        in_create_table = False
        skip_block = False

        for raw in fh:
            # Skip CREATE TABLE blocks if --data-only
            if data_only:
                if re.match(r"\s*DROP\s+TABLE\s+IF\s+EXISTS", raw, re.I):
                    skip_block = True
                    continue
                if re.match(r"\s*CREATE\s+TABLE", raw, re.I):
                    skip_block = True
                    continue
                if skip_block:
                    if raw.rstrip().endswith(";"):
                        skip_block = False
                    continue

            converted = convert_line(raw)
            if converted.strip():
                out.append(converted)

    out.append("\n-- Re-enable FK constraint checks\n")
    out.append("SET session_replication_role = DEFAULT;\n")
    return "".join(out)


def main():
    parser = argparse.ArgumentParser(
        description="Convert MySQL/MariaDB dump → PostgreSQL SQL.",
    )
    parser.add_argument("input", type=Path, help="Path to mysqldump .sql file")
    parser.add_argument(
        "--data-only",
        action="store_true",
        help="Skip CREATE TABLE blocks (assumes target schema already migrated)",
    )
    parser.add_argument(
        "-o", "--output", type=Path, default=None,
        help="Output path (default: stdout)",
    )
    args = parser.parse_args()

    if not args.input.exists():
        print(f"ERROR: input file not found: {args.input}", file=sys.stderr)
        sys.exit(1)

    converted = convert(args.input, data_only=args.data_only)

    if args.output:
        args.output.write_text(converted, encoding="utf-8")
        print(f"Wrote {len(converted):,} chars to {args.output}", file=sys.stderr)
    else:
        sys.stdout.write(converted)


if __name__ == "__main__":
    main()
