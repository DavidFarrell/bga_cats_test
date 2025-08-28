# Repository Guidelines

## Project Structure & Module Organization
- `src/`: Main game code for Board Game Arena (BGA).
  - `herdingcats.game.php`: Server-side game logic (Table subclass).
  - `herdingcats.action.php`: Server endpoints for client actions.
  - `herdingcats.view.php`: Game page rendering; `herdingcats_herdingcats.tpl` template.
  - `herdingcats.js` / `herdingcats.css`: Client UI (Dojo/ebg) and styles.
  - `states.inc.php`, `material.inc.php`, `gameinfos.inc.php`: State machine, constants/material, metadata.
  - `dbmodel.sql`, `stats.json`, `gamepreferences.json`: DB schema, statistics, preferences.
  - `img/`: Assets used by the client.
- Top-level docs: `README.md`, `DEPLOYMENT_GUIDE.md`, design/plan files, `screenshots/`.

## Build, Test, and Development Commands
- `./mount_bga.sh`: Mount your BGA Studio project at `~/BGA_mount` (macFUSE/sshfs required).
- `./pull.sh`: One-time import from BGA mount into `src/`.
- `./deploy.sh [--watch]`: Sync `src/` to BGA mount; `--watch` auto-syncs on changes.
- `./sync_status.sh`: Show diffs between `src/` and the mount.
- `./unmount_bga.sh`: Unmount when done.

## Coding Style & Naming Conventions
- PHP: 4 spaces; classes `CamelCase` (e.g., `HerdingCats`); methods `lowerCamelCase` (e.g., `setupNewGame`); constants `UPPER_SNAKE_CASE`; prefer snake_case for DB fields and global labels.
- JS (Dojo/ebg): 4 spaces; `lowerCamelCase` for methods/vars; keep AMD `define([...])` structure.
- Files: Follow BGA patterns (`herdingcats.game.php`, `.action.php`, `.view.php`). Keep changes localized and consistent.
- Linting: No enforced linters; run `php -l <file>` for quick syntax checks.

## Testing Guidelines
- Automated tests are not configured. Use BGA Studio tables for manual testing.
- Typical loop: `./deploy.sh --watch` → refresh Studio table → verify logs/UI. Test multi-player flows and state transitions (`states.inc.php`).
- Keep DB/schema changes in `dbmodel.sql`; validate Studio DB resets as needed.

## Commit & Pull Request Guidelines
- Commits: Imperative, concise subject; include context (e.g., "Add action handler for declare"). Group related changes.
- PRs: Clear description, scope of changes, affected states/actions, screenshots from `screenshots/` when UI changes, and links to relevant docs or issues.

## Security & Configuration Tips
- Do not commit secrets. Use `.env` (ignored) as per `.env_example`. Avoid modifying `LICENCE_BGA`.
- Verify mount credentials locally; never store Studio passwords in code or commits.
