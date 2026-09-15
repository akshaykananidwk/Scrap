# The GitHub update system

ScrapX updates itself from a GitHub repository — no SSH, no FTP, no command line.

## Setup

1. Push this codebase to a GitHub repository (private is fine).
2. In **Admin → Updates**, enter the owner, repository and branch.
3. For a private repository, create a GitHub personal access token with `repo` scope and paste it.

The token is encrypted with **AES-256-GCM** before it is stored. It is never rendered into HTML,
JavaScript, logs, API responses or error messages. Re-opening the page shows only
"Stored — leave blank to keep it".

Choose a channel:

- **Branch** (default) — track the head of a branch. A new commit means an update is available even
  if the version string has not changed.
- **Releases** — track tagged releases only. Better for production.

## What happens when you apply an update

15 steps, in order. If any step throws, the whole update rolls back.

1. Verify the repository connection
2. Read the remote manifest (`version.json`) and check compatibility (PHP version, extensions)
3. **Take a full backup** — database and files (when enabled)
4. Enter maintenance mode
5. Download the archive from GitHub
6. Verify and extract it to a temporary directory
7. Build the file list, excluding repo-only files (`.git`, `.github`, tests, `README`)
8. **Skip every protected path** — nothing in the list below is touched
9. Compare checksums and skip files that are already identical
10. Copy new and changed files into place
11. Record every file with its action (`added`, `updated`, `skipped_protected`, `skipped_identical`, `failed`)
12. Run pending database migrations
13. Clear the application cache
14. Run the health check
15. Leave maintenance mode and write the result

## Protected paths — never overwritten

```
config.php        .env              uploads/
storage/          backup/           backups/
logs/             user_uploads/     public/uploads/     storage/uploads/
```

You can add more under Admin → Updates → Extra protected paths (one per line). Your database
credentials, your per-installation application key and every file your users uploaded are safe by
construction, not by convention.

## Automatic rollback

If any step fails, ScrapX restores the pre-update backup — files **and** database — and records the
update as `rolled_back` with the error and a step-by-step log. A successful update can also be
rolled back manually from its detail page while the backup still exists.

## The manifest

`version.json` in the repository root drives compatibility checks:

```json
{
  "version": "1.0.0",
  "database_version": "0013",
  "minimum_php": "8.2.0",
  "required_extensions": ["pdo_mysql", "mbstring", "openssl"],
  "release_notes": "What changed in this release."
}
```

If the server cannot satisfy the manifest, the update is refused **before** anything is downloaded.

## Requirements

Applying an update needs `curl`, `zip`, and a writable application directory. Admin → Updates says
plainly which of the three is missing and disables the button until they are all present.
