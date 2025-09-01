Herding Cats — Agent Onboarding & Runbook

Overview
- This repo contains a Board Game Arena (BGA) Studio implementation of “Herding Cats”.
- All testing is on the Studio domain: https://studio.boardgamearena.com (not the main site).
- Game code follows BGA patterns: PHP server rules, JS client UI, Smarty template.
- End-to-end testing is done manually and via Playwright helper tools in this CLI.

Key Docs (read these in full into your context)
- Game design and rules: game_design.md
- BGA docs: api_docs/bga_documentation.md, api_docs/bga_guide.md
- Test plan index: testplan.md → per-card plans in testplans/
- Playwright guide: docs/playwright_e2e_guide.md

Project Structure (src/)
- herdingcats.game.php: Server logic (Table subclass), state actions, helpers.
- herdingcats.action.php: AJAX endpoints called from client.
- herdingcats.view.php + herdingcats_herdingcats.tpl: Layout and template.
- herdingcats.js / herdingcats.css: Client UI (Dojo/ebg) and styles.
- states.inc.php, material.inc.php, gameinfos.inc.php: State machine, constants/material, metadata.
- dbmodel.sql, stats.json, gamepreferences.json: DB schema, stats, preferences.

Dev Workflow
- Mount Studio files once: ./mount_bga.sh (mounts at ~/BGA_mount)
- Import (one-time): ./pull.sh (Studio → src/)
- Deploy: ./deploy.sh (or ./deploy.sh --watch for auto-sync) - it will be running in background but if user says it is not, you use it like this
- Diff: ./sync_status.sh; Unmount: ./unmount_bga.sh

Studio Quickstart (Manual)
1) Open Control Panel: https://studio.boardgamearena.com/controlpanel
2) Manage games → select “herdingcats”.
3) Optionally open “Game page” to check metadata.
4) Open Lobby: https://studio.boardgamearena.com/lobby?game=13181
5) Click “Create” (Play with friends), then click “Express start” on the table page.
6) Game opens at /1/herdingcats?table=<id> with two test players seated.
7) Switch players: in right panel, click “see more” next to a player to open their view (adds &testuser parameter).
8) Express Stop: on the table page click “Quit game” (or close table from Studio controls).

Playwright Quickstart (CLI)
- Use the Playwright tools exposed by this CLI (navigate, click, evaluate, tabs, snapshot, wait_for).
- Always use Studio URLs; create a fresh table via Lobby → Create → Express start.
- Switch tabs after clicking “see more” to control both players.
- Reference docs/playwright_e2e_guide.md for selectors, flows, and assertions.

Selectors & UI Conventions
- Hand container: #hc_current_hand; cards: #hc_current_hand_item_<n>
  - Card type discovered via element background image (e.g., kitten.jpeg, alleycat.jpeg).
- Declaration overlay: “Declare Card Type” buttons (Kitten, Show Cat, Alley Cat, Catnip, Animal Control, Laser Pointer).
- Challenge window: multiple-active state where non-actor sees “Challenge/Pass”.
- Target overlays: only for targeted types (Alley Cat, Catnip, Animal Control).
- Counters: #hc_hand_count_<playerId>; herds: #hc_herd_face_down_<playerId>, #hc_herd_face_up_<playerId>.

Test Scope & Order
- Start with Kitten K1 (truthful, unchallenged) and K2 (truthful, challenged) from testplans/kitten.md.
- Continue with Alley Cat, Catnip, Animal Control, Laser Pointer, Show Cat plans in testplans/.
- Use Express Start before a block; Express Stop after. Validate logs, counters, and herd updates.

Common Pitfalls
- Wrong domain: always use studio.boardgamearena.com.
- Stale refs: run browser_snapshot() after DOM changes before reusing element refs.
- Multi-active states: challenge windows require switching to the eligible player tab.
- Stale table id/DB missing: recreate table from Lobby and Express start again.
- Translation warnings during setup are expected and do not block testing.

Credentials & Security
- Do not commit secrets. Use .env locally; never paste contents in code or commits.
- Important keys: bga_studio_url, bga_studio_prime_account, alt_bga_studio_accounts, SFTP/DB creds.

Troubleshooting
- Use in-game links: “BGA request&SQL logs”, “unexpected exceptions logs”, and the Input/Output box to trace requests and notifications.
- Client emits actClientLog entries (used in tests) for key state transitions.
- If a server error popup appears, note the stack, reproduce with minimal flow, and verify state args are provided.

Coding Conventions
- PHP: 4 spaces; classes CamelCase; methods lowerCamelCase; constants UPPER_SNAKE_CASE; snake_case DB fields.
- JS: 4 spaces; Dojo/ebg AMD pattern; keep notifications concise and consistent.
- Keep changes localized and consistent with existing code.
- code lives in /src

Handy Studio Links
- Control Panel: https://studio.boardgamearena.com/controlpanel
- Manage Games: https://studio.boardgamearena.com/studio
- Lobby (Herding Cats): https://studio.boardgamearena.com/lobby?game=13181
- Active table: https://studio.boardgamearena.com/1/herdingcats?table=<id>

What To Do First
- Read game_design.md to internalize rules and timing windows (challenge, intercept, ineffective-against-itself, scoring).
- Skim src/states.inc.php to understand state machine and transitions.
- Review src/herdingcats.game.php + herdingcats.action.php for server actions and endpoints.
- Use testplan.md and testplans/* to drive validation; automate with docs/playwright_e2e_guide.md patterns.
