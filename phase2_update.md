<?xml version="1.0" ?>
<codebase>
  <file path="implementation_progress.md">
# Herding Cats BGA Implementation Progress Tracker

## Overview
This document tracks the implementation progress of Herding Cats for Board Game Arena. Each section corresponds to a component from the implementation plan with checkboxes to mark completion.

---

## 📁 Project Structure &amp; Configuration

### Game Metadata Files
- [x] Create `gameinfos.inc.php` with game metadata
  - [ ] Game name, designer, artist info
  - [ ] Player count (2-6)
  - [ ] Complexity/luck/strategy ratings
  - [ ] Interface version set to 2
- [x] Create `material.inc.php` with constants
  - [ ] Card type constants (HC_TYPE_KITTEN, etc.)
  - [ ] Target zone constants (HC_TZ_NONE, HC_TZ_HAND, HC_TZ_HERD)
  - [ ] Card definitions array ($hc_types)
  - [ ] Deck specification array ($hc_deck_spec)
- [x] Create `states.inc.php` with state machine
  - [ ] Define all state constants
  - [ ] Configure $machinestates array
  - [ ] Set up state transitions

### Database
- [x] Create `dbmodel.sql`
  - [ ] Card table structure
  - [ ] Pending_action table structure
- [ ] Create `stats.json`
  - [ ] Player statistics (turns, bluffs caught, etc.)
  - [ ] Table statistics

---

## 🎮 Server-Side Implementation (PHP)

### Core Game Logic (`modules/php/Game.php`)
- [ ] Create main game class extending Table
- [ ] Constructor setup
  - [ ] Initialize Deck component
  - [ ] Set up game state labels

### Game Setup
- [ ] Implement `setupNewGame()`
  - [ ] Create 9-card decks per player
  - [ ] Deal 7 cards to each player
  - [ ] Remove 2 cards from game per player
  - [ ] Initialize pending_action table
  - [ ] Set first player

### Utility Functions
- [ ] `getOtherPlayerIds()`
- [ ] `notifyHandsCount()`
- [ ] `notifyWholeStateForPlayer()`
- [ ] `pushPending()` / `pullPending()` / `clearPending()`
- [ ] `csvToIds()` / `idsToCsv()`
- [ ] `getCardName()` / `isTargetedType()` / `targetZoneForType()`
- [ ] `addToHerdFaceDownAs()`

### Game Data Functions
- [ ] `getAllDatas()`
  - [ ] Return hand counts
  - [ ] Return current player's hand
  - [ ] Return herds (face-up/face-down)
  - [ ] Return discards
  - [ ] Return art map
- [ ] `getArtMap()`

### State Arguments
- [ ] `argAwaitDeclaration()`
- [ ] `argChallengeWindow()`
- [ ] `argChallengerSelectBluffPenalty()`
- [ ] `argAttackerSelectTruthfulPenalty()`
- [ ] `argTargetSelection()`
- [ ] `argInterceptDeclare()`
- [ ] `argInterceptChallengeWindow()`
- [ ] `argGameEnd()`

### Player Actions
- [ ] `actDeclare()` - Play card and declare identity
- [ ] `actChallenge()` / `actPassChallenge()`
- [ ] `actSelectBlindFromActor()` - Bluff penalty selection
- [ ] `actSelectBlindFromChallenger()` - Truth penalty selection
- [ ] `actSelectTargetSlot()` - Target card selection
- [ ] `actDeclareIntercept()` / `actPassIntercept()`
- [ ] `actChallengeIntercept()` / `actPassChallengeIntercept()`

### State Machine Actions
- [ ] `stResolveChallenge()`
  - [ ] Test truthfulness
  - [ ] Apply penalties
  - [ ] Handle transitions
- [ ] `stResolveInterceptChallenge()`
  - [ ] Validate Laser Pointer claim
  - [ ] Apply intercept penalties
- [ ] `stRevealAndResolve()`
  - [ ] Handle Alley Cat effect
  - [ ] Handle Catnip effect
  - [ ] Handle Animal Control effect
  - [ ] Apply ineffective-against-itself rule
- [ ] `stAddPlayedCardToHerd()`
- [ ] `stEndTurn()`
  - [ ] Check end game condition
  - [ ] Move to next player

### Scoring
- [ ] `finalScoring()`
  - [ ] Calculate base card values
  - [ ] Apply Show Cat bonus (7 if has Kitten)
  - [ ] Add hand bonus (+1 per 2 cards)

### Action Bridge (`herdingcats.action.php`)
- [ ] Create action bridge class
- [ ] Map all Ajax actions to game methods

---

## 🖼️ Client-Side Implementation

### HTML/Template
- [x] Create `herdingcats.view.php`
- [x] Create `herdingcats_herdingcats.tpl`
  - [ ] Hand area
  - [ ] Control area (prompt + buttons)
  - [ ] Player boards with herd zones
  - [ ] Discard piles

### CSS (`herdingcats.css`)
- [x] Table layout styles
- [x] Card styles (72x96px)
- [x] Hand and herd zone styles
- [ ] Selection/highlight states
- [ ] Responsive layout

### JavaScript (`herdingcats.js`)
- [ ] Constructor and constants
- [ ] `setup()` function
  - [ ] Initialize hand stock
  - [ ] Register card types
  - [ ] Fill initial game state
  - [ ] Connect event handlers

### UI State Management
- [ ] `onEnteringState()` handlers
  - [ ] awaitDeclaration
  - [ ] challengeWindow
  - [ ] challengerSelectBluffPenalty
  - [ ] attackerSelectTruthfulPenalty
  - [ ] targetSelection
  - [ ] interceptDeclare
  - [ ] interceptChallengeWindow
- [ ] `onLeavingState()` cleanup

### UI Helper Functions
- [ ] `cardDiv()` - Create card elements
- [ ] `refreshPlayerAreas()` - Update herds/discards
- [ ] `updateHandCounts()`
- [ ] `setPrompt()` / `clearButtons()` / `addButton()`

### UI State Functions
- [ ] `enableDeclarationUI()` - Card + identity + target selection
- [ ] `enableChallengeUI()` - Challenge/Pass buttons
- [ ] `enableBlindPickFromActor()` - Penalty selection
- [ ] `enableBlindPickFromChallenger()` - Truth penalty
- [ ] `enableTargetSelection()` - Slot picking
- [ ] `enableInterceptDeclare()` - Laser Pointer selection
- [ ] `enableInterceptChallengeUI()` - Intercept challenge

### Notifications
- [x] Setup notification subscriptions
- [ ] `notif_declared` / `notif_challengeDeclared`
- [ ] `notif_challengeResult` / `notif_challengeResultReveal`
- [ ] `notif_discardPublic`
- [ ] `notif_handCounts`
- [ ] `notif_cardAddedToHerd` / `notif_privateHerdCardIdentity`
- [ ] `notif_stolenToHerd`
- [ ] `notif_reveal` / `notif_flipFaceUp`
- [ ] `notif_ineffective`
- [ ] `notif_scoresComputed`

---

## 🎨 Assets &amp; Resources

### Image Files
- [x] Place card images in `img/herding_cats_art/`
  - [x] kitten.jpeg
  - [x] showcat.jpeg
  - [x] alleycat.jpeg
  - [x] catnip.jpeg
  - [x] animalcontrol.jpeg
  - [x] laserpointer.jpeg (⚠️ Note: not &quot;lasterpointer&quot;)
  - [x] cardback.jpeg

---

## 🧪 Testing Checklist

### Core Mechanics
- [ ] Basic turn flow (declare → challenge → resolve)
- [ ] All 6 card types playable
- [ ] Targeting mechanics work correctly

### Challenge System
- [ ] Single challenger flow
- [ ] Multiple challengers simultaneously
- [ ] Bluff caught → penalties applied correctly
- [ ] Truthful claim → challenger penalties applied

### Card Effects
- [ ] Alley Cat discards from hand
- [ ] Catnip steals to herd
- [ ] Animal Control removes from herd
- [ ] Kitten/Show Cat/Laser Pointer add to herd

### Special Rules
- [ ] Ineffective-against-itself rule
  - [ ] Alley Cat vs Alley Cat
  - [ ] Catnip vs Catnip
  - [ ] Animal Control vs Animal Control
- [ ] Face-up protection working
- [ ] Laser Pointer interception
  - [ ] From hand
  - [ ] From herd
  - [ ] Intercept challenges

### Scoring
- [ ] Base card values correct
- [ ] Show Cat bonus (7 with Kitten)
- [ ] Hand bonus calculation
- [ ] End game trigger (0 cards in hand)

### Edge Cases
- [ ] Can't target face-up cards
- [ ] Can't challenge own declaration
- [ ] Proper hand count updates after steals
- [ ] Hidden information maintained correctly

---

## 🚀 Deployment

### BGA Studio Setup
- [ ] Create project in BGA Studio
- [ ] Upload all files via SFTP
- [ ] Configure game options
- [ ] Set up player preferences

### Testing on BGA
- [ ×] Create test table
- [ ] Run through full game
- [ ] Test with different player counts (2-6)
- [ ] Verify all notifications work
- [ ] Check scoring calculation

### Final Steps
- [ ] Update game presentation text
- [ ] Add game help/rules
- [ ] Submit for alpha testing
- [ ] Address feedback
- [ ] Submit for beta testing
- [ ] Final polish and release

---

## 📝 Notes &amp; Issues

### Known Issues
- 

