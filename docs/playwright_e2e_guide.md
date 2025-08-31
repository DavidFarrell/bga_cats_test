Herding Cats — Playwright E2E Guide (Studio)

Purpose
- Run end-to-end tests against BGA Studio for Herding Cats using the Playwright tools available in this CLI.
- Exercise flows like Express Start/Stop, player switching, declarations, challenges, target selection, and UI assertions.

Prerequisites
- Use the Studio domain: `https://studio.boardgamearena.com` (NOT the main site domain).
- `.env` holds credentials and IDs: prime account, alternates, and the Studio base URL.
- Source auto‑deploy is running (`./deploy.sh --watch`) so changes reflect during tests.

Key Tools (CLI)
- `browser_navigate(url)`: Open a page.
- `browser_click(ref|role|text)`: Click by element snapshot ref or role+name.
- `browser_fill_form(...)`: Fill a specific field by ref and name.
- `browser_evaluate(fn)`: Run JS in page to query DOM (useful for dynamic locators).
- `browser_snapshot()`: Refresh the element refs after DOM updates.
- `browser_tabs(action: select|close, index)`: Switch between player tabs.
- `browser_wait_for({ text|textGone|time })`: Synchronize on UI text.

Basic Workflow
1) Navigate to Studio
   - `browser_navigate('https://studio.boardgamearena.com/')`
   - Verify header shows the prime account (e.g., `PaidiaGames0`). If not, sign in on Studio and retry.

2) Open Control Panel → Manage games
   - `browser_navigate('https://studio.boardgamearena.com/controlpanel')`
   - Click “Manage games”, then your project “herdingcats”.
   - From “Manage game”, open “Game page” in a new tab to confirm metadata if needed.

3) Create table and Express Start
   - Go to the Lobby for the game: `browser_navigate('https://studio.boardgamearena.com/lobby?game=13181')`.
   - Click “Create” (Play with friends section).
   - On the table page, click “Express start”.
   - The game will open at `/1/herdingcats?table=XXXXX` (two players auto‑seated).

4) Switch player views
   - In the right player panel, click “see more” next to a player name to open their view in a new tab (`&testuser=<playerId>` added).
   - Use `browser_tabs({ action: 'select', index: N })` to toggle between players.

5) Locating cards in hand
   - Hand container id: `#hc_current_hand`.
   - Each card has id `hc_current_hand_item_<n>` and a background image revealing its type (e.g., `kitten.jpeg`).
   - Use `browser_evaluate` to enumerate and filter by computed style:
     ```js
     (() => Array.from(document.querySelectorAll('[id^="hc_current_hand_item_"]')).map(el => {
       const bg = getComputedStyle(el).backgroundImage || '';
       const m = bg.match(/\/([^\/]+)\.(png|jpe?g|webp)/i);
       return { id: el.id, type: m ? m[1] : null };
     }))()
     ```

6) Declaring a card (e.g., Kitten K1)
   - In the active player tab: click a desired card element (by ref or via `browser_evaluate(() => document.getElementById('hc_current_hand_item_100').click())`).
   - The “Declare Card Type” overlay appears. Click “Kitten”.
   - Expect: hand count decrements for the actor; challenge window opens for the other player.

7) Challenge window
   - Switch to the non‑active player tab via “see more” or `browser_tabs`.
   - You can click “Pass” (K1) or “Challenge” (K2). For K2 truth‑case, after pass/challenge resolution you should see:
     - Actor hand −1; herd +1 (card added to herd‑FD).
     - If challenged and truthful: challenger loses one blind card.

8) Assertions to verify
   - Hand counters: `#hc_hand_count_<playerId>` reflect expected values.
   - Logs/notifications: the “Input / Output” box updates (e.g., `cardPlayed`, `herdUpdate`).
   - UI text: “Declared as: Kitten”, “Waiting for possible challenges”, or “You may challenge the declaration”.
   - Herd change: `hc_herd_face_down_<playerId>` count increments after add.

9) Cleanup — Express Stop
   - From the game tab, click the header “Logo” to return to the table page, then click “Express stop” (or “Quit game”). Confirm the table shows “Game has ended”. Always do this immediately after a scenario to avoid stale state.

Locator Strategy Tips
- Prefer roles and accessible names when available: buttons (“Kitten”), links (“Express start”, “Pass”, “Challenge”).
- After each action that changes the DOM, run `browser_snapshot()` before reusing `ref` values.
- For dynamic elements (hand cards) use `browser_evaluate` to compute ids based on styles or attributes.

Sample Flow: Kitten K1 (Truthful, Unchallenged)
- Active player tab:
  - Click a Kitten card → click “Kitten”.
  - Observe “Waiting for possible challenges”.
- Other player tab:
  - Click “Pass”.
- Back to active player tab:
  - Expect herd‑FD +1, hand −1; state returns to “You must declare a card identity and play it”.
  - Cleanup: perform Express Stop on the table page.

Sample Flow: Kitten K2 (Truthful, Challenged)
- Repeat K1 through declaration, then switch to other player tab.
- Click “Challenge”.
- Expect server to resolve challenge; actor selects a blind penalty from challenger (if implemented), then card added to herd‑FD.
- Verify hand counters (challenger −1, actor −1) and logs show penalty + placement.

Common Pitfalls
- Wrong domain: always use `studio.boardgamearena.com` for Studio testing.
- Stale refs: element `ref` ids change after navigation; call `browser_snapshot()` to refresh.
- Multi‑active states: challenge windows require switching to the eligible player tab.
- Express Start errors: create a fresh table from Lobby if table id is stale or DB missing.

Express Start/Stop Checklist
- Always “Express start” before running a scenario, and “Express stop” immediately after completing it.
- Keep the test plan’s Start/Stop guidance consistent across all card tests.
