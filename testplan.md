Herding Cats — End-to-End Test Plan (BGA Studio)

Scope
- End-to-end functional tests for Herding Cats using BGA Studio (manual or Playwright-driven).
- Validates declarations, challenge flow, interceptions, ineffective-against-itself, zone/visibility, and scoring.

Prerequisites
- BGA Studio table with 2–4 dev accounts (Playwright can automate logins via .env).
- Fresh game state unless a case specifies mid-game setup.
- Auto-deploy on save is active; refresh table between cases as needed.

Playwright Start/Stop
- Before running any test case: Express Start the game via Studio (automated by Playwright).
- After completing each test case: ALWAYS perform Express Stop on the table page (click header “Logo” → “Express stop”/“Quit game”). This prevents stale state from affecting subsequent tests.

Conventions
- Players: A (active), B (defender), C/D (other challengers).
- Zones: hand (hidden), herd-FD (face-down), herd-FU (face-up), discard (public).
- “Staging area” = UI overlay showing card-back slots for hidden selections.
- “Counters” = hand size next to each player name; herd count if present.

Cross-Cutting Checks (apply to every case)
- Logs: declaration, challenges, reveals, interceptions, discards, herd additions.
- UI: correct enable/disable of actions; target selection gates (e.g., only FD herd for Animal Control).
- Counters: hand counts adjust; herd additions visible to all (FD), identity only to owner when appropriate.
- Refresh: reloading the page reconstructs the same state via getAllDatas.

Mechanics Test Matrix
- Declarations: out-of-turn blocked; in-turn requires declare + optional target.
- Challenge flow: pass/one/many challengers; truthful vs bluff outcomes; first-challenger penalty-pick on bluff.
- Interception (Laser Pointer): from hand (vs. Alley Cat/Catnip) and from herd (vs. Animal Control); LP claim may be challenged; on success, selected card remains hidden and untouched; LP goes face-up to discard; attacker’s played card still enters herd-FD.
- Ineffective-against-itself: Alley Cat↔Alley Cat (hand), Catnip↔Catnip (hand), Animal Control↔Animal Control (herd) behave per spec.
- End condition: if any player reaches 0 cards in hand at end of a turn, game ends and scoring runs.
- Scoring: Show Cat=5 (or 7 if any Kitten present), Kitten=2, Alley Cat=1, Catnip=1, Animal Control=0, Laser Pointer=0; hand bonus +1 per 2 cards (rounded up).

Card-Specific Plans
- See per-card documents under testplans/:
  - testplans/kitten.md
  - testplans/show_cat.md
  - testplans/alley_cat.md
  - testplans/catnip.md
  - testplans/animal_control.md
  - testplans/laser_pointer.md

Progress Tracking
- Each per-card plan ends with a case checklist. Mark "[x]" when a case passes; leave "[ ]" and append "❌" if failing.
- Work through cases top-to-bottom. When a case fails, capture logs/screenshots, fix, retest, then change to "[x]".
- Use Express Start before each case and Express Stop after each case. Reset tables between attempts if state is ambiguous.

Playwright Notes
- Prefer deterministic setups: if specific cards are required, reset until present or use developer helpers if available.
- Drive: declare → challenge dialog(s) → target selection overlay → optional LP interception → resolution → end-turn.
- Assert: DOM texts, counters, element visibility, notifications, and card movements (e.g., remove from hand, add to herd-FD).
