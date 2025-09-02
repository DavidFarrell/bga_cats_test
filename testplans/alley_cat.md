Herding Cats — Alley Cat Test Plan

Summary
- Targeted hand discard. Reveal selected hand card; if it is Alley Cat, ineffective (return to hand). Otherwise defender discards that revealed card. Attacker’s played card goes to herd-FD as Alley Cat unless ineffective-against-itself triggers. If a Laser Pointer interception stands, resolve by substitution: defender discards Laser Pointer from hand face-up instead of revealing/losing the selected card; the selected card remains hidden; attacker’s Alley Cat still enters attacker’s herd-FD.

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
- A’s played card is discarded face-up (thwarted); A hand counter -1.
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
- A’s card enters herd-FD for normal removal; if AC2 ineffective occurs, A’s played card is discarded face-up. Logs reflect penalty then effect resolution.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac4"></a>
Case AC4 — Bluff, unchallenged
Purpose
- Verify Alley Cat bluff, unchallenged. Actor plays a non–Alley Cat card, declares “Alley Cat”. If defender’s revealed slot is Alley Cat → ineffective; else defender discards. Attacker’s played card goes to herd‑FD unless ineffective; in that case attacker’s card is discarded face‑up. No `alleyCatIneffective` unless ineffective triggers.

Prereqs
- Auto‑deploy running (`./deploy.sh --watch`); Studio domain only.

Flow
1) Navigate Lobby → Create table → Express start.
2) Open second player via “see more” (switchable tabs).
3) Actor tab:
   - Enumerate hand; pick a non–Alley Cat (type/img !== `alleycat`, e.g., `showcat` or `kitten`).
   - Click that card → click “Alley Cat” in the declaration overlay.
   - Click target “<defender_name> (Hand)”.
4) Defender tab:
   - Click “Pass” on challenge window.
   - Click “Allow Attack” on intercept window.
5) Actor tab:
   - In the blind selection UI for Alley Cat effect, click a slot in defender’s hand.

Selectors / Helpers
- Enumerate hand items via `browser_evaluate`:
  ```js
  (() => Array.from(document.querySelectorAll('[id^="hc_current_hand_item_"], .stockitem')).map(el => {
    const bg = getComputedStyle(el).backgroundImage || '';
    const m = bg.match(/\/([^\/]+)\.(png|jpe?g|webp)/i);
    return { id: el.id, img: m ? m[1] : null };
  }))()
  ```
- After each DOM change, call `browser_snapshot()` before reusing element refs.
- Use `browser_tabs({ action: 'select', index })` to switch players.

Assertions (in order; branch on outcome)
- cardPlayed: `declared_type = 3` (Alley Cat); actor hand −1; no `herdUpdate` yet.
- targetSelected: `target_player_id = defender`; `target_zone = 'hand'`.
- `alleyCatEffect` present; no `alleyCatIneffective` unless ineffective occurs.
- After actor selects defender slot:
  - If ineffective (revealed card is Alley Cat):
    - `alleyCatIneffective` notification appears.
    - No `discardUpdate` for defender; defender hand unchanged (counter 0 delta).
    - Attacker’s played card is discarded face‑up (`discardUpdate` for actor with original non‑AC type); no `herdUpdate` for actor.
    - Final counters: actor −1; defender 0.
  - Else (normal removal):
    - `cardRemoved` for defender’s hand with the selected `card_id`.
    - `discardUpdate` for defender with revealed card type; defender hand −1.
    - `herdUpdate` for actor with a new face‑down herd card; no `discardUpdate` for actor.
    - Final counters: actor −1; defender −1.

Cleanup
- Return to table page and click “Express stop” / “Quit game”.
- Close all game tabs.

<a id="ac5"></a>
Case AC5 — Bluff, challenged and challenge succeeds
Purpose
- Verify Alley Cat bluff when challenged and verified as a bluff: attacker’s played card is discarded; first challenger discards a blind penalty from attacker’s hand; Alley Cat effect does not resolve; no herd placement.

