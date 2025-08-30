# Phase 2 Plan - Engineer Guide (Complete Build)

This repository already contains a complete server-and-client implementation. Use this guide to run, test and extend it.

## 1. Run locally

- Copy the contents of `src/` into a BGA Studio game project named `bgacats`.
- Import `dbmodel.sql` in the Studio admin page (or re-create tables by running any action, BGA will auto-create with the Deck module).
- Make sure `gameinfos.inc.php` is present and the game name matches your Studio project folder (`bgacats`).

## 2. Build overview

- **Server** - `bgacats.game.php` with helper `modules/HCRules.php`.
- **States** - `states.inc.php`.
- **Material/constants** - `material.inc.php`.
- **Stats** - `stats.inc.php`.
- **DB model** - `dbmodel.sql` (standard Deck tables).
- **Client** - `bgacats.view.php`, `bgacats_bgacats.tpl`, `bgacats.js`, `bgacats.css`.
- **Design addendum** - `design_addendum_v1.2.md` explains the Laser Pointer buff used here.

## 3. Test checklist

1. 2 players - play Kitten truthfully, no challenge - card enters herd.
2. Challenge a truthful play - attacker chooses blind discards from each challenger.
3. Challenge a bluff - first challenger chooses blind discard from attacker, turn ends.
4. Alley Cat vs Alley Cat in hand - ineffective, reveal and return, attacker's card still enters herd.
5. Catnip steals non-Catnip - card moves to attacker's herd face-down, identity preserved.
6. Animal Control vs Animal Control in herd - ineffective, target flips face-up and stays.
7. Interception from hand and from herd with Laser Pointer - challenge success and failure paths.
8. End trigger when a player reaches 0 hand cards, scoring and tie.
9. Hot-seat with 3+ players - multi-active challenge windows unblock properly.
10. Zombie players auto-pass in windows.

## 4. Extending

- To tweak values, see `HCRules::$CARD_VALUES` in `modules/HCRules.php`.
- To change the Laser Pointer buff, set `HCRules::$BUFF_LASER_TO_HERD = false` if you want original discard behaviour.
- Client text strings are in `this.T(...)` calls in `bgacats.js` for easy localisation.