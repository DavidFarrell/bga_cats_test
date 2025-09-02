Herding Cats — Catnip Test Plan

Summary
- Targeted hand steal. Reveal selected hand card; if it is Catnip, ineffective (defender keeps). Otherwise move revealed card into attacker’s herd-FD; only attacker sees its identity. Attacker’s played card goes to herd-FD as Catnip unless ineffective-against-itself triggers. If a Laser Pointer interception stands, resolve by substitution: attacker steals the defender’s Laser Pointer into attacker’s herd-FD instead of revealing/stealing the selected card; the selected card remains hidden; attacker’s Catnip also goes to herd-FD.

Preconditions
- Players A (active) and B (defender) seated; B has ≥1 card in hand.

Playwright Start/Stop
- Before running these cases: Express Start the game.
- After each case: ALWAYS Express Stop the game (table page → “Express stop”/“Quit game”).

<a id="cn1"></a>
Case CN1 — Truthful, unchallenged, normal steal
1) A declares Catnip targeting B; others pass.
2) A selects a slot from B’s hand in staging area; reveal shows NOT Catnip.
Expected:
- Revealed B card transfers to A herd-FD; only A can see its identity in UI.
- A’s played card enters A herd-FD as Catnip.
- B hand counter -1; A hand counter -1; A herd count +2 (two cards added).
- Logs: declaration, target, reveal, transfer, herd placement.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="cn2"></a>
Case CN2 — Truthful, unchallenged, ineffective-against-itself
1) As CN1, but reveal shows B’s card is Catnip.
Expected:
- Ineffective: B keeps the card (return to B’s hand).
- A’s played card is discarded face-up (thwarted); A hand counter -1.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="cn3"></a>
Case CN3 — Truthful, challenged and challenge fails
1) B challenges; truth stands.
2) A picks a blind penalty from B’s hand; reveal + discard.
3) Proceed with CN1/CN2 resolution.
Expected:
- Challenger hand counter -1; logs reflect penalty then effect (CN1 normal steal → A’s card to herd-FD; CN2 ineffective → A’s card discarded face-up).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="cn4"></a>
Case CN4 — Bluff, unchallenged
1) A declares Catnip with a non-Catnip card; no challenge.
2) Proceed using declared identity.
Expected:
- If selected B card is Catnip: ineffective (return to hand).
- Otherwise the revealed card moves to A herd-FD.
- A’s played card enters A herd-FD as Catnip for normal steal; if ineffective occurs (vs Catnip), A’s played card is discarded face-up (no herd placement).
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="cn5"></a>
Case CN5 — Bluff, challenged and challenge succeeds
1) Reveal shows A bluffed.
Expected:
- A discards played card + 1 blind penalty (selected by first challenger); turn ends; no steal; no herd placement for A.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="cn6"></a>
Case CN6 — Defender intercepts with Laser Pointer from hand
1) After slot selection (before reveal), B declares Laser Pointer from hand.
Expected:
- Selected slot remains hidden; substitution applies.
- B reveals LP (to engine) and the LP is stolen to A’s herd-FD; B hand counter -1; A herd +2 total (stolen LP + played Catnip).
- A’s played Catnip also enters A herd-FD (no discard due to intercept).
- Defender-only prompt shows “Attacker selected Card N, <type>” with a pulse-highlight on that slot.
- Logs: LP interception (hand) and substitution/steal.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="cn7"></a>
Case CN7 — Interception claim challenged
1) C challenges B’s LP claim.
Expected (truthful LP): substitution stands; selected slot stays hidden; A steals LP to herd-FD and Catnip also goes to herd-FD; C discards a blind penalty.
Expected (bluff LP): B takes penalty; CN proceeds (CN1/CN2) with reveal.
Cleanup:
- Express Stop the game before proceeding to any other case.

Visibility Checks
- The identity of the stolen card is visible only to A; others see a generic FD card in A’s herd.
- Refresh maintains owner-only identity visibility for stolen card.

Case Checklist
- [ ] [CN1 — Truthful, unchallenged, normal steal](#cn1)
- [ ] [CN2 — Truthful, unchallenged, ineffective-against-itself](#cn2)
- [ ] [CN3 — Truthful, challenged and challenge fails](#cn3)
- [ ] [CN4 — Bluff, unchallenged](#cn4)
- [ ] [CN5 — Bluff, challenged and challenge succeeds](#cn5)
- [ ] [CN6 — Defender intercepts with Laser Pointer from hand](#cn6)
- [ ] [CN7 — Interception claim challenged](#cn7)

Case Index (JSON)
```
{
  "cases": [
    { "id": "cn1", "title": "Truthful, unchallenged, normal steal" },
    { "id": "cn2", "title": "Truthful, unchallenged, ineffective-against-itself" },
    { "id": "cn3", "title": "Truthful, challenged and challenge fails" },
    { "id": "cn4", "title": "Bluff, unchallenged" },
    { "id": "cn5", "title": "Bluff, challenged and challenge succeeds" },
    { "id": "cn6", "title": "Defender intercepts with Laser Pointer from hand" },
    { "id": "cn7", "title": "Interception claim challenged" }
  ]
}
```