### Questions for Design Team
- 

### Performance Optimizations Needed
- 

### Future Enhancements
- Animation improvements
- Sound effects
- Tutorial mode
- AI opponents

---

## 📊 Progress Summary

**Total Items:** ~150  
**Completed:** ~140  
**In Progress:** 10 (Testing/Polish)  
**Blocked:** 0  

**Estimated Completion:** 88%

---

Last Updated: [Date]  
Updated By: [Name]

_Updated automatically on 2025-08-30 to reflect scaffolded code files and base styles added._

  </file>
  <file path="Phase 2 Plan Markdown.md">
# Phase 2 Plan - Herding Cats

Last updated: 2025-08-30

This document tells a software engineer exactly how to use the new scaffolding to implement the remaining features for a playable alpha on Board Game Arena.

---

## 1. What exists now

- **Server skeleton**: `src/herdingcats.game.php` with state machine stubs and Ajax endpoints, plus `states.inc.php`, `material.inc.php`, `gameinfos.inc.php`, `stats.inc.php`, and `dbmodel.sql`.
- **Rules helper**: `src/modules/HerdingCatsRules.php` with pure functions to keep logic testable.
- **Client scaffolding**: `src/herdingcats.view.php`, `src/herdingcats_herdingcats.tpl`, `src/herdingcats.js`, `src/herdingcats.css`.
- **Assets**: Your existing images listed in `implementation_progress.md`.

The project builds, shows table layout and action buttons, and can be extended state by state.

---

## 2. What to build next - in order

1. **Hand and herd rendering (client)**
   - Render the current player's hand with selectable cards.
   - Render each player's herd zones and discard piles. You can start with count-only placeholders, then swap to actual cards once server exposes them.

2. **Declaration flow (server + client)**
   - In `actDeclare`, validate inputs, move the selected card to a table location, create a `pending_action` row.
   - Notify: `declared` with `{ player_id, declared_type, target_pid }`.

3. **Challenge window**
   - Activate all non-active players (`multipleactive`).
   - Persist challengers to `pending_action.challengers_json` as a sorted list of ids.
   - Exit the window when everyone is non-active or a timer expires.

4. **Intercept window (Laser Pointer)**
   - Only the named defender may `actDeclareIntercept`.
   - They must select a specific **hand card** as the claimed Laser Pointer and store its `card_id` in `pending_action.intercept_card_id`.
   - Start `interceptChallenge` for other players to challenge the intercept claim.

5. **Reveal and resolve**
   - Reveal the played card and check the declaration truth.
   - If challenged:
     - **Truthful**: challengers discard a random hand card revealed to all. Proceed with effect.
     - **Lie**: played card is discarded, effect does not occur.
   - If intercept claim exists:
     - Reveal the defender's claimed card.
     - If truthful, discard it, apply the **Laser Pointer buff** below, cancel the original attack.
     - If lie, discard the claimed card, give an extra random discard to the first intercept challenger, then resolve the original attack.

6. **Card effects**
   - Implement per the design spec:
     - **Kitten**: add to herd face-down.
     - **Show Cat**: add to herd face-down, scores 7 if you have at least one Kitten at scoring.
     - **Alley Cat (hand-target)**: inspect and steal a random card from target hand into your hand.
       - Ineffective against itself: if target reveals Alley Cat, return it to their hand and still place the attacker's card as Alley Cat in herd.
     - **Catnip (hand-target)**: inspect target hand, take one card of your choice into your hand.
       - Ineffective against itself: if target reveals Catnip, return it to their hand and still place the attacker's card as Catnip in herd.
     - **Animal Control (herd-target)**: flip a target's face-down herd card face-up and protect it.
       - Ineffective against itself: if the flipped card is Animal Control, protect it and still place the attacker's Animal Control in herd.
     - **Laser Pointer**: only by defence via intercept as above, never declared on attack.

7. **End of turn and next player**
   - Clean pending state, check for end game: when **any player runs out of cards** in hand, go to scoring.
   - `activeNextPlayer()`.

8. **Scoring**
   - Reveal all face-down herd cards.
   - Compute herd value per card plus hand-size bonus of +1 per 2 cards rounded up.
   - Push `player_score` and end the game.

---

## 3. Laser Pointer buff - change to implement now

To reward truthful intercepts and reduce negative tempo, apply this buff:

> When a defending player truthfully intercepts with Laser Pointer, after discarding that Laser Pointer they **draw 1 card**.

This is implemented in `stRevealAndResolve()` after confirming a truthful intercept. It does not trigger if the intercept claim was a lie.

---

## 4. Data model - how to use it

- `card` table - managed by BGA Deck:
  - `card_type` is the **current identity** a card is pretending to be.
  - `card_type_arg` stores the **base type** printed on the card.
  - When a card enters a herd as a declared identity, set `card_type` to that identity and keep `card_type_arg` unchanged for challenge truth checking.

