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

External Share Playbook (Consolidated XML + Reviewer Note)
- Purpose: When asked to “write a note to my friend” and package the current codebase, do the following.
- Generate consolidated XML:
  - cd into `screenshots/`.
  - Run `python3 consolidate_codebase.py` (or `./consolidate_codebase.py`).
  - This creates `screenshots/consolidated_codebase.xml` containing all relevant source files as `<file path="...">` blocks.
- Compose the reviewer message (include these sections):
  - Context: game/project summary and what the XML contains (paths under `src/`).
  - Problem: concise description of the observed issue and how to reproduce.
  - Evidence: summarize key logs/notifications showing the behavior.
  - Current findings: hypotheses or confirmed root cause candidates.
  - Proposed focus areas: specific components to adjust (client selection, server resolution, notifications, schema consistency).
  - Deliverable: request a single updated XML with full file contents for modified paths.
  - Acceptance criteria: concrete, verifiable behavior for the scenario and UI.
- Return both: the new `consolidated_codebase.xml` and the message.
- Notes:
  - Prefer numeric ids in payloads and keep legacy string fields for compatibility.
  - If asked, include minimal diffs or a change list, but the XML is the canonical transfer artifact.

Subagent: Playwright E2E Runner
- Purpose: When asked to “create a subagent and tell it to test with Playwright”, follow this runbook to drive Studio via the CLI Playwright tools.
- Prereqs:
  - Studio domain only: `https://studio.boardgamearena.com`.
  - Prime account is signed in (or sign in when prompted).
  - Auto‑deploy running (`./deploy.sh --watch`) or perform `./deploy.sh` after code changes.
  - Use a fresh table per scenario (Express Start/Stop) to avoid stale state.
- High‑level flow (per docs/playwright_e2e_guide.md):
  1) Navigate Lobby → Create table → Express start.
  2) On game page, open second player via “see more” (adds `&testuser=`) to control both players in tabs.
  3) For tests, always: actor tab declares; switch to other tab to challenge/pass; return to actor to continue.
  4) Assert counters, notifications, and card movements.
- CLI tool usage patterns:
  - `browser_navigate(url)` → open Lobby and game pages.
  - `browser_snapshot()` → refresh element refs after DOM changes.
  - `browser_evaluate(fn)` → query dynamic elements (e.g., enumerate hand cards and detect type via `getComputedStyle(...).backgroundImage`).
  - `browser_click(ref, element)` / `browser_type(...)` / `browser_press_key(...)` → interact with buttons/overlays; always call `browser_snapshot()` prior to reusing refs.
  - `browser_tabs({ action: 'select', index })` → hop between actor/defender tabs.
- Card type detection in hand (JS snippet for `browser_evaluate`):
  ```js
  (() => Array.from(document.querySelectorAll('[id^="hc_current_hand_item_"], .stockitem')).map(el => {
    const bg = getComputedStyle(el).backgroundImage || '';
    const m = bg.match(/\/([^\/]+)\.(png|jpe?g|webp)/i);
    return { id: el.id, img: m ? m[1] : null };
  }))()
  ```
  - Map `img` to types: kitten→1, showcat→2, alleycat→3, catnip→4, animalcontrol→5, laserpointer→6.
- Test scripts (baseline):
  - K1 (Kitten truthful, unchallenged):
    1) Active tab: click a Kitten card → click “Kitten”.
    2) Other tab: click “Pass”.
    3) Assert: one herd‑FD added for actor only after resolution (no early herdUpdate on declare); hand counts updated.
  - K2 (Kitten truthful, challenged):
    1) Active tab: select Kitten → declare “Kitten”.
    2) Other tab: click “Challenge”.
    3) Actor tab: penalty selection UI appears; select blind card from challenger.
    4) Assert: challengeResult has `was_bluffing:false`; challenger hand −1; exactly one herd add after resolve.
  - Optional bluff (if a Show Cat present):
    1) Active tab: select Show Cat → declare “Kitten”.
    2) Other tab: click “Challenge”.
    3) Assert: challengeResult has `was_bluffing:true`; played card from limbo moved to discard; no herd add; penalty flow for bluffer.
- Assertions to include:
  - Notifications: `challengeResult` includes `was_bluffing`, `declared_type`, `actual_card_type`.
  - No `herdUpdate` immediately after `cardPlayed`; herd add arrives post‑resolution only.
  - Counters: `#hc_hand_count_<playerId>` reflect expected deltas.
- Cleanup per case:
  - ALWAYS return to the table page and click “Express stop” / “Quit game” immediately after each test case. Do not reuse tables between cases.
  - Close all game tabs after stopping. Start the next scenario by creating a brand‑new table (Lobby → Create → Express start).
