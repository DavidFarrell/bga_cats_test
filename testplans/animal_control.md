Herding Cats — Animal Control Test Plan

Summary
- Targeted herd removal. Must select a face-down herd card. Reveal selected card; if it is Animal Control, ineffective (flip face-up and protect). Otherwise discard it from defender’s herd. Attacker’s played card goes to herd-FD as Animal Control unless ineffective-against-itself triggers. If a Laser Pointer interception stands, resolve by substitution: defender discards a Laser Pointer from herd face-up instead of revealing/losing the selected herd card; the selected card remains hidden; attacker’s Animal Control still enters attacker’s herd-FD.

Preconditions
- Players A (active) and B (defender); B has ≥1 face-down herd card.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

<a id="an1"></a>
Case AN1 — Truthful, unchallenged, normal removal
1) A declares Animal Control targeting B; others pass.
2) UI allows selecting only face-down herd cards; A selects one.
3) Reveal shows NOT Animal Control.
Expected:
- Revealed B card moves to B discard; B herd count -1.
- A’s played card enters A herd-FD; A hand counter -1.
- Logs: declaration, target, reveal, discard, herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an2"></a>
Case AN2 — Truthful, unchallenged, ineffective-against-itself
1) As AN1, but reveal shows Animal Control.
Expected:
- Ineffective: selected card flips face-up in B’s herd and is protected.
- A’s played card is discarded face-up (thwarted); A hand counter -1.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an3"></a>
Case AN3 — Truthful, challenged and challenge fails
1) B challenges A’s claim; truth stands.
2) A selects a blind slot from B’s hand; reveal + discard.
3) Proceed with AN1/AN2.
Expected:
- Challenger hand counter -1; logs show penalty then effect (AN1 normal removal → A’s card to herd-FD; AN2 ineffective → A’s card discarded face-up).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an4"></a>
Case AN4 — Bluff, unchallenged
1) A declares Animal Control with a non–Animal Control; no challenge.
2) Proceed using declared identity.
Expected:
- AN1/AN2 behavior applies based on revealed defender card.
- A’s played card enters A herd-FD as Animal Control for normal removal; if ineffective (vs Animal Control), A’s played card is discarded face-up (no herd placement).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an5"></a>
Case AN5 — Bluff, challenged and challenge succeeds
1) Reveal shows A bluffed.
Expected:
- A discards played card + 1 blind penalty (first challenger picks); turn ends; no herd effect; no herd placement for A.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an6"></a>
Case AN6 — Defender intercepts with Laser Pointer from herd (unchallenged)
1) After A selects the FD herd card (before reveal), B declares Laser Pointer from herd.
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
1) C challenges B’s LP-from-herd claim.
Expected (truthful LP): B discards LP; C discards blind penalty; selected card remains hidden; substitution stands; A’s Animal Control enters A herd-FD.
Expected (bluff LP): B takes penalty; AN proceeds (AN1/AN2) and selected card is revealed.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="an8"></a>
Case AN8 — Target selection gates
1) UI must disallow selecting face-up herd cards.
2) If B has no FD herd cards, Animal Control target selection should be blocked or show no valid targets.
Cleanup:
- Express Stop the game before proceeding to any other case.

UI/Validation Checklist
- Lock during resolution; correct enablement of actions.
- Counters update: hand/herd/face-up states consistent; refresh preserves FU protection.

Case Checklist
- [ ] [AN1 — Truthful, unchallenged, normal removal](#an1)
- [ ] [AN2 — Truthful, unchallenged, ineffective-against-itself](#an2)
- [ ] [AN3 — Truthful, challenged and challenge fails](#an3)
- [ ] [AN4 — Bluff, unchallenged](#an4)
- [ ] [AN5 — Bluff, challenged and challenge succeeds](#an5)
- [ ] [AN6 — Defender intercepts with Laser Pointer from herd (unchallenged)](#an6)
- [ ] [AN7 — Interception claim challenged](#an7)
- [ ] [AN8 — Target selection gates](#an8)

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