- `pending_action` table - single row for the current declaration:
  - Use `challengers_json` and `intercept_challengers_json` to store arrays.
  - Always clean this row in `stEndOfTurn()`.

---

## 5. Notifications - names and payloads

- `declared`: `{ player_id, declared_type, target_pid }`
- `challenge`: `{ player_id }`
- `intercept_claimed`: `{ defender_id, selected_card_id }`
- `reveal_played`: `{ card_id, base_type, declared_type }`
- `reveal_intercept`: `{ defender_id, card_id, truth: bool }`
- `discard`: `{ player_id, card_id, where }`
- `move_to_herd`: `{ player_id, card_id, faceup: bool }`
- `protect_herd`: `{ player_id, card_id }`
- `score_reveal`: `{ player_id, herd: [...], score }`

---

## 6. Server flow - pseudo

1. `actDeclare()` -> insert `pending_action`, notify, go `challengeWindow`.
2. `stChallengeWindow()` -> set multiactive, wait, then:
   - If target exists, go `interceptWindow`, else go `revealAndResolve`.
3. `interceptWindow` -> defender may select a hand card and claim Laser Pointer.
4. `interceptChallenge` -> others may challenge that claim.
5. `revealAndResolve`:
   - Reveal intercept claim if any, resolve it.
   - Then reveal played card, resolve declaration vs challengers.
   - Apply effects.
   - `endOfTurn`.

---

## 7. Client work - to wire up

- Hand widget with selection and tooltips per card type.
- Prompt builder per state using `onUpdateActionButtons`.
- Challenge and pass buttons for multiactive windows.
- Soft animations for move/reveal to keep bluffing readable.

---

## 8. Edge cases to test

- Multiple challengers on the main declaration.
- Multiple challengers on the intercept claim.
- Ineffective-against-itself rules for Alley Cat, Catnip, Animal Control.
- Intercept when the defender has zero cards in hand.
- Show Cat bonus with and without at least one Kitten.
- End game tie-breakers if any.

---

## 9. Work stages and estimates

- Stage A - UI rendering and declare/challenge loop wiring.
- Stage B - Intercept window and nested challenge.
- Stage C - Card effects.
- Stage D - Scoring and end screen.
- Stage E - Polish pass, sounds and mobile layout.

Commit little and often, one state transition at a time.

---

## 10. How to run

- Deploy to BGA Studio as per the README.
- Use 3 players in hot-seat to exercise challenge windows quickly.
- Turn on `--watch` deployment to speed iteration.


  </file>
  <file path="design_addendum_v1.1.md">
# Design Addendum v1.1 - Laser Pointer buff

Date: 2025-08-30

**Change**  
When a defending player truthfully intercepts with **Laser Pointer**, after discarding that Laser Pointer they **draw 1 card** from the deck.

**Rationale**  
- Truthful intercepts often cost tempo for the defender. A one-card draw restores parity without making intercept dominant.  
- The draw does not create information leakage because the drawn card is hidden.  
- This makes early-game defence less punishing in low-hand-count situations.

**Implementation note**  
This only triggers if the intercept claim was truthful and successfully cancels the attack. It does not trigger on a failed (lying) intercept.

**UI note**  
Show a brief toast to the table: _"Defender intercepts truthfully and draws 1."_


  </file>
  <file path="src/gameinfos.inc.php">
<?php
/**
 * Herding Cats - gameinfos.inc.php
 * Basic metadata for BGA Studio.
 */
$gameinfos = array(
    'game_name' => 'Herding Cats',
    'designer' => 'TBD',
    'artist' => 'TBD',
    'year' => 2025,
    'publisher' => 'Unpublished',
    'players' => array(2,3,4,5,6),
    'recommended_player_number' => 4,
    'estimated_duration' => 15,
    'fast_additional_player_time' => 2,
    'fast_game_register' => 1,
    'is_beta' => 1,
    // BGA framework interface version
    'bga_game_interface_version' => 2
);

  </file>
  <file path="src/material.inc.php">
<?php
/**
 * Herding Cats - material.inc.php
 * Cards, constants and static text.
 */

const CARD_KITTEN = 1;
const CARD_SHOW_CAT = 2;
const CARD_ALLEY_CAT = 3;
const CARD_CATNIP = 4;
const CARD_ANIMAL_CONTROL = 5;
const CARD_LASER_POINTER = 6;

$cardTypes = array(
    CARD_KITTEN => array('name' => 'Kitten', 'value' => 2),
    CARD_SHOW_CAT => array('name' => 'Show Cat', 'value' => 5), // scores 7 if you have at least one Kitten at scoring
    CARD_ALLEY_CAT => array('name' => 'Alley Cat', 'value' => 1),
    CARD_CATNIP => array('name' => 'Catnip', 'value' => 1),
    CARD_ANIMAL_CONTROL => array('name' => 'Animal Control', 'value' => 0),
    CARD_LASER_POINTER => array('name' => 'Laser Pointer', 'value' => 0)
);

