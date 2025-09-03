Herding Cats — Catnip Implementation Plan

Purpose
- Capture the Catnip design, notification/privacy rules, and a concrete implementation plan with checkboxes and clear Definitions of Done (DoD).

Scope
- Server logic and notifications (PHP: `src/modules/php/Game.php`).
- Client handling (JS: `src/herdingcats.js`).
- E2E tests (Playwright) aligned to `testplans/catnip.md`.
- Logging/analytics and backward compatibility.

References (design docs)
- Game spec: `game_design.md` (Catnip rules, timing, privacy).
- Test plan: `testplans/catnip.md` (CN1–CN7 cases, assertions).
- State machine: `src/states.inc.php` (select → challenge → intercept → resolve).
- Client UI: `src/herdingcats.js` (existing notification names/handlers).

—

1) Catnip Game Design Summary (authoritative behavior)
- Value: 1 point in herd.
- Target: one opponent’s hand (hidden slot selection).
- Resolution flow (no challenge or failed challenge):
  - Attacker selects a hidden hand slot.
  - Defender’s intercept window (Laser Pointer from hand) happens after selection but before reveal; the intercept claim can be challenged.
  - If no intercept stands: reveal selected card to attacker only; if it is Catnip, publicly reveal and it is ineffective; otherwise attacker steals that card to their herd face-down.
  - Attacker’s played card: added to attacker’s herd face-down unless ineffective-against-itself triggered; if ineffective (selected was Catnip) the played Catnip is discarded face-up.
- Challenges:
  - Challenge against declaration: on truth, challenger penalty first, then resolve Catnip as above; on bluff, played card + one blind penalty discarded, no effect.
- Intercept substitution:
  - If LP from hand stands: originally selected slot stays hidden; attacker steals the LP into herd FD and still places their played Catnip to herd FD (no discard due to intercept).
  - If LP claim is a bluff: presented bluff discarded, then proceed with normal Catnip reveal/resolve.
- Visibility:
  - Identity of a stolen card is visible only to the attacker (owner-only UI); not included in public notifications or logs.
  - Only ineffective case (selected is Catnip) is publicly revealed.

—

2) Notification & Payload Design

Goals
- Preserve privacy for stolen identities in public payloads/logs.
- Provide attacker a private channel to learn the stolen identity for UI labeling.
- Maintain strict ordering guarantees and avoid early herd updates.

Public notifications (no stolen type):
- `cardRemoved`: { player_id, from_zone:'hand'|'herd_down'|'herd_up', card_id }
- `cardStolen`: { from_player_id, to_player_id, card:{ id }, hand_counts }
- `herdUpdate`: { player_id, visible:false|true, card:{ id[, type when visible:true] } }
- `discardUpdate`: { player_id, card:{ id, type } } or { player_id, discard_cards:[...] }
- `catnipIneffective` (new): { target_player_id, card:{ id, type: CARD_TYPE_CATNIP }, selected_slot_index }
- `interceptChallengeResult`: keep existing shape/order; do not leak original slot identity
- `interceptApplied`: compact info that a substitution occurred (no identity leak)

Private notification (attacker-only):
- `cardStolenPrivate` (new): { from_player_id, to_player_id, card:{ id, type } }

Ordering guarantees (must-haves)
- No `herdUpdate` for the played card or stolen card until after intercept challenge resolution.
- On intercept success: emit `interceptChallengeResult` first; then substitution notifications (`cardRemoved` of LP from defender hand, `cardStolen` + `cardStolenPrivate`, then the attacker’s played Catnip `herdUpdate`).
- Never reveal the originally selected slot when an intercept stands.

—

3) Server Implementation Tasks (PHP: `src/modules/php/Game.php`)

