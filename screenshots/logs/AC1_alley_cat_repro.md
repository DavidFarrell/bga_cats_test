AC1 — Alley Cat (Truthful, unchallenged) — Repro Note

Context
- Domain: https://studio.boardgamearena.com
- Game: herdingcats (Studio table)
- Scenario: Alley Cat truthful, unchallenged, normal removal (AC1)
- Players: PaidiaGames1 (actor), PaidiaGames0 (defender)

Steps to Reproduce
1) Lobby → Create → Express start.
2) Open second player via “see more” (control both tabs).
3) Actor tab: select an Alley Cat card → click “Alley Cat” in declaration dialog.
4) Target selection: choose “PaidiaGames0 (Hand)”.
5) Challenge window: Defender clicks “Pass”.
6) Intercept window: Defender sees “Intercept with Laser Pointer / Allow Attack”; click “Allow Attack”.

Observed Evidence (selected logs)
- cardPlayed: { player_id: 2422123, declared_type: 3, card_id: 3103, hand_counts: { 2422123:6, 2422122:7 } }
- State flow: awaitDeclaration → selectTarget → challengeWindow → resolveChallenge → interceptDeclare → prepareAttackerPenalty → attackerSelectTruthfulPenalty
- Intercept prompt: “PaidiaGames0 may intercept with Laser Pointer” (state 50)
- After “Allow Attack”, engine enters state 52 → 32 (attackerSelectTruthfulPenalty) with args challengers: []
- UI/log errors (placeholders not substituted):
  - “Invalid or missing substitution argument for log message: ${actor_name} may discard a card from ${challenger_name}'s hand”
  - “You may discard one card from ${challenger_name}'s hand”
- Client prompt shows “This game action is impossible right now” and no penalty UI is rendered.
- No discardUpdate for defender and no herdUpdate for actor (flow stalls).

What Was Expected (AC1)
- No challenge taken; optional intercept prompt appears; on Allow Attack:
  - Actor selects one blind card from defender’s hand (defender −1 hand, discardUpdate logged).
  - Then effect resolves and actor’s played Alley Cat is added to actor’s herd face-down (herdUpdate with visible=false).

Current Findings
- Server routes unchallenged Alley Cat through attackerSelectTruthfulPenalty (state 32), which is designed for “failed challenge” and expects a real challenger (G_CHALLENGER) to populate ${challenger_name} and penalty target.
- In AC1 there is no challenger; G_CHALLENGER remains 0, so argAttackerSelectTruthfulPenalty returns challengers: [] and no challenger_name, causing placeholder substitution errors and missing UI.
- The correct penalty target for AC1 should be the selected defender (G_TARGET_PLAYER), not a challenger.

Proposed Fix (minimal, localized)
1) Server: src/modules/php/Game.php (argAttackerSelectTruthfulPenalty)
   - If no challenger is set AND a pending effect penalty is flagged (G_PENALTY_TO_RESOLVE=1), treat the current target player (G_TARGET_PLAYER) as the penalty target.
   - Populate challenger_id/challenger_name and challengers[] accordingly so the existing state 32 UI/strings work without change.

2) Optional client robustness: src/herdingcats.js
   - In onEnteringState for attackerSelectTruthfulPenalty, fallback to args.target_player_id/hand_count if args.challengers is empty, so the UI still renders in edge cases.

Acceptance Criteria for AC1
- After Defender clicks “Pass” in the challenge window and then “Allow Attack” on the intercept prompt:
  - The actor sees blind selection UI with N face-down slots for the defender’s hand.
  - On pick: logs include discardUpdate with player_id = defender and revealed card type != 3 (not enforced here beyond notification), and handCountUpdate shows defender −1.
  - After effect, herdUpdate adds actor’s played Alley Cat to actor’s herd face-down (visible=false), and the prompt returns to the normal state for the next action/turn.
  - No placeholder substitution errors in headers/descriptions.

Notes
- Challenge window timing in this build is: target → challenge (acceptable if consistent).
- “String not translated” and “unknown item type” warnings during setup are benign and can be deferred.


Implementation Notes (what was changed today)
- Files touched:
  - src/modules/php/Game.php:472 (argAttackerSelectTruthfulPenalty)
- Change summary:
  - When G_CHALLENGER is 0 and a penalty is pending (G_PENALTY_TO_RESOLVE=1), the server now treats the selected defender (G_TARGET_PLAYER) as the penalty target.
  - It fills challenger_id, challenger_name, target_player_id, hand_count, and challengers[] so state 32 (attackerSelectTruthfulPenalty) strings and UI have all data they expect.
- Rationale:
  - In AC1 there’s no challenger; penalty should target the defender’s hand. Previously, the state expected a challenger and produced placeholder errors and no UI.
- Risks/considerations:
  - This change affects only the no-challenge, has-penalty path; challenged paths are unchanged.
  - If future logic sets G_PENALTY_TO_RESOLVE for non-Alley Cat flows, they will also use G_TARGET_PLAYER as the penalty target (intended for Catnip/AC follow-ups too).
- Validation plan (next session):
  1) Redeploy to Studio and run AC1 again.
  2) After “Allow Attack”, confirm: penalty UI appears for defender’s hand; pick any slot.
  3) See discardUpdate for defender and handCountUpdate −1; then herdUpdate for actor (visible=false).
  4) Ensure headers show proper names (no ${challenger_name} placeholders).
- Potential follow-ups (not done):
  - Add a UI fallback in herdingcats.js so if args.challengers is empty but target_player_id exists, it still renders the penalty hand.
  - Clean up “String not translated” warnings and unknown item type warnings during setup.

How to Recreate (quick recipe)
1) Create fresh table → Express start.
2) Actor tab: play Alley Cat; select defender “PaidiaGames0 (Hand)”.
3) Defender tab: Pass challenge; on intercept prompt click “Allow Attack”.
4) Actor tab: a blind selection UI should now appear (slots for defender’s hand). Pick one.
5) Verify logs/counters/herd per Acceptance Criteria above.
