Herding Cats — Show Cat Test Plan

Summary
- Non-targeting play; value 5 in herd, or 7 if the owner has at least one Kitten in herd at scoring. Physical card becomes Show Cat when added to herd.

Preconditions
- Players A and B; A holds Show Cat; later scoring tests require controlled herds.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

<a id="sc1"></a>
Case SC1 — Truthful, unchallenged
1) A declares Show Cat; others pass.
Expected: A’s card enters A herd-FD as Show Cat; A hand counter -1; logs reflect placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="sc2"></a>
Case SC2 — Truthful, challenged and challenge fails
1) B challenges; truth stands; A picks blind penalty from B; reveal + discard.
2) A’s card enters herd-FD.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="sc3"></a>
Case SC3 — Bluff, challenged and challenge succeeds
1) Reveal shows A bluffed.
Expected: A discards played card + 1 blind penalty; turn ends; no herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="sc4"></a>
Case SC4 — Scoring without Kitten synergy
1) Reach end-of-game with A’s herd containing Show Cat and no Kittens.
Expected: Show Cat counts 5 points for A.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="sc5"></a>
Case SC5 — Scoring with Kitten synergy
1) Reach end-of-game with A’s herd containing Show Cat and ≥1 Kitten.
Expected: Show Cat counts 7 points for A (verify sum reflects +2 bonus).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="sc6"></a>
Case SC6 — Stolen from hand by Catnip
1) B declares Catnip on A; reveal shows Show Cat; B steals it to B herd-FD.
Expected: B’s herd now contains a FD Show Cat visible only to B in UI; A hand counter -1; B herd count +1 (plus Catnip card placement).
2) At scoring, that Show Cat belongs to B and gets +2 if B also has a Kitten.
Cleanup:
- Express Stop the game before proceeding to any other case.

UI/Validation Checklist
- Counters and logs update correctly; page refresh preserves herd-FD and owner-only identity of stolen Show Cat.

Case Checklist
- [ ] [SC1 — Truthful, unchallenged](#sc1)
- [ ] [SC2 — Truthful, challenged and challenge fails](#sc2)
- [ ] [SC3 — Bluff, challenged and challenge succeeds](#sc3)
- [ ] [SC4 — Scoring without Kitten synergy](#sc4)
- [ ] [SC5 — Scoring with Kitten synergy](#sc5)
- [ ] [SC6 — Stolen from hand by Catnip](#sc6)

Case Index (JSON)
```
{
  "cases": [
    { "id": "sc1", "title": "Truthful, unchallenged" },
    { "id": "sc2", "title": "Truthful, challenged and challenge fails" },
    { "id": "sc3", "title": "Bluff, challenged and challenge succeeds" },
    { "id": "sc4", "title": "Scoring without Kitten synergy" },
    { "id": "sc5", "title": "Scoring with Kitten synergy" },
    { "id": "sc6", "title": "Stolen from hand by Catnip" }
  ]
}
```