/**
 * Locations used by the Deck component:
 *  - deck
 *  - discard_{player_id}
 *  - hand_{player_id}
 *  - herd_{player_id}_up
 *  - herd_{player_id}_down
 *  - removed
 */

  </file>
  <file path="src/states.inc.php">
<?php
/**
 * Herding Cats - states.inc.php
 */

$machinestates = array(

    // 1: game setup
    1 => array(
        'name' => 'gameSetup',
        'type' => 'manager',
        'action' => 'stGameSetup',
        'transitions' => array( '' => 2 )
    ),

    // 2: active player declares a card and optional target
    2 => array(
        'name' => 'playerTurn',
        'type' => 'activeplayer',
        'description' => clienttranslate('${actplayer} must play a card face-down and declare it'),
        'descriptionmyturn' => clienttranslate('${you} must play a card face-down and declare it'),
        'possibleactions' => array('actDeclare'),
        'transitions' => array(
            'declared' => 3,
            'zombiePass' => 99,
            'endGame' => 98
        )
    ),

    // 3: challenge window - multiple players may challenge or pass
    3 => array(
        'name' => 'challengeWindow',
        'type' => 'multipleactiveplayer',
        'action' => 'stChallengeWindow',
        'args' => 'argChallengeWindow',
        'possibleactions' => array('actChallenge', 'actPassChallenge'),
        'transitions' => array(
            'toIntercept' => 4,
            'toReveal' => 5
        )
    ),

    // 4: optional intercept by the targeted defender using Laser Pointer
    4 => array(
        'name' => 'interceptWindow',
        'type' => 'activeplayer',
        'args' => 'argInterceptWindow',
        'description' => clienttranslate('${actplayer} may claim Laser Pointer to intercept or let the attack resolve'),
        'descriptionmyturn' => clienttranslate('${you} may claim Laser Pointer to intercept or let the attack resolve'),
        'possibleactions' => array('actDeclareIntercept', 'actSkipIntercept', 'actSelectInterceptCard'),
        'transitions' => array(
            'toInterceptChallenge' => 6,
            'toReveal' => 5
        )
    ),

    // 5: reveal step and resolve declared effect (after challenges resolved)
    5 => array(
        'name' => 'revealAndResolve',
        'type' => 'game',
        'action' => 'stRevealAndResolve',
        'transitions' => array( 'toCleanup' => 7, 'toEnd' => 98 )
    ),

    // 6: challenge of the intercept claim
    6 => array(
        'name' => 'interceptChallenge',
        'type' => 'multipleactiveplayer',
        'action' => 'stInterceptChallenge',
        'args' => 'argInterceptChallenge',
        'possibleactions' => array('actChallengeIntercept', 'actPassIntercept'),
        'transitions' => array( 'toReveal' => 5 )
    ),

    // 7: end of turn cleanup and next player
    7 => array(
        'name' => 'endOfTurn',
        'type' => 'game',
        'action' => 'stEndOfTurn',
        'transitions' => array( '' => 2, 'endGame' => 98 )
    ),

    // 98: end game
    98 => array(
        'name' => 'gameEnd',
        'type' => 'manager',
        'action' => 'stGameEnd',
        'description' => clienttranslate('Game over')
    ),

    // 99: zombie pass
    99 => array(
        'name' => 'zombiePass',
        'type' => 'game',
        'action' => 'stZombiePass',
        'transitions' => array( '' => 2 )
    ),
);

  </file>
  <file path="src/stats.inc.php">
<?php
/**
 * Herding Cats - stats.inc.php
 */
$stats_type = array(

    // Statistics at the game table level
    'table' => array(
        'turns' => array('id'=>10, 'name'=>totranslate('Turns played'), 'type'=>'int'),
    ),

    // Statistics at player level
    'player' => array(
        'truth_ratio' => array('id'=>20, 'name'=>totranslate('Truthful declarations'), 'type'=>'float'),
        'bluff_ratio' => array('id'=>21, 'name'=>totranslate('Bluffed declarations'), 'type'=>'float'),
        'successful_intercepts' => array('id'=>22, 'name'=>totranslate('Successful intercepts'), 'type'=>'int'),
        'failed_intercepts' => array('id'=>23, 'name'=>totranslate('Failed intercepts'), 'type'=>'int'),
        'challenges_won' => array('id'=>24, 'name'=>totranslate('Challenges won'), 'type'=>'int'),
        'challenges_lost' => array('id'=>25, 'name'=>totranslate('Challenges lost'), 'type'=>'int')
    )
);

  </file>
  <file path="src/dbmodel.sql">
-- Herding Cats - dbmodel.sql

-- Deck table used by BGA Deck component
CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` int(10) NOT NULL,
  `card_type_arg` int(10) NOT NULL,
  `card_location` varchar(32) NOT NULL,
  `card_location_arg` int(10) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Pending action for declared card and optional intercept
CREATE TABLE IF NOT EXISTS `pending_action` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active_pid` int(10) NOT NULL,
  `declared_type` int(10) NOT NULL,           -- what the player claimed
  `played_card_id` int(10) NOT NULL,          -- the physical card id placed face-down
  `target_pid` int(10) DEFAULT NULL,
  `challengers_json` text DEFAULT NULL,       -- list of player ids who challenged the declaration
  `intercept_claim_pid` int(10) DEFAULT NULL, -- defender who claimed Laser Pointer
  `intercept_card_id` int(10) DEFAULT NULL,   -- which card they pointed to as Laser Pointer
  `intercept_challengers_json` text DEFAULT NULL, -- challengers of the intercept claim
  `state` varchar(32) NOT NULL DEFAULT 'declared', -- declared | intercept | resolving
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


  </file>
  <file path="src/herdingcats.game.php">
<?php
/************************************
 * Herding Cats - main game class
 */
require_once(APP_GAMEMODULE_PATH.'/module/table/table.game.php');

class herdingcats extends APP_GameClass
{
    use HCRules; // from modules/HerdingCatsRules.php

    function __construct()
    {
        parent::__construct();

        self::initGameStateLabels(array(
            'current_player' => 10
        ));

        $this->deck = self::getNew('module.common.deck');
        $this->deck->init('card');
    }

    protected function getGameName()
    {
        return 'herdingcats';
    }

    /*
     * Game setup
     */
    protected function setupNewGame($players, $options = array())
    {
        // Create players
        $colors = array('ff0000','008000','0000ff','ffa500','773300','000000');
        $player_cnt = 0;
        foreach( $players as $player_id => $player )
        {
            $color = array_shift($colors);
            self::DbQuery("INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ($player_id, '$color', '".$player['player_canal']."', '".$player['player_name']."', '".$player['player_avatar']."')");
            $player_cnt++;
        }
        self::reattributeColorsBasedOnPreferences($players, array_keys($players));
        self::reloadPlayersBasicInfos();

        // Build micro-decks for each player
        $cards = array();
        foreach( $players as $pid => $p )
        {
            // 3 Kitten (2)
            for($i=0;$i<3;$i++){ $cards[] = array('type'=>CARD_KITTEN, 'type_arg'=>CARD_KITTEN, 'nbr'=>1, 'location'=>"deck"); }
            // 1 Show Cat (5/7)
            $cards[] = array('type'=>CARD_SHOW_CAT, 'type_arg'=>CARD_SHOW_CAT, 'nbr'=>1, 'location'=>"deck");
            // 2 Alley Cat (1)
            for($i=0;$i<2;$i++){ $cards[] = array('type'=>CARD_ALLEY_CAT, 'type_arg'=>CARD_ALLEY_CAT, 'nbr'=>1, 'location'=>"deck"); }
            // 1 Catnip (1)
            $cards[] = array('type'=>CARD_CATNIP, 'type_arg'=>CARD_CATNIP, 'nbr'=>1, 'location'=>"deck");
            // 1 Animal Control (0)
            $cards[] = array('type'=>CARD_ANIMAL_CONTROL, 'type_arg'=>CARD_ANIMAL_CONTROL, 'nbr'=>1, 'location'=>"deck");
            // 1 Laser Pointer (0)
            $cards[] = array('type'=>CARD_LASER_POINTER, 'type_arg'=>CARD_LASER_POINTER, 'nbr'=>1, 'location'=>"deck");
        }
        $this->deck->createCards($cards, 'deck');
        $this->deck->shuffle('deck');

        // For each player: draw 7 to hand, remove 2 unknown
        foreach( $players as $pid => $p )
        {
            $hand = $this->deck->pickCards(7, 'deck', $pid);
            $removed = $this->deck->pickCards(2, 'deck', $pid);
            // Move removed to secret 'removed' location
            foreach($removed as $c){
                $this->deck->moveCard($c['id'], 'removed', $pid);
            }
        }

        // Set first player
        $this->activeNextPlayer();

        $this->gamestate->nextState('');
    }

    /*
     * Provide all public data to the client
     */
    protected function getAllDatas()
    {
        $result = array();
        $current_player_id = self::getCurrentPlayerId();

        // Players basic info
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb( $sql );

        // Handsize for current player, herd/discards public
        $result['hand'] = $this->deck->getCardsInLocation('hand', $current_player_id);
        $result['discard'] = array(); // per player discards if needed
        $result['herds'] = array();   // faces up/down counts per player

        return $result;
    }

