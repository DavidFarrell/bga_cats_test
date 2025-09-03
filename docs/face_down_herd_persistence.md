Face‑Down Herd Identity Persistence (Spec)

Purpose
- Preserve owner‑only knowledge of stolen face‑down herd cards across refresh/reconnect without leaking to other players.
- Keep behavior consistent in both real‑deck mode and current dummy/no‑deck fallback.

Problem Statement
- Today, owner‑only identity is tracked only client‑side (`knownFD`) and is lost on refresh.
- There is no authoritative server record of which face‑down herd card identities are known by whom.
- Cleanup is ad‑hoc: when a card is discarded/turned face‑up, the client should remove labels, but after refresh we can’t re‑compute past knowledge.

Goals
- On reconnect, the owning player immediately sees labels/tooltips for previously stolen face‑down cards they know.
- No other player receives or can infer these identities via public payloads or `getAllDatas`.
- Knowledge is revoked automatically if the card is revealed, discarded, or otherwise leaves the owner’s face‑down herd.
- Minimal changes; backwards compatible with existing notifications.

Non‑Goals
- Persisting knowledge across separate tables/games.
- Adding new gameplay sources of private knowledge beyond Catnip steals.

Sources of Owner‑Only Knowledge
- Catnip normal steal (hand → attacker herd FD): attacker learns stolen `type` via `cardStolenPrivate`.
- Catnip intercept substitution (LP from hand): attacker learns `type=Laser Pointer` via `cardStolenPrivate`.

Revocation Events (lose label)
- The specific card id:
  - Is revealed face‑up in herd (`herdUpdate.visible=true`).
  - Is removed from herd (`cardRemoved` with `from_zone='herd_down'|'herd_up'`).
  - Is discarded with id (`discardUpdate.card.id` matches).
  - Is transferred elsewhere (future features): any `cardStolen` where `from_player_id` equals owner and `card.id` matches.

Data Model
- Server‑side authoritative map: `known_fd_identities` (per table, private mapping of knowledge).
  - Shape: `{ [playerId: number]: { [cardId: number]: number /* type */ } }`.
  - Storage: `globals` JSON works for runtime persistence; privacy is enforced by only returning the current player’s slice in `getAllDatas`.
  - Optional v2: DB table `known_fd(player_id INT, card_id INT, card_type INT)` if real deck + long games need stronger guarantees.

Server API Changes
- When creating owner‑only knowledge:
  - On Catnip normal/intercept steal: update `known_fd_identities[attacker][card.id] = card.type`.
- When revoking knowledge:
  - On the notifications listed in Revocation Events: remove the corresponding `card.id` from all players’ maps (or at least from the owner if determinable).
- Reconnect:
  - `getAllDatas`: add `known_identities` for the current player only:
    - `{ known_identities: { [cardId]: cardType } }`
  - Do not include these for other players.

Client Changes
- Setup:
  - Merge `gamedatas.known_identities` into `this.knownFD` during `setup()`.
- Cleanup:
  - On `herdUpdate.visible=true`, `cardRemoved` (herd zones), `discardUpdate` (matching id), remove `knownFD[cardId]`.
  - Keep existing owner‑only badge/tooltip rendering based on `knownFD`.

Notifications & Ordering (No breaking changes)
- Continue emitting `cardStolen` (public id‑only) and `cardStolenPrivate` (to attacker with type).
- Do not add type to any public message.
- Knowledge creation occurs after steal is finalized (post‑intercept/challenge), before the played card’s `herdUpdate` where applicable.

Privacy & Security
- `known_fd_identities` is never sent wholesale; only per‑viewer slice via `getAllDatas`.
- Avoid logging the map or types to public logs.

Backward Compatibility
- Old clients ignoring `known_identities` behave unchanged.
- No change to public payload shapes; only additional optional field in `getAllDatas` for the current player.

Edge Cases
- Dummy/no‑deck mode: card ids are stable within a game; `globals` suffices. If ids are regenerated (e.g., debug tools), mappings should be cleared.
- Multiple steals: map may accumulate many entries; cleanup ensures consistency when cards flip or leave herd.
- Simultaneous notifications: revocation handlers should be idempotent if the same id is removed twice.

Acceptance Criteria
- After Catnip steal, refresh the attacker’s page → owner badge/tooltip persists on the stolen FD card; other players see no label.
- After the stolen card is revealed face‑up or discarded, labels disappear immediately, and a refresh does not restore them.
- No public notification contains the stolen type.

Implementation Plan (Do Not Implement Yet)
- Server
  - Add helpers: `getKnownMap()`, `setKnownMap(map)`, `addKnown(playerId, cardId, type)`, `clearKnown(cardId[, ownerId])`.
  - Hook into Catnip resolution where `cardStolenPrivate` is sent to also call `addKnown`.
  - Hook into revocation notifications to call `clearKnown`.
  - `getAllDatas`: include `known_identities` for current player only.
- Client
  - Merge `gamedatas.known_identities` into `this.knownFD` in `setup`.
  - On revocation notifications, delete `this.knownFD[cardId]` and remove any badge node if present.

Test Plan (E2E)
- CN1 persistence: steal non‑Catnip → refresh → attacker sees label;
  flip the card face‑up via a test hook → label removed → refresh → stays removed.
- CN6 substitution: steal LP via intercept → refresh → attacker sees LP label.
- Visibility: secondary tab never shows labels; public notifications contain no `type`.

Open Questions
- If the stolen FD card later moves to another player’s herd (future feature), should knowledge transfer? Proposed: no; knowledge is tied to the viewer, not the card’s new owner.
- If a player resigns/rejoins, BGA preserves `globals`; behavior remains consistent.