Prereqs
- Auto‑deploy running (`./deploy.sh --watch`); Studio domain only.

Flow
1) Navigate Lobby → Create table → Express start.
2) Open second player via “see more” (switchable tabs).
3) Actor tab:
   - Enumerate hand; pick a non–Alley Cat (img !== `alleycat`).
   - Click that card → click “Alley Cat” in the declaration overlay.
   - Click target “<defender_name> (Hand)”.
4) Defender tab:
   - Click “Challenge”.
5) Resolution:
   - Game verifies bluff; played card is revealed/discarded for the actor.
   - First challenger (defender) is prompted to pick a blind slot from the actor’s hand; select one.
6) Confirm turn ends; attacker does not add to herd and no Alley Cat effect resolves on defender.

Selectors / Helpers
- Enumerate hand items via `browser_evaluate`:
  ```js
  (() => Array.from(document.querySelectorAll('[id^="hc_current_hand_item_"], .stockitem')).map(el => {
    const bg = getComputedStyle(el).backgroundImage || '';
    const m = bg.match(/\/([^\/]+)\.(png|jpe?g|webp)/i);
    return { id: el.id, img: m ? m[1] : null };
  }))()
  ```
- After each DOM change, call `browser_snapshot()` before reusing refs.
- Use `browser_tabs({ action: 'select', index })` to switch players.

Assertions (in order)
- cardPlayed: `declared_type = 3` (Alley Cat); actor hand −1; no `herdUpdate` yet.
- targetSelected: `target_player_id = defender`; `target_zone = 'hand'`.
- challengeResult: `was_bluffing = true`; includes `declared_type:3` and `actual_card_type` for the played non‑AC.
- discardUpdate (actor, played card): actor’s played card discarded face‑up; actor hand unchanged beyond the initial −1.
- penalty selection UI for challenger appears over actor’s hand backs; challenger selects a slot:
  - cardRemoved: from actor’s hand with selected `card_id`.
  - discardUpdate: for actor with revealed penalty card type.
  - handCountUpdate: actor −2 total relative to initial hand; defender 0 delta overall.
- No `alleyCatEffect` nor `alleyCatIneffective` notifications should occur; no `herdUpdate` for actor at any time.
- State returns to awaitDeclaration for the next player.

Cleanup
- Return to the table page and click “Express stop” / “Quit game”.
- Close all game tabs.

<a id="ac6"></a>
Case AC6 — Defender intercepts with Laser Pointer from hand (unchallenged)
1) During AC1 step 2 (after slot selection, before reveal), B declares Laser Pointer from hand.
Expected:
- No reveal of the selected slot; substitution applies.
- B reveals LP (to engine) and discards it face-up; B hand counter -1.
- A’s played Alley Cat still enters A herd-FD (no discard due to intercept).
- Defender-only prompt shows “Attacker selected Card N, <type>” with a pulse-highlight on that slot.
- Logs: LP interception (hand) and substitution.
Cleanup:
- Express Stop the game before proceeding to any other case.

<a id="ac7"></a>
Case AC7 — Interception claim challenged
1) Same as AC6, but C challenges B’s LP claim.
Expected (truthful LP):
- B reveals LP, discards it; C discards a blind penalty; no reveal of selected slot; substitution stands; A’s Alley Cat enters A herd-FD.
Expected (bluff LP):
- B fails challenge: B discards the presented bluff card face-up (the exact card they clicked as LP); then AC proceeds (go to AC1/AC2) and the selected slot is revealed and resolved normally. No extra blind/random penalty is applied. Net effect for defender in this scenario is −2 cards: the presented bluff card plus the card removed by Alley Cat.
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
- [x] [AC1 — Truthful, unchallenged, normal removal](#ac1)
- [x] [AC2 — Truthful, unchallenged, ineffective-against-itself](#ac2)
- [x] [AC3 — Truthful, challenged and challenge fails](#ac3)
- [x] [AC4 — Bluff, unchallenged](#ac4)
- [x] [AC5 — Bluff, challenged and challenge succeeds](#ac5)
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
