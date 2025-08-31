# Herding Cats — Run Log

Purpose
- Persistent, human-readable notes to resume work if context is lost.
- Summarize sessions, test results, table links, issues, and next steps.
- Keep this concise and append-only; link back to detailed plans where needed.

How To Use
- Add a new session block per working session.
- Reference test cases via anchors (e.g., [K1](testplans/kitten.md#k1)).
- Capture table id(s), high-level results, and any follow-ups.

Template
```
## YYYY-MM-DD HH:MM TZ — <short summary>
- Studio account: <name>
- Table(s): <links or ids>
- Deployed from: <branch/commit or local>
- Tests attempted: <IDs with pass/fail>
- Key observations: <bullets>
- Issues/Risks: <bullets>
- Next steps: <bullets>
```

---

## 2025-08-31 14:35 PT — Bootstrapped E2E + K1
- Studio account: PaidiaGames0 (Studio domain)
- Table: https://studio.boardgamearena.com/1/herdingcats?table=763474
- Deployed from: local workspace (auto-sync active)

- Tests attempted:
  - [x] [K1 — Truthful, unchallenged](testplans/kitten.md#k1)

- Key observations:
  - Challenge window appeared; opponent passed; flow resolved; herd placement notification fired.
  - Actor hand counter decremented: 7 → 6 as expected for K1.
  - Logs show `cardPlayed`, `challengeWindow`, `herdUpdate` notifications in sequence.

- Issues/Risks:
  - After a full page refresh, face-down herd items did not reconstruct (hand counts were correct). Likely `getAllDatas()` returns only `face_down_count` without the per-card ids needed for UI rebuild.

- Next steps:
  - Fix `getAllDatas()` to include face-down herd card ids (owner-visible identities optional) so `setup()` can rebuild stocks after refresh.
  - Run [K2](testplans/kitten.md#k2) to validate truthful, challenged flow and penalty discard.
  - Add a simple `reports/` JSON per-session exporter if we want machine-readable history.

## 2025-08-31 14:50 PT — K1 retest + Express Stop discipline
- Studio account: PaidiaGames0 (Studio domain)
- Table: https://studio.boardgamearena.com/1/herdingcats?table=763474
- Deployed from: local workspace (auto-sync active)

- Tests attempted:
  - [x] [K1 — Truthful, unchallenged](testplans/kitten.md#k1)

- Key observations:
  - getAllDatas now returns herds.face_down ids and face_up cards; refresh correctly repopulates herd-FD.
  - Hand counters use camelCase `handCounts`; UI counters updated and persisted.

- Issues/Risks:
  - None noted for K1 after fix.

- Clean up:
  - Per test discipline enforced: performed Express Stop on the table page after finishing K1 (table shows “Game has ended”).

- Next steps:
  - Proceed to [K2](testplans/kitten.md#k2) once ready.

## 2025-08-31 14:55 PT — Test docs: blanket Express Stop per case
- Updated Playwright guide and all per-card test plans to include an explicit end-of-case Cleanup step: perform Express Stop on the table page after each case.
- Intent: avoid stale state leaking between cases; make cleanup unmissable.
