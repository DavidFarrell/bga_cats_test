Herding Cats — End Game & Scoring Test Plan

Summary
- Verifies game-end trigger and final scoring. Game ends at the end of a turn if any player has 0 cards in hand; then compute herd points plus hand bonus.

Rules Under Test
- End trigger: at the end of any player’s turn, if any player has 0 cards in hand → game ends immediately and scoring runs.
- Herd values: Kitten 2; Show Cat 5 (or 7 with ≥1 Kitten in that player’s herd); Alley Cat 1; Catnip 1; Animal Control 0; Laser Pointer 0.
- Hand bonus: +1 point per 2 cards remaining in hand, rounded up (1→+1, 2→+1, 3→+2, 4→+2, …).
- Ties: allowed (no built-in tie‑breaker).

Preconditions
- 2 players (A active, B defender). 3rd player optional for tie/bonus cases.
- Use straightforward plays to shape hands/herds; prefer unchallenged flows unless the case needs challenges.

Playwright Start/Stop
- Before each case: Express Start a fresh table.
- After each case: the game will often end; otherwise manually Express Stop and close all tabs.

Notation
- “FD/FU” = face‑down/face‑up herd. “LP” = Laser Pointer, “AC” = Animal Control.
- Use Kitten/Show Cat as easy non‑targeting plays to place FD herd cards.

<a id="eg1"></a>
Case EG1 — End triggers when a player reaches 0 cards (end of turn)
Setup
- Make A’s hand low via normal plays and/or penalties.

Procedure
1) A plays their last card (any declaration). Resolve completely.
2) At end of A’s turn, check for game end.

Expected
- Game ends automatically at end of turn with A at 0 in hand. Final scoring panel appears.

Cleanup
- Start a new table for the next case.

<a id="eg2"></a>
Case EG2 — No early termination mid‑resolution
Setup
- A has exactly 1 card in hand.

Procedure
1) A declares a bluff; B challenges.
2) Bluff resolution discards the played card and 1 blind penalty card → A hits 0 mid‑resolution.

Expected
- Turn resolves fully (finish penalty notifications). Only then does the end‑of‑turn check fire and end the game.

<a id="eg3"></a>
Case EG3 — Hand bonus examples
Procedure/Expected
- EG3a: Player with 0 hand → +0 bonus.
- EG3b: Player with 1 hand → +1 bonus.
- EG3c: Player with 2 hand → +1 bonus.
- EG3d: Player with 3 hand → +2 bonus.
- EG3e: Player with 4 hand → +2 bonus.

Notes
- Shape via truth penalties (discard from challenger), unchallenged plays (reduce attacker’s hand by 1), and Catnip steals (hand −1 for victim).

<a id="eg4"></a>
Case EG4 — Show Cat without Kitten synergy (counts 5)
Setup
- Ensure a player ends the game with Show Cat in herd but no Kitten.

Expected
- Show Cat is worth 5 in final tally.

<a id="eg5"></a>
Case EG5 — Show Cat with Kitten synergy (counts 7)
Setup
- Ensure same owner has ≥1 Kitten FD/FU in herd at game end.

Expected
- Show Cat is worth 7 for that owner. Confirm total reflects +2 bonus.

<a id="eg6"></a>
Case EG6 — Zero‑value cards don’t score
Setup
- Put LP and/or AC into a player’s herd.

Expected
- LP and AC contribute 0 points; totals exclude them.

<a id="eg7"></a>
Case EG7 — Tie handling
Setup
- Arrange herds/hand bonuses to produce equal totals for two players.

Expected
- Final display shows a tie (no tie‑breaker); both top scores equal.

<a id="eg8"></a>
Case EG8 — Persistence on refresh at end screen
Procedure
1) After game end, refresh both player tabs (F5).

Expected
- Final scores and herd/hand/discard summaries reload identically via getAllDatas.

UI/Validation Checklist
- End‑of‑turn check runs exactly once; no premature end mid‑resolution.
- Final scoreboard shows per‑player herd totals + hand bonus line items (if exposed) and overall total.
- Herd contents preserved; Show Cat synergy applied per owner’s herd at end only.
- Discard piles remain inspectable.

Case Checklist
- [ ] [EG1 — End triggers at 0 cards](#eg1)
- [ ] [EG2 — No early termination mid‑resolution](#eg2)
- [ ] [EG3 — Hand bonus examples](#eg3)
- [ ] [EG4 — Show Cat without Kitten synergy](#eg4)
- [ ] [EG5 — Show Cat with Kitten synergy](#eg5)
- [ ] [EG6 — Zero‑value cards don’t score](#eg6)
- [ ] [EG7 — Tie handling](#eg7)
- [ ] [EG8 — Persistence on refresh](#eg8)

Case Index (JSON)
```
{
  "cases": [
    { "id": "eg1", "title": "End triggers at 0 cards" },
    { "id": "eg2", "title": "No early termination mid-resolution" },
    { "id": "eg3", "title": "Hand bonus examples" },
    { "id": "eg4", "title": "Show Cat without Kitten synergy" },
    { "id": "eg5", "title": "Show Cat with Kitten synergy" },
    { "id": "eg6", "title": "Zero-value cards don’t score" },
    { "id": "eg7", "title": "Tie handling" },
    { "id": "eg8", "title": "Persistence on refresh" }
  ]
}
```

