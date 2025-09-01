Herding Cats — Kitten Test Plan

Summary
- Non-targeting play; value 2 in herd. Physical card becomes Kitten when added to herd. Tests cover declaration, challenge outcomes, bluff, and UI changes.

Preconditions
- Players A (active) and B (opponent) seated; fresh game; A holds at least one Kitten.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

<a id="k1"></a>
Case K1 — Truthful, unchallenged
1) A declares Kitten; others pass.
Expected:
- A’s played card moves from A hand to A herd-FD as Kitten.
- A hand counter decrements by 1; herd count increments by 1.
- Log shows declaration and herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="k2"></a>
Case K2 — Truthful, challenged and challenge fails
1) A declares Kitten; B challenges.
2) Engine verifies truth; (optional) reveal or mark truthful.
3) A selects a card-back slot from B’s hand in staging area.
Expected:
- Selected B card reveals and discards to B discard; B hand counter -1.
- A’s played card enters A herd-FD.
- Logs: challenge failed, penalty to B, discard identity, herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="k3"></a>
Case K3 — Bluff, unchallenged
1) A plays a non-Kitten but declares Kitten; others pass.
Expected:
- No reveal of the played card; it enters A herd-FD as Kitten (declared identity).
- A hand counter -1; logs reflect Kitten placed to herd.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="k4"></a>
Case K4 — Bluff, challenged and challenge succeeds
1) A declares Kitten; B challenges; verification shows bluff.
2) Reveal played card; A discards it.
3) First challenger (B) selects a slot from A’s hand; reveal + discard.
Expected:
- A loses 2 cards total (played + blind penalty); A hand counter -2.
- Turn ends immediately; no herd placement; logs reflect bluff and penalties.
Cleanup:
- Express Stop the game before proceeding to any other case.

UI/Validation Checklist (all cases)
- Only A can act during A’s turn; others only see Challenge/Pass.
- Staging area shows B’s hand backs in fixed order for blind pick when needed.
- Counters update immediately for both players; page refresh preserves state.

Case Checklist
- [x] [K1 — Truthful, unchallenged](#k1)
- [x] [K2 — Truthful, challenged and challenge fails](#k2)
- [x] [K3 — Bluff, unchallenged](#k3)
- [x] [K4 — Bluff, challenged and challenge succeeds](#k4)
 

Case Index (JSON)
```
{
  "cases": [
    { "id": "k1", "title": "Truthful, unchallenged" },
    { "id": "k2", "title": "Truthful, challenged and challenge fails" },
    { "id": "k3", "title": "Bluff, unchallenged" },
    { "id": "k4", "title": "Bluff, challenged and challenge succeeds" }
  ]
}
```
