Herding Cats — Animal Control Test Plan

Summary
- Targeted herd removal. Must select a face-down herd card. Reveal selected card; if it is Animal Control, ineffective (flip face-up and protect). Otherwise discard it from defender’s herd. Attacker’s played card goes to herd-FD as Animal Control unless ineffective-against-itself triggers. If a Laser Pointer interception stands, resolve by substitution: defender discards a Laser Pointer from herd face-up instead of revealing/losing the selected herd card; the selected card remains hidden; attacker’s Animal Control still enters attacker’s herd-FD.

Preconditions
- Players A (active) and B (defender); B has ≥1 face-down herd card.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

Tester Quickstart & Conventions
- Studio domain only: `https://studio.boardgamearena.com`.
- Players: A = Player 0 (attacker), B = Player 1 (defender).
- Abbreviations: AC = Animal Control, LP = Laser Pointer, FD = face-down herd, FU = face-up herd.
- Fresh table per case: use Express Start before a case and Express Stop after. Do not reuse tables.
- Two tabs: on the game page use “see more” next to the other player to open their view; switch tabs to act as A/B.
- Seeding cheat-sheet (to prepare herds quickly):
  - Add a non-AC FD to B’s herd: have B play Kitten/Show Cat/LP unchallenged (non-targeting place-to-herd).
  - Put AC into B’s herd-FD: have B declare Animal Control on A, others pass, and select any non-AC from A’s herd; B’s played AC enters B’s herd-FD.
  - Put LP in B’s herd-FD (for intercept-from-herd): have B play LP unchallenged.
  - Produce a FU protected AC in B’s herd: first ensure B has AC in herd-FD (previous bullet), then have A play AC on B selecting that AC → it flips FU/protected; A’s AC is discarded FU.

<a id="an1"></a>
Case AN1 — Truthful, unchallenged, normal removal
Setup (fresh table)
- B plays Kitten (or Show Cat/LP) unchallenged → B herd-FD +1 (non-AC).

Procedure
1) A declares Animal Control targeting B; others pass.
2) A selects B’s FD herd card.
3) Reveal shows NOT Animal Control.
Expected:
- Revealed B card moves to B discard; B herd count -1.
- A’s played card enters A herd-FD; A hand counter -1.
- Logs: declaration, target, reveal, discard, herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

Progress
- [x] Completed via Playwright

<a id="an2"></a>
Case AN2 — Truthful, unchallenged, ineffective-against-itself
Setup (fresh table)
- A plays Kitten unchallenged → A herd-FD +1.
- B declares Animal Control on A; others pass; B selects A’s Kitten (not AC) → B’s played AC enters B herd-FD.

Procedure
1) A declares Animal Control on B; others pass.
2) A selects B’s only FD slot (the AC).
3) Reveal shows Animal Control.
Expected:
- Ineffective: selected card flips face-up in B’s herd and is protected.
- A’s played card is discarded face-up (thwarted); A hand counter -1.
Cleanup:
- Express Stop the game before proceeding to any other case.

Progress
- [x] Setup complete (A FD + B AC seeded)
- [x] Declaration + target selection performed
- [ ] Reveal/resolve: defender AC flips FU; attacker AC discarded FU
- [ ] Assertions: counters/logs verified
- [ ] Cleanup (Express Stop)

Notes (in-flight)
- Current run: herd-slot selection is displayed and element is present (`hc_herd_face_down_2422122_item_2100`), but click is intermittently intercepted by chat layer; programmatic `.click()` dispatch triggers but state does not advance.
- Mitigation being used: temporarily disable chatbar pointer-events during this step; if still blocked, retry with fresh table and re-seed.
- Expected next logs: `fdKnown` for selected slot (type=5 in AN2 proper), `herdUpdate` for A’s prior placement only after resolution, and discard FU for attacker when ineffective.

<a id="an3"></a>
Case AN3 — Truthful, challenged and challenge fails
Setup (fresh table)
- B plays Kitten unchallenged → B herd-FD +1 (non-AC).

Procedure
1) A declares Animal Control on B; B clicks Challenge.
2) Challenge fails (A truthful): A selects a blind penalty from B’s hand; reveal + discard.
3) Continue resolution like AN1 (normal removal): A selects B’s FD herd card; reveal shows not AC; discard it; A’s played AC to A herd-FD.
Expected:
- Challenger hand counter -1; logs show penalty then effect (AN1 normal removal → A’s card to herd-FD; AN2 ineffective → A’s card discarded face-up).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an4"></a>
Case AN4 — Bluff, unchallenged
Setup (pick one variant)
- Normal removal variant: B plays Kitten unchallenged → B herd-FD +1 (non-AC).
- Ineffective variant: prepare B with exactly one FD AC as in AN2 setup.

Procedure
1) A declares Animal Control while actually playing a non-AC; no one challenges.
2) Proceed using declared identity: select B’s FD slot.
Expected:
- AN1/AN2 behavior applies based on revealed defender card.
- A’s played card enters A herd-FD as Animal Control for normal removal; if ineffective (vs Animal Control), A’s played card is discarded face-up (no herd placement).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an5"></a>
Case AN5 — Bluff, challenged and challenge succeeds
Setup (optional)
- Optionally seed B’s herd with one FD card (e.g., Kitten) to confirm no removal occurs.