3.1 Normal steal (CN1)
- [ ] Emit `cardRemoved` for defender hand (selected slot’s card id).
- [ ] Emit `cardStolen` (public) with card id only, include `hand_counts`.
- [ ] Emit `cardStolenPrivate` (attacker-only) with card id + type.
- [ ] Emit `herdUpdate` for attacker’s played Catnip (visible:false) after steal resolution.
- DoD:
  - On public channels, the stolen `card.type` is absent.
  - Attacker receives stolen `card.type` privately.
  - Counters match: defender hand −1; attacker hand −1; attacker herd +2.
  - No herd changes are sent before intercept/challenge windows resolve.

3.2 Ineffective-against-itself (CN2)
- [ ] Emit `catnipIneffective` (public) revealing defender’s Catnip (includes type).
- [ ] Return the revealed Catnip to defender’s hand (no removal from defender at end state).
- [ ] Emit `discardUpdate` for the attacker’s played Catnip (face-up); skip `herdUpdate` for the played card.
- DoD:
  - Public reveal of defender’s Catnip occurs; attacker’s played Catnip is discarded face-up.
  - Hand/Discard counters reflect −1 attacker hand; defender hand unchanged at end of resolution.

3.3 Intercept with Laser Pointer from hand (CN6/7)
- [ ] On an LP claim that stands: do not reveal selected slot; never send its type anywhere.
- [ ] Remove LP from defender hand (`cardRemoved`).
- [ ] Send `cardStolen` (public id-only) and `cardStolenPrivate` (attacker-only type=Laser Pointer) to move LP to attacker’s herd FD.
- [ ] Send `herdUpdate` for attacker’s played Catnip (visible:false).
- [ ] Maintain `interceptChallengeResult` ordering before any herd updates.
- [ ] On bluff: discard presented bluff card face-up; proceed with CN1/CN2 as applicable.
- DoD:
  - When LP stands: selected slot remains hidden; attacker herd +2; defender hand −1; no public leak of identities for the stolen LP beyond its inevitable type in attacker-only channel.
  - `interceptChallengeResult` appears before herd changes.

3.4 Stats/analytics & logs
- [ ] Increment “cards stolen” and “cards lost to Catnip” without type granularity in public log lines.
- [ ] Attacker-only log lines may mention the stolen type.
- DoD:
  - No public log line exposes stolen type; attacker sees specific type in their local log.

3.5 Backwards compatibility
- [ ] Keep `cardStolen` compatible with older clients (id-only).
- [ ] Make `cardStolenPrivate` additive and optional; missing private notif must not break.
- DoD:
  - Legacy client path (ignoring the private notif) remains functional.

—

4) Client Implementation Tasks (JS: `src/herdingcats.js`)

4.1 Private stolen identity handling
- [ ] Add handler `notif_cardStolenPrivate(args)`.
- [ ] Maintain `this.knownFD` map keyed by `card.id` storing `type` for the current player only.
- [ ] After public `notif_cardStolen`, annotate the attacker’s FD herd card node (title/tooltip) if `this.player_id === to_player_id` and identity exists in `knownFD`.
- DoD:
  - Attacker sees owner-only identity info (e.g., tooltip) on the new FD card.
  - Other tabs never show the stolen type.

4.2 Public stolen handling resilience
- [ ] Keep `notif_cardStolen` tolerant to missing `card.type` (already id-only; no change in behavior).
- DoD:
  - No errors if `card.type` is undefined; FD stock uses card-back.

4.3 Ineffective notification
- [ ] Add `notif_catnipIneffective(args)` to show “Ineffective: defender reveals Catnip (returns to hand)” and handle minor UI (optional animation, counter sync via provided payloads).
- DoD:
  - Non-actor tabs see the public reveal; attacker’s played Catnip is discarded face-up; no `herdUpdate` for it.

4.4 Messaging hygiene
- [ ] Ensure public messages for Catnip never include stolen type; attacker-only messages may.
- DoD:
  - Verify all message strings that mention stolen cards are generic unless on attacker tab.

4.5 Reconnect persistence (optional v2)
- [ ] Add a per-player `known_identities` map to `getAllDatas` and hydrate `this.knownFD` on setup.
- DoD:
  - After refresh, attacker still sees owner-only identities of previously stolen FD cards.

—