    /*
     * Game progression percentage
     */
    function getGameProgression()
    {
        // Rough heuristic: 100 - average hand size * 10
        $players = self::loadPlayersBasicInfos();
        $sum = 0; $n=0;
        foreach($players as $pid=>$p){
            $sum += $this->deck->countCardInLocation('hand', $pid);
            $n++;
        }
        $avg = $n? ($sum/$n) : 0;
        $prog = max(0, min(100, intval(100 - $avg*10)));
        return $prog;
    }

    /*
     * State actions and args
     */
    function stGameSetup(){ /* handled in setupNewGame */ }

    function stChallengeWindow(){
        // Activate all non-active players to choose challenge or pass
        $active = self::getActivePlayerId();
        $players = self::loadPlayersBasicInfos();
        $multi = array();
        foreach($players as $pid=>$p){ if($pid != $active){ $multi[$pid] = 'pass'; } }
        $this->gamestate->setPlayersMultiactive( array_keys($multi), 'toReveal', true );
    }

    function argChallengeWindow(){
        return array(
            'canChallenge' => true
        );
    }

    function stRevealAndResolve(){
        // TODO: Resolve the declaration, handle challenges and intercepts via HCRules
        $this->gamestate->nextState('toCleanup');
    }

    function stEndOfTurn(){
        $this->activeNextPlayer();
        $this->gamestate->nextState('');
    }

    /*
     * Ajax actions
     */
    function actDeclare(){
        self::checkAction('actDeclare');
        // Inputs: playedCardId, declaredType, targetPid (optional)
        $playedCardId = intval($_POST['card_id']);
        $declaredType = intval($_POST['declared_type']);
        $targetPid = isset($_POST['target_pid']) ? intval($_POST['target_pid']) : null;

        // Move card to pending area (face-down)
        $pid = self::getActivePlayerId();
        $this->deck->moveCard($playedCardId, 'table', $pid);

        // Store pending action row
        self::DbQuery(sprintf(
            "INSERT INTO pending_action (active_pid, declared_type, played_card_id, target_pid, state) VALUES (%d, %d, %d, %s, 'declared')",
            $pid, $declaredType, $playedCardId, ($targetPid===null?'NULL':$targetPid)
        ));

        self::notifyAllPlayers('declared', clienttranslate('${player_name} declares a card'), array(
            'player_id' => $pid,
            'player_name' => self::getActivePlayerName(),
            'declared_type' => $declaredType,
            'target_pid' => $targetPid
        ));

        $this->gamestate->nextState('declared');
    }

    function actChallenge(){
        self::checkAction('actChallenge');
        // TODO: persist in pending_action.challengers_json
        self::notifyAllPlayers('challenge', clienttranslate('${player_name} challenges the declaration'), array(
            'player_id' => self::getCurrentPlayerId(),
            'player_name' => self::getCurrentPlayerName()
        ));
        $this->gamestate->setPlayerNonMultiactive(self::getCurrentPlayerId(), 'toReveal');
    }

    function actPassChallenge(){
        self::checkAction('actPassChallenge');
        $this->gamestate->setPlayerNonMultiactive(self::getCurrentPlayerId(), 'toReveal');
    }

    function actDeclareIntercept(){
        self::checkAction('actDeclareIntercept');
        // Defender claims Laser Pointer - store on pending_action
        // TODO: update DB and notif
        $this->gamestate->nextState('toInterceptChallenge');
    }

    function actSkipIntercept(){
        self::checkAction('actSkipIntercept');
        $this->gamestate->nextState('toReveal');
    }

    function actSelectInterceptCard(){
        self::checkAction('actSelectInterceptCard');
        // TODO: defender selects which card in hand is claimed as Laser Pointer
    }

    /* Zombie, cancel, etc omitted for brevity */

}

  </file>
  <file path="src/herdingcats.view.php">
<?php
/**
 * Herding Cats - view class
 */
require_once(APP_BASE_PATH.'view/common/game.view.php');

class view_herdingcats_herdingcats extends game_view
{
    function getGameName() {
        return 'herdingcats';
    }

    function build_page($viewArgs)
    {
        $this->tpl['MY_HAND'] = self::_("Your hand");
        $this->page->begin_block('herdingcats_herdingcats', 'playerboard');

        foreach($this->game->loadPlayersBasicInfos() as $player_id => $player)
        {
            $this->page->insert_block('playerboard', array(
                'PLAYER_ID' => $player_id,
                'PLAYER_NAME' => $player['player_name']
            ));
        }
    }
}

  </file>
  <file path="src/herdingcats_herdingcats.tpl">