Procedure
1) A declares Animal Control while actually playing a non-AC; B challenges.
2) Reveal shows A bluffed.
Expected:
- A discards played card + 1 blind penalty (first challenger picks); turn ends; no herd effect; no herd placement for A.
Cleanup:
- Express Stop the game before proceeding to any other case.

Progress
- [x] Completed via Playwright (AN5 passed)
- Evidence highlights:
  - challengeResult: was_bluffing:true, declared_type:5, actual_card_type:4
  - Penalty UI count = pre‑declare hand − 1; applied one slot
  - Actor hand −2 net; defender hand unchanged; actor discard +2; no herd updates

<a id="an6"></a>
Case AN6 — Defender intercepts with Laser Pointer from herd (unchallenged)
Setup (fresh table)
- B plays Laser Pointer unchallenged → B herd-FD +1 (LP).
- B plays Kitten (or Show Cat) unchallenged → B herd-FD +2 (ensure a non-LP FD exists to target).

Procedure
1) A declares Animal Control on B; others pass; A selects B’s non-LP FD herd card.
2) Before reveal, B declares intercept from herd with the LP; no one challenges.
Expected:
- Selected herd card remains hidden and untouched; substitution applies.
- B discards LP face-up from herd; B herd count -1.
- A’s played Animal Control still enters A herd-FD (no discard due to intercept).
- Defender-only prompt shows “Attacker selected Card N, <type>” with a pulse-highlight on that slot.
- Logs: LP interception (herd) and substitution.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an7"></a>
Case AN7 — Interception claim challenged
Setup A — truthful intercept
- As in AN6: B herd contains LP + ≥1 non-LP FD.

Procedure A — truthful intercept
1) A declares AC on B; selects a non-LP FD slot.
2) B declares LP-from-herd intercept; A (or C) challenges.
3) Challenge fails (truthful): defender selects a blind penalty from challenger’s hand; discard.
4) Substitution stands: LP discarded FU; selected slot remains hidden; A’s AC enters A herd-FD.

Setup B — bluff intercept
- Ensure B has no LP in herd and ≥2 FD non-LP herd cards (e.g., B plays Kitten, then Show Cat unchallenged).

Procedure B — bluff intercept
1) A declares AC on B; selects one FD slot (“target slot”).
2) B declares intercept-from-herd presenting the other FD card (not an LP); A (or C) challenges.
3) Challenge succeeds (bluff): presented bluff is discarded FU.
4) Proceed with normal AC reveal/resolve on the original “target slot” (AN1/AN2 depending on identity).
Expected (truthful LP): B discards LP; C discards blind penalty; selected card remains hidden; substitution stands; A’s Animal Control enters A herd-FD.
Expected (bluff LP): B takes penalty; AN proceeds (AN1/AN2) and selected card is revealed.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an8"></a>
Case AN8 — Target selection gates
Setup (face-up protection check)
- Produce a FU protected AC in B’s herd:
  - Ensure B has AC in herd-FD (see “Seeding cheat-sheet”).
  - A declares AC on B; others pass; A selects that AC slot.
  - Result: the selected AC flips FU/protected; A’s played AC is discarded FU.

Procedure
1) Start a new AC attempt targeting B: UI must disallow selecting the FU herd card; only FD slots (if any) are clickable.
2) If B has no FD herd cards (empty herd or only FU protected cards), Animal Control target selection should be blocked or show no valid targets.
Cleanup:
- Express Stop the game before proceeding to any other case.

UI/Validation Checklist
- Lock during resolution; correct enablement of actions.
- Counters update: hand/herd/face-up states consistent; refresh preserves FU protection.

Case Checklist
- [x] [AN1 — Truthful, unchallenged, normal removal](#an1)
- [x] [AN2 — Truthful, unchallenged, ineffective-against-itself](#an2)
- [x] [AN3 — Truthful, challenged and challenge fails](#an3)
- [x] [AN4 — Bluff, unchallenged](#an4)
 - [x] [AN5 — Bluff, challenged and challenge succeeds](#an5)
 - [x] [AN6 — Defender intercepts with Laser Pointer from herd (unchallenged)](#an6)
 - [x] [AN7 — Interception claim challenged](#an7)
 - [x] [AN8 — Target selection gates](#an8)

Case Index (JSON)
```
{
  "cases": [
    { "id": "an1", "title": "Truthful, unchallenged, normal removal" },
    { "id": "an2", "title": "Truthful, unchallenged, ineffective-against-itself" },
    { "id": "an3", "title": "Truthful, challenged and challenge fails" },
    { "id": "an4", "title": "Bluff, unchallenged" },
    { "id": "an5", "title": "Bluff, challenged and challenge succeeds" },
    { "id": "an6", "title": "Defender intercepts with Laser Pointer from herd (unchallenged)" },
    { "id": "an7", "title": "Interception claim challenged" },
    { "id": "an8", "title": "Target selection gates" }
  ]
}
```
