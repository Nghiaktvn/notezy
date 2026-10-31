"""Create one small, reproducible Docker-ready source ZIP for E-learning."""

from __future__ import annotations

import subprocess
import zipfile
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "submission" / "Notezy-Docker-Source.zip"
LIMIT = 100 * 1024 * 1024
EXCLUDED_PREFIXES = ("supabase/", "tests/", "ai_agent/tests/")
EXCLUDED_FILES = {
    "scripts/check_supabase.php",  # PostgreSQL-only preflight, unused by Docker.
    "web_ck/table_upscaled.png",  # Removed local illustration.
}
REQUIRED_FILES = {
    ".env.example",
    "Dockerfile",
    "README.md",
    "docker-compose.yml",
    "entrypoint.sh",
    "index.php",
    "note.sql",
    "migrations.sql",
    "scripts/seed_demo_accounts.php",
    "scripts/seed_demo_showcase.php",
}


def main() -> None:
    tracked = subprocess.run(
        ["git", "ls-files", "-z"], cwd=ROOT, check=True, capture_output=True
    ).stdout
    names = {part.decode("utf-8") for part in tracked.split(b"\0") if part}
    names.update({"scripts/package_submission.py", "scripts/seed_demo_accounts.php", "scripts/seed_demo_showcase.php"})
    names = {
        name
        for name in names
        if name not in EXCLUDED_FILES
        and not name.startswith(EXCLUDED_PREFIXES)
        and (ROOT / name).is_file()
    }
    required_missing = REQUIRED_FILES - names
    if required_missing:
        raise SystemExit(f"Required Docker files missing: {sorted(required_missing)}")

    OUTPUT.parent.mkdir(exist_ok=True)
    with zipfile.ZipFile(
        OUTPUT, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6
    ) as archive:
        for name in sorted(names):
            archive.write(ROOT / name, arcname=name)

    with zipfile.ZipFile(OUTPUT) as archive:
        corrupt = archive.testzip()
        if corrupt:
            raise SystemExit(f"Corrupt ZIP entry: {corrupt}")
        if set(archive.namelist()) != names:
            raise SystemExit("ZIP contents differ from the source list")

    size = OUTPUT.stat().st_size
    if size >= LIMIT:
        raise SystemExit(f"ZIP exceeds 100 MB: {size / 1024**2:.2f} MB")
    print(f"Created: {OUTPUT}")
    print(f"Archive files: 1; source files inside: {len(names)}")
    print(f"Size: {size / 1024**2:.2f} MB (limit: 100 MB per uploaded file)")
    print("Included: Docker, PHP, MySQL SQL, frontend, functional assets, demo seeds, README")
    print("Excluded: .env, .git, test files, test images, dependencies, caches, outputs, Supabase")


if __name__ == "__main__":
    main()