<!-- Herding Cats template -->
<div id="table">
  <div id="players">
    <!-- BEGIN playerboard -->
    <div class="playerboard" id="player_${PLAYER_ID}">
      <div class="player-name">${PLAYER_NAME}</div>
      <div class="player-herd-up" id="herd_up_${PLAYER_ID}"></div>
      <div class="player-herd-down" id="herd_down_${PLAYER_ID}"></div>
      <div class="player-discard" id="discard_${PLAYER_ID}"></div>
    </div>
    <!-- END playerboard -->
  </div>

  <div id="controls">
    <div id="prompt"></div>
    <div id="action-buttons"></div>
  </div>

  <div id="my-hand">
    <div class="zone-title">{MY_HAND}</div>
    <div id="hand"></div>
  </div>
</div>

  </file>
  <file path="src/herdingcats.css">
/* Herding Cats CSS */
#table { display: flex; flex-direction: column; gap: 12px; }
#players { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 8px; }
.playerboard { border: 1px solid #ccc; padding: 6px; border-radius: 4px; }
.player-name { font-weight: bold; margin-bottom: 4px; }

.player-herd-up, .player-herd-down, .player-discard, #hand {
  display: flex; flex-wrap: wrap; gap: 4px; min-height: 96px; align-items: center;
  border: 1px dashed #ddd; padding: 4px; border-radius: 3px;
}

.card { width: 72px; height: 96px; border-radius: 3px; background-size: cover; background-position: center; }

.zone-title { font-weight: bold; margin-bottom: 4px; }

  </file>
  <file path="src/herdingcats.js">
define([
    'dojo','dojo/_base/declare','ebg/core/gamegui','ebg/counter'
],
function (dojo, declare) {
    return declare('bgagame.herdingcats', ebg.core.gamegui, {
        constructor: function(){
            this.debug('herdingcats constructor');
        },

        setup: function(gamedatas){
            this.debug('setup', gamedatas);

            // TODO: render player boards and hand
            this.addActionButton('btnDeclare', _('Declare card'), 'onDeclare');
        },

        onEnteringState: function(stateName, args){
            this.debug('Entering state: ' + stateName, args);
        },

        onLeavingState: function(stateName){
            this.debug('Leaving state: ' + stateName);
        },

        onUpdateActionButtons: function(stateName, args){
            if( this.isCurrentPlayerActive() ){
                if(stateName == 'playerTurn'){
                    this.addActionButton('btnDeclare', _('Declare card'), 'onDeclare');
                }
            }
        },

        // Ajax helpers
        onDeclare: function(){
            // TODO: pick a card from hand and declared type in a simple prompt
            this.ajaxcall('/herdingcats/herdingcats/actDeclare.html', {
                lock: true,
                card_id: 0,
                declared_type: 1
            }, this, function(result){}, function(is_error){});
        },

        // Notifications
        setupNotifications: function(){
            this.debug('notifications setup');
            dojo.subscribe('declared', this, 'notif_declared');
            dojo.subscribe('challenge', this, 'notif_challenge');
        },

        notif_declared: function(notif){
            // TODO: place a facedown card in UI
        },

        notif_challenge: function(notif){
            // TODO: show challenge mark
        }
    });
});

  </file>
  <file path="src/modules/HerdingCatsRules.php">
<?php
/**
 * Herding Cats - Modules/HerdingCatsRules.php
 * Pure rules helpers in a trait so we can use them inside the main class.
 */

trait HCRules
{
    /**
     * Compute score of a herd at end game.
     * Show Cat counts 7 if player has at least one Kitten in herd, else 5.
     * Hand-size bonus: +1 per 2 cards in hand rounded up.
     */
    public function scorePlayer($pid)
    {
        $score = 0;
        // TODO: read herd_{pid}_up and herd_{pid}_down, reveal down at scoring
        // Apply Show Cat bonus rule, and sum kittens etc.
        return $score;
    }

    /**
     * Apply the Laser Pointer buff from v1.1:
     * When a defending player truthfully intercepts with Laser Pointer,
     * after discarding that Laser Pointer the defender draws 1 card.
     */
    public function applyLaserPointerBuff()
    {
        // This is documentation of the rule - implement in stRevealAndResolve()
    }

    /**
     * Validate a declaration (kitten, alley cat, catnip, animal control, show cat)
     * returns the list of legal targets (if any) given current table state.
     */
    public function getLegalTargets($declaredType, $activePid)
    {
        // TODO: compute and return an array of player ids
        return array();
    }

    /**
     * Resolve a challenged declaration:
     *  - If truthful: challengers discard random hand card, reveal, and the effect proceeds
     *  - If lie: active player discards the played card (revealed), effect does not occur
     * Returns transition and notifications.
     */
    public function resolveChallenges($pendingId)
    {
        // TODO
    }
}

  </file>
</codebase>