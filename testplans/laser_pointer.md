Herding Cats — Laser Pointer Test Plan

Summary
- Non-targeting on play (value 0). Special: may be discarded from hand to intercept hand-targeting attacks (Alley Cat/Catnip) or from herd to intercept herd-targeting attacks (Animal Control). Interception claim itself may be challenged.

Preconditions
- Players A (active), B (defender), C (other) as needed.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

Play-As-Card Cases
<a id="lp1"></a>
Case LP1 — Truthful, unchallenged
1) A declares Laser Pointer; others pass.
Expected: A’s played card enters A herd-FD as Laser Pointer; A hand counter -1; logs reflect placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="lp2"></a>
Case LP2 — Truthful, challenged and challenge fails
1) B challenges; truth stands; A selects blind penalty from B; reveal + discard.
2) A’s LP enters herd-FD.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="lp3"></a>
Case LP3 — Bluff, challenged and challenge succeeds
1) Reveal shows A bluffed.
Expected: A discards played card + 1 blind penalty; turn ends; no herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

Interception From Hand (vs. Alley Cat/Catnip)
<a id="lp4"></a>
Case LP4 — Hand intercept, unchallenged
1) B is targeted in hand (AC/CN); after slot selection, B declares LP from hand.
Expected: Selected slot remains hidden; attack canceled; B discards LP face-up; A’s played card is discarded face-up (no herd placement); logs reflect cancel.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="lp5"></a>
Case LP5 — Hand intercept claim challenged
1) C challenges B’s LP-from-hand.
Expected (truthful): B reveals/discards LP; C discards blind penalty; cancel stands; selected slot stays hidden; A’s played card is discarded face-up.
Expected (bluff): B takes penalty; original attack proceeds; selected slot is revealed and resolved.
Cleanup:
- Express Stop the game before proceeding to any other case.

Interception From Herd (vs. Animal Control)
<a id="lp6"></a>
Case LP6 — Herd intercept, unchallenged
1) B is targeted in herd; after FD slot selection, B declares LP from herd.
Expected: Selected herd card remains hidden; attack canceled; B discards LP face-up from herd; A’s played card is discarded face-up (no herd placement).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="lp7"></a>
Case LP7 — Herd intercept claim challenged
1) C challenges B’s LP-from-herd.
Expected (truthful): B discards LP; C discards blind penalty; cancel stands; selected card stays hidden; A’s played card is discarded face-up.
Expected (bluff): B takes penalty; original AN proceeds revealing selected herd card.
Cleanup:
- Express Stop the game before proceeding to any other case.

Additional Checks
- Face-up LP in herd may still be used for interception.
- Discarded LP should appear face-up in discard; all players can inspect discard contents.
- Refresh preserves discard visibility and herd contents.

Case Checklist
- [ ] [LP1 — Truthful, unchallenged](#lp1)
- [ ] [LP2 — Truthful, challenged and challenge fails](#lp2)
- [ ] [LP3 — Bluff, challenged and challenge succeeds](#lp3)
- [ ] [LP4 — Hand intercept, unchallenged](#lp4)
- [ ] [LP5 — Hand intercept claim challenged](#lp5)
- [ ] [LP6 — Herd intercept, unchallenged](#lp6)
- [ ] [LP7 — Herd intercept claim challenged](#lp7)

Case Index (JSON)
```
{
  "cases": [
    { "id": "lp1", "title": "Truthful, unchallenged" },
    { "id": "lp2", "title": "Truthful, challenged and challenge fails" },
    { "id": "lp3", "title": "Bluff, challenged and challenge succeeds" },
    { "id": "lp4", "title": "Hand intercept, unchallenged" },
    { "id": "lp5", "title": "Hand intercept claim challenged" },
    { "id": "lp6", "title": "Herd intercept, unchallenged" },
    { "id": "lp7", "title": "Herd intercept claim challenged" }
  ]
}
```