5) E2E Tests (Playwright)

CN1 — Truthful, unchallenged, normal steal
- [ ] Non-actor tab never sees `card.type` in public `cardStolen`.
- [ ] Actor tab receives `cardStolenPrivate` and can derive the stolen identity.
- [ ] Counters: defender hand −1; attacker hand −1; attacker herd +2.
- [ ] No herd updates before resolution completion.

CN2 — Truthful, unchallenged, ineffective-against-itself
- [ ] Public reveal of selected Catnip to all.
- [ ] Attacker’s played Catnip is discarded face-up (no herd add for it).

CN6 — Intercept with LP from hand (unchallenged)
- [ ] No reveal of the originally selected slot; selected slot remains hidden.
- [ ] Substitution: LP stolen to attacker’s herd FD; attacker’s played Catnip also to herd FD.
- [ ] Counters: defender hand −1; attacker herd +2.
- [ ] `interceptChallengeResult` arrives before any herd updates.

CN7 — Intercept claim challenged
- [ ] Truthful: as CN6 plus challenger penalty is applied.
- [ ] Bluff: presented card discarded face-up; proceed to CN1/CN2 reveal logic.

General assertions
- [ ] Public logs/notifications never include the stolen type.
- [ ] Only ineffective case reveals Catnip publicly.

—

6) Risks & Pitfalls
- Early herd updates: ensure none are emitted before intercept/challenge resolution completes.
- Identity leaks: never include stolen `card.type` in public `cardStolen`, logs, or any global notify; keep defender-only previews scoped with `notifyPlayer`.
- Counter drift: keep `hand_counts` synchronized in the same notification or immediately following update.

—

7) Rollout & Backward Compatibility
- Phase 1: Ship server changes with `cardStolen` id-only and start emitting `cardStolenPrivate` for attackers; client ignores if unimplemented.
- Phase 2: Ship client to consume `cardStolenPrivate` and show owner-only labels.
- Optional Phase 3: Reconnect identity map in `getAllDatas`.

—

8) Milestones & Tracking (checkboxes with DoD)

Server
- [ ] Catnip normal steal public/private split (DoD in §3.1)
- [ ] Catnip ineffective public reveal + played discard (DoD in §3.2)
- [ ] Intercept substitution without reveal; ordering guaranteed (DoD in §3.3)
- [ ] Logs/analytics privacy (DoD in §3.4)
- [ ] Backward compatibility guards (DoD in §3.5)

Client
- [ ] `notif_cardStolenPrivate` handler + owner-only identity map (DoD in §4.1)
- [ ] `notif_cardStolen` resilient to no type (DoD in §4.2)
- [ ] `notif_catnipIneffective` handler (DoD in §4.3)
- [ ] Public vs attacker-only message hygiene (DoD in §4.4)
- [ ] Reconnect identity persistence (optional) (DoD in §4.5)

Tests
- [ ] CN1 assertions (privacy + counters)
- [ ] CN2 assertions (public reveal + discard)
- [ ] CN6 assertions (no reveal + substitution + order)
- [ ] CN7 assertions (truth vs bluff branches)

—

9) Definition of Done (overall)
- All Server, Client, and Test checkboxes above are complete.
- Manual/Playwright runs of CN1, CN2, CN6, CN7 pass consistently on fresh tables.
- No stolen identity appears in any public notification payload or public log string.
- Attacker sees stolen identity reliably, including after a page refresh if §4.5 is implemented.
- Notification ordering guarantees are verified in logs: no herd updates before `interceptChallengeResult` and final resolution.

—

10) Quick Change List (for implementers)
- PHP: add/emit `cardStolenPrivate` and `catnipIneffective`; adjust `cardStolen` (id-only) and ordering around intercept.
- JS: add `notif_cardStolenPrivate`, `notif_catnipIneffective`, and owner-only identity mapping/tooltip; keep `notif_cardStolen` tolerant.
- Tests: extend Playwright helpers to assert privacy and ordering; update `testplans/catnip.md` checklist as cases pass.

