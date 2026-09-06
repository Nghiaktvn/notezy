#!/usr/bin/env python3
"""
Heuristic PHP structural check: brace/paren/bracket balance and basic
tokenizer-lite scan, done because php -l is unavailable in this sandbox
(no network to install PHP). This is NOT a substitute for a real PHP
parser and cannot catch every syntax error class -- flagged as such in
the audit report.
"""
import sys

def extract_php_segments(src):
    """Pull out only the code between <?php ... ?> tags, since these files
    mix raw HTML/CSS/JS with PHP and a naive full-file scan miscounts CSS
    '{ }' blocks or JS braces as PHP braces."""
    segments = []
    i = 0
    n = len(src)
    while i < n:
        start = src.find("<?php", i)
        if start == -1:
            start = src.find("<?=", i)
            if start == -1:
                break
            tag_len = 3
        else:
            tag_len = 5
        end = src.find("?>", start)
        if end == -1:
            segments.append(src[start + tag_len:])
            break
        segments.append(src[start + tag_len:end])
        i = end + 2
    return "\n".join(segments)


def check(path):
    with open(path, encoding="utf-8") as f:
        raw = f.read()
    src = extract_php_segments(raw)

    depth = {"{": 0, "(": 0, "[": 0}
    pairs = {"}": "{", ")": "(", "]": "["}
    i = 0
    n = len(src)
    in_squote = in_dquote = in_line_comment = in_block_comment = in_heredoc = False
    heredoc_tag = None
    errors = []
    line = 1

    while i < n:
        c = src[i]
        if c == "\n":
            line += 1
            in_line_comment = False

        if in_line_comment:
            i += 1
            continue
        if in_block_comment:
            if src[i:i+2] == "*/":
                in_block_comment = False
                i += 2
                continue
            i += 1
            continue
        if in_squote:
            if c == "\\":
                i += 2
                continue
            if c == "'":
                in_squote = False
            i += 1
            continue
        if in_dquote:
            if c == "\\":
                i += 2
                continue
            if c == '"':
                in_dquote = False
            i += 1
            continue

        if src[i:i+2] == "//" or c == "#":
            in_line_comment = True
            i += 2 if src[i:i+2] == "//" else 1
            continue
        if src[i:i+2] == "/*":
            in_block_comment = True
            i += 2
            continue
        if c == "'":
            in_squote = True
            i += 1
            continue
        if c == '"':
            in_dquote = True
            i += 1
            continue

        if c in "({[":
            depth[c] += 1
        elif c in ")}]":
            open_c = pairs[c]
            depth[open_c] -= 1
            if depth[open_c] < 0:
                errors.append(f"line ~{line}: unmatched closing '{c}'")
                depth[open_c] = 0
        i += 1

    for k, v in depth.items():
        if v != 0:
            errors.append(f"unbalanced '{k}' -> off by {v}")

    if raw.count("<?php") == 0 and raw.count("<?=") == 0:
        errors.append("no <?php or <?= open tag found")

    return errors

if __name__ == "__main__":
    any_fail = False
    for path in sys.argv[1:]:
        errs = check(path)
        if errs:
            any_fail = True
            print(f"FAIL {path}")
            for e in errs:
                print(f"   - {e}")
        else:
            print(f"OK   {path} (brace/paren/bracket balance + basic scan)")
    sys.exit(1 if any_fail else 0)
