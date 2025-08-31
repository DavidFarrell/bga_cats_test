Herding Cats — Alley Cat Test Plan

Summary
- Targeted hand attack. Reveal selected hand card; if it is Alley Cat, ineffective (return to hand). Otherwise defender discards that revealed card. Attacker’s played card enters herd-FD as Alley Cat.

Preconditions
- Players A (active) and B (defender) seated; B has ≥1 card in hand.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

<a id="ac1"></a>
Case AC1 — Truthful, unchallenged, normal removal
1) A declares Alley Cat targeting B; others pass.
2) A selects a slot from B’s hand in the staging area.
3) Reveal selected card; it is NOT Alley Cat.
Expected:
- Revealed B card moves to B discard; B hand counter -1.
- A’s played card enters A herd-FD as Alley Cat; A hand counter -1.
- Logs: declaration, target, reveal, discard, herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac2"></a>
Case AC2 — Truthful, unchallenged, ineffective-against-itself
1) As AC1, but reveal shows B’s card is Alley Cat.
Expected:
- Ineffective: B card is revealed then returned to B’s hand; no loss for B.
- A’s played card still enters A herd-FD; A hand counter -1.
- Logs: ineffective vs same identity.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac3"></a>
Case AC3 — Truthful, challenged and challenge fails
1) B challenges A’s Alley Cat claim; truth stands.
2) A selects a blind slot from B’s hand for penalty; reveal + discard.
3) Proceed with AC1/AC2 effect resolution.
Expected:
- B discards one blind penalty in addition to AC effect; B hand counter -2 (or -1 if AC2 ineffective).
- A’s card enters herd-FD; logs reflect penalty + effect sequence.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac4"></a>
Case AC4 — Bluff, unchallenged
1) A plays a non–Alley Cat but declares Alley Cat; others pass.
2) Proceed with effect using declared identity.
Expected:
- If selected B card is Alley Cat: ineffective (return to hand).
- Otherwise B discards the revealed card.
- A’s played card enters A herd-FD as Alley Cat; no reveals of the played card.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac5"></a>
Case AC5 — Bluff, challenged and challenge succeeds
1) B challenges; reveal shows A bluffed.
Expected:
- A discards played card; B (first challenger) picks a blind slot from A’s hand; reveal + discard.
- Turn ends; no effect on B’s hand from Alley Cat, no herd placement for A.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac6"></a>
Case AC6 — Defender intercepts with Laser Pointer from hand (unchallenged)
1) During AC1 step 2 (after slot selection, before reveal), B declares Laser Pointer from hand.
Expected:
- No reveal of the selected slot; attack canceled.
- B reveals LP and discards it face-up; B hand counter -1.
- A’s played card still enters A herd-FD as Alley Cat.
- Logs: LP interception (hand) and cancel.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac7"></a>
Case AC7 — Interception claim challenged
1) Same as AC6, but C challenges B’s LP claim.
Expected (truthful LP):
- B reveals LP, discards it; C discards a blind penalty; no reveal of selected slot; attack canceled; A’s card to herd-FD.
Expected (bluff LP):
- B fails challenge: B discards blind penalty; AC proceeds (go to AC1/AC2) and selected slot is revealed.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac8"></a>
Case AC8 — Target selection gates
1) UI should force selecting exactly one opponent on declaration; self-target disallowed.
2) Staging area shows card backs in fixed order; cannot select more than one slot.
Cleanup:
- Express Stop the game before proceeding to any other case.

UI/Validation Checklist
- Correct lock during resolution; only legal actions enabled.
- Counters update immediately for both players.
- Refresh preserves herd-FD placement and discard contents.

Case Checklist
- [ ] [AC1 — Truthful, unchallenged, normal removal](#ac1)
- [ ] [AC2 — Truthful, unchallenged, ineffective-against-itself](#ac2)
- [ ] [AC3 — Truthful, challenged and challenge fails](#ac3)
- [ ] [AC4 — Bluff, unchallenged](#ac4)
- [ ] [AC5 — Bluff, challenged and challenge succeeds](#ac5)
- [ ] [AC6 — Defender intercepts with Laser Pointer from hand (unchallenged)](#ac6)
- [ ] [AC7 — Interception claim challenged](#ac7)
- [ ] [AC8 — Target selection gates](#ac8)

Case Index (JSON)
```
{
  "cases": [
    { "id": "ac1", "title": "Truthful, unchallenged, normal removal" },
    { "id": "ac2", "title": "Truthful, unchallenged, ineffective-against-itself" },
    { "id": "ac3", "title": "Truthful, challenged and challenge fails" },
    { "id": "ac4", "title": "Bluff, unchallenged" },
    { "id": "ac5", "title": "Bluff, challenged and challenge succeeds" },
    { "id": "ac6", "title": "Defender intercepts with Laser Pointer from hand (unchallenged)" },
    { "id": "ac7", "title": "Interception claim challenged" },
    { "id": "ac8", "title": "Target selection gates" }
  ]
}
```
