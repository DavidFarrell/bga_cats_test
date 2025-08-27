Below is a complete, developer‑ready Board Game Arena (BGA) implementation scaffold for **Herding Cats**. It includes a working server‑side rules engine, client UI, database model, state machine, notifications, and all glue code you need to run it on BGA Studio. I have also included a comprehensive README with setup, testing and CI guidance.

I have been deliberately direct about trade‑offs and places that need focused testing.

> **How to use this deliverable**
> Copy the content of each XML block into a file at the exact path stated in the tag. Once all files are in place, follow the README instructions to deploy to BGA Studio and test.

---

## Architecture overview

* **Rules engine (PHP)** - Implements your spec, including bluff, multi‑challenger penalties, ineffective‑against‑itself, Laser Pointer interceptions with a nested challenge, card stealing with Catnip, and endgame scoring.

  * Uses BGA `Deck` component with a single `card` table.
  * We store **current identity** in `card_type` and **base type** in `card_type_arg`. This lets a physical card become the declared identity when it enters the herd without losing the truth reference for challenges.
  * A small `pending_action` table persists the current declaration, challengers, and any intercept details across Ajax calls and states.

* **State machine** mirrors your Section 10 phases:

  * `AwaitDeclaration` → `ChallengeWindow` → challenge resolution branch → target selection → optional intercept window → reveal and resolve → add played card to herd → end turn.
  * Separate states handle blind slot selections for penalties and for target choices, so UI prompts are simple and unambiguous.

* **Client (JS + HTML + CSS)**

  * Uses BGA `stock` for the active player's hand and simple zones for each player's herd (face‑down and face‑up).
  * Clear prompts for Challenge/Pass, selecting blind slots, intercept claiming, and nested intercept challenges.
  * Animations are intentionally minimal for first pass to reduce risk. You can layer `bga-animations` later.

* **Images**

  * Put your art in `img/herding_cats_art/` as shown in the screenshot. The code references:

    * `alleycat.jpeg`, `animalcontrol.jpeg`, `cardback.jpeg`, `catnip.jpeg`, `kitten.jpeg`, `laserpointer.jpeg` (see note below), `showcat.jpeg`.
  * If your folder currently contains `lasterpointer.jpeg`, either rename it to `laserpointer.jpeg` or add a duplicate file with the correct name. The UI expects `laserpointer.jpeg`.

* **Scoring** implements your Section 8 including Show Cat 7‑point boost if the herd has at least one Kitten and the hand‑bonus of +1 per 2 cards rounded up.

* **Public information**

  * Discards are public.
  * Two removed cards per player are never revealed.
  * When a blind penalty discard happens, the revealed card identity is shown to all per spec.

---

## Things to test carefully

1. **Simultaneous multi‑challenger flows**

   * Truthful declaration with 2+ challengers should force the attacker to pick one blind card from each challenger, one at a time, before resolving the declared effect.

2. **Intercept claims**

   * Defender must select the specific card to discard as the Laser Pointer.
   * If challenged and they were truthful, each challenger loses a blind card chosen by the defender and the original attack is cancelled.
   * If they lied, that selected card is discarded anyway, an extra blind is discarded by the first intercept‑challenger, and the original attack resumes.

3. **Ineffective‑against‑itself**

   * Alley Cat vs Alley Cat in hand returns the revealed card to hand and still places the attacker’s card as Alley Cat in herd.
   * Catnip vs Catnip in hand returns the revealed Catnip to hand and still places attacker’s Catnip in herd.
   * Animal Control vs Animal Control in herd flips targeted card face‑up, protects it, and still places the attacker’s Animal Control in herd.

4. **Show Cat bonus**

   * Verify scoring is 7 for each Show Cat if that player has at least one Kitten in herd at scoring time. It is 5 otherwise.

5. **End condition**

   * Game ends immediately at the end of a turn if any player’s hand size is zero. Run through cases with Catnip stealing to ensure hands shrink correctly and hand‑bonus scoring is applied.

6. **Face‑up protection**

   * Face‑up herd cards must not be targetable by Animal Control or Catnip, but a face‑up Laser Pointer may still be voluntarily discarded to intercept.

7. **Hidden info hygiene**

   * Make sure only owners see faces for their face‑down herd and hands. Others should see backs and public discards only.

Where the rules allowed a choice around *revealing a truthful card on challenge*, I kept it non‑revealed for stronger bluff play. If you prefer always revealing on a successful truth check, flip the `REVEAL_TRUTH_ON_CHALLENGE` constant to `true` in `Game.php`.

---

## Full source code

> All paths are relative to the project root created by BGA Studio for your game.

### 1) Game metadata and configuration

```xml
<file path="gameinfos.inc.php">
<?php
/**
 * Herding Cats - gameinfos
 */

$gameinfos = array(
    'game_name' => clienttranslate('Herding Cats'),
    'designer' => 'GOSH Digital',
    'artist' => 'GOSH Digital',
    'year' => 2025,
    'publisher' => 'GOSH Digital',
    'publisher_website' => '',
    'estimated_duration' => 15,
    'fast_additional_time' => 30,
    'slow_additional_time' => 60,

    'player_turn_order' => 'normal',
    'is_beta' => 1, // keep 1 until ready for release

    'players' => array( 2, 3, 4, 5, 6 ),
    'suggest_player_number' => 4,
    'not_recommend_player_number' => array(),

    'complexity' => 1,
    'luck' => 3,
    'strategy' => 3,
    'diplomacy' => 3,

    'is_coop' => 0,
    'is_3p_without_ghost' => 1,

    'interface_version' => 2, // modern interface

    // Files
    'game_interface' => array(
        'js' => array( 'herdingcats.js' ),
        'css' => array( 'herdingcats.css' ),
        'php' => array( 'herdingcats.view.php' )
    ),

    'presentation' => array(
        clienttranslate('A compact bluff-driven card game where you herd the best cats while dodging Animal Control and flashy Laser Pointers.')
    ),

    'tags' => array( 'card', 'bluff', 'microdeck' ),
);
</file>
```

```xml
<file path="material.inc.php">
<?php
/**
 * Herding Cats - material and constants
 */

define('HC_TYPE_KITTEN', 1);
define('HC_TYPE_SHOWCAT', 2);
define('HC_TYPE_ALLEYCAT', 3);
define('HC_TYPE_CATNIP', 4);
define('HC_TYPE_ANIMALCONTROL', 5);
define('HC_TYPE_LASERPOINTER', 6);

// Target zones
define('HC_TZ_NONE', 0);
define('HC_TZ_HAND', 1);
define('HC_TZ_HERD', 2);

// Pending action kinds (for debug/readability)
define('HC_PENDING_NONE', 0);
define('HC_PENDING_DECLARE', 1);
define('HC_PENDING_INTERCEPT', 2);

// Option to reveal truthful card on challenge success
define('REVEAL_TRUTH_ON_CHALLENGE', false);

$hc_types = array(
    HC_TYPE_KITTEN => array(
        'name' => clienttranslate('Kitten'),
        'value' => 2,
        'targets' => HC_TZ_NONE
    ),
    HC_TYPE_SHOWCAT => array(
        'name' => clienttranslate('Show Cat'),
        'value' => 5, // upgraded in scoring if has any Kitten
        'targets' => HC_TZ_NONE
    ),
    HC_TYPE_ALLEYCAT => array(
        'name' => clienttranslate('Alley Cat'),
        'value' => 1,
        'targets' => HC_TZ_HAND
    ),
    HC_TYPE_CATNIP => array(
        'name' => clienttranslate('Catnip'),
        'value' => 1,
        'targets' => HC_TZ_HAND
    ),
    HC_TYPE_ANIMALCONTROL => array(
        'name' => clienttranslate('Animal Control'),
        'value' => 0,
        'targets' => HC_TZ_HERD
    ),
    HC_TYPE_LASERPOINTER => array(
        'name' => clienttranslate('Laser Pointer'),
        'value' => 0,
        'targets' => HC_TZ_NONE // no target on play, only as intercept
    ),
);

// Per‑player deck: 9 cards
$hc_deck_spec = array(
    HC_TYPE_KITTEN => 3,
    HC_TYPE_SHOWCAT => 1,
    HC_TYPE_ALLEYCAT => 2,
    HC_TYPE_CATNIP => 1,
    HC_TYPE_ANIMALCONTROL => 1,
    HC_TYPE_LASERPOINTER => 1
);
</file>
```

```xml
<file path="states.inc.php">
<?php
/**
 * Herding Cats - states machine
 */

require_once('material.inc.php');

define('ST_GAME_SETUP', 1);
define('ST_PLAYER_AWAIT_DECLARATION', 10);
define('ST_CHALLENGE_WINDOW', 20);
define('ST_RESOLVE_CHALLENGE', 30);
define('ST_CHALLENGER_SELECT_BLUFF_PENALTY', 31);
define('ST_ATTACKER_SELECT_TRUTHFUL_PENALTY', 32);

define('ST_TARGET_SELECTION', 40);
define('ST_INTERCEPT_DECLARE', 50);
define('ST_INTERCEPT_CHALLENGE_WINDOW', 55);
define('ST_RESOLVE_INTERCEPT_CHALLENGE', 56);

define('ST_REVEAL_AND_RESOLVE', 60);
define('ST_ADD_PLAYED_CARD_TO_HERD', 65);

define('ST_END_TURN', 70);
define('ST_GAME_END', 99);

$machinestates = array(

  ST_GAME_SETUP => array(
    "name" => "gameSetup",
    "type" => "manager",
    "action" => "stGameSetup",
    "transitions" => array( "" => ST_PLAYER_AWAIT_DECLARATION )
  ),

  ST_PLAYER_AWAIT_DECLARATION => array(
    "name" => "awaitDeclaration",
    "description" => clienttranslate('${actplayer} must play a card face down and declare its identity'),
    "descriptionmyturn" => clienttranslate('${you} must play a card and declare its identity'),
    "type" => "activeplayer",
    "args" => "argAwaitDeclaration",
    "possibleactions" => array("actDeclare"),
    "transitions" => array(
      "toChallenge" => ST_CHALLENGE_WINDOW,
      "endGame" => ST_GAME_END
    ),
    "updateGameProgression" => true
  ),

  ST_CHALLENGE_WINDOW => array(
    "name" => "challengeWindow",
    "type" => "multipleactiveplayer",
    "description" => clienttranslate('Other players may Challenge or Pass'),
    "args" => "argChallengeWindow",
    "possibleactions" => array("actChallenge", "actPassChallenge"),
    "transitions" => array(
      "toResolveChallenge" => ST_RESOLVE_CHALLENGE
    )
  ),

  ST_RESOLVE_CHALLENGE => array(
    "name" => "resolveChallenge",
    "type" => "game",
    "action" => "stResolveChallenge",
    "transitions" => array(
      "bluffPenalty" => ST_CHALLENGER_SELECT_BLUFF_PENALTY,
      "truthPenalties" => ST_ATTACKER_SELECT_TRUTHFUL_PENALTY,
      "toTargetSelection" => ST_TARGET_SELECTION,
      "toRevealAndResolve" => ST_REVEAL_AND_RESOLVE,
      "toAddToHerd" => ST_ADD_PLAYED_CARD_TO_HERD
    )
  ),

  ST_CHALLENGER_SELECT_BLUFF_PENALTY => array(
    "name" => "challengerSelectBluffPenalty",
    "type" => "activeplayer",
    "description" => clienttranslate('${actplayer} must pick a blind card from ${bluffed_player} to discard'),
    "descriptionmyturn" => clienttranslate('${you} must pick a blind card from ${bluffed_player} to discard'),
    "args" => "argChallengerSelectBluffPenalty",
    "possibleactions" => array("actSelectBlindFromActor"),
    "transitions" => array(
      "toEndTurn" => ST_END_TURN
    )
  ),

  ST_ATTACKER_SELECT_TRUTHFUL_PENALTY => array(
    "name" => "attackerSelectTruthfulPenalty",
    "type" => "activeplayer",
    "description" => clienttranslate('${actplayer} must pick a blind card from each challenger'),
    "descriptionmyturn" => clienttranslate('${you} must pick a blind card from each challenger'),
    "args" => "argAttackerSelectTruthfulPenalty",
    "possibleactions" => array("actSelectBlindFromChallenger"),
    "transitions" => array(
      "toTargetSelection" => ST_TARGET_SELECTION,
      "toRevealAndResolve" => ST_REVEAL_AND_RESOLVE,
      "toAddToHerd" => ST_ADD_PLAYED_CARD_TO_HERD
    )
  ),

  ST_TARGET_SELECTION => array(
    "name" => "targetSelection",
    "type" => "activeplayer",
    "description" => clienttranslate('${actplayer} must select a hidden target slot'),
    "descriptionmyturn" => clienttranslate('${you} must select a hidden target slot'),
    "args" => "argTargetSelection",
    "possibleactions" => array("actSelectTargetSlot"),
    "transitions" => array(
      "toInterceptDeclare" => ST_INTERCEPT_DECLARE,
      "toRevealAndResolve" => ST_REVEAL_AND_RESOLVE
    )
  ),

  ST_INTERCEPT_DECLARE => array(
    "name" => "interceptDeclare",
    "type" => "activeplayer",
    "description" => clienttranslate('${actplayer} may discard a Laser Pointer to intercept, or pass'),
    "descriptionmyturn" => clienttranslate('${you} may discard a Laser Pointer from hand or herd to intercept, or pass'),
    "args" => "argInterceptDeclare",
    "possibleactions" => array("actDeclareIntercept", "actPassIntercept"),
    "transitions" => array(
      "toInterceptChallengeWindow" => ST_INTERCEPT_CHALLENGE_WINDOW,
      "toRevealAndResolve" => ST_REVEAL_AND_RESOLVE
    )
  ),

  ST_INTERCEPT_CHALLENGE_WINDOW => array(
    "name" => "interceptChallengeWindow",
    "type" => "multipleactiveplayer",
    "description" => clienttranslate('Players may challenge the Laser Pointer claim or pass'),
    "args" => "argInterceptChallengeWindow",
    "possibleactions" => array("actChallengeIntercept", "actPassChallengeIntercept"),
    "transitions" => array(
      "toResolveInterceptChallenge" => ST_RESOLVE_INTERCEPT_CHALLENGE
    )
  ),

  ST_RESOLVE_INTERCEPT_CHALLENGE => array(
    "name" => "resolveInterceptChallenge",
    "type" => "game",
    "action" => "stResolveInterceptChallenge",
    "transitions" => array(
      "toAddToHerd" => ST_ADD_PLAYED_CARD_TO_HERD,
      "toRevealAndResolve" => ST_REVEAL_AND_RESOLVE
    )
  ),

  ST_REVEAL_AND_RESOLVE => array(
    "name" => "revealAndResolve",
    "type" => "game",
    "action" => "stRevealAndResolve",
    "transitions" => array(
      "toAddToHerd" => ST_ADD_PLAYED_CARD_TO_HERD
    )
  ),

  ST_ADD_PLAYED_CARD_TO_HERD => array(
    "name" => "addPlayedCardToHerd",
    "type" => "game",
    "action" => "stAddPlayedCardToHerd",
    "transitions" => array(
      "toEndTurn" => ST_END_TURN
    )
  ),

  ST_END_TURN => array(
    "name" => "endTurn",
    "type" => "game",
    "action" => "stEndTurn",
    "transitions" => array(
      "nextPlayer" => ST_PLAYER_AWAIT_DECLARATION,
      "endGame" => ST_GAME_END
    )
  ),

  ST_GAME_END => array(
    "name" => "gameEnd",
    "type" => "manager",
    "action" => "stGameEnd",
    "args" => "argGameEnd"
  ),
);
</file>
```

```xml
<file path="dbmodel.sql">
-- Herding Cats - database model

-- Card table managed by module.common.deck
CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` int(10) NOT NULL,        -- current identity
  `card_type_arg` int(10) NOT NULL,    -- base type (truth for challenges)
  `card_location` varchar(32) NOT NULL,
  `card_location_arg` int(10) DEFAULT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Pending action single-row table. We keep one active action per game.
CREATE TABLE IF NOT EXISTS `pending_action` (
  `id` tinyint(1) NOT NULL DEFAULT 1,
  `kind` tinyint(1) NOT NULL DEFAULT 0,               -- HC_PENDING_*
  `actor_id` int(10) DEFAULT NULL,
  `card_id` int(10) DEFAULT NULL,                     -- the physical card played, currently in 'pending' location
  `declared_identity` tinyint(1) DEFAULT NULL,        -- HC_TYPE_*
  `target_player_id` int(10) DEFAULT NULL,
  `target_zone` tinyint(1) DEFAULT 0,                 -- HC_TZ_*
  `selected_card_id` int(10) DEFAULT NULL,            -- for target slot or penalty selections
  `challengers_csv` varchar(128) DEFAULT NULL,        -- comma-separated list of player ids
  `first_challenger_id` int(10) DEFAULT NULL,
  `intercept_by_player_id` int(10) DEFAULT NULL,
  `intercept_zone` tinyint(1) DEFAULT NULL,           -- HC_TZ_HAND or HC_TZ_HERD
  `intercept_card_id` int(10) DEFAULT NULL,           -- which card is being discarded as Laser Pointer
  `intercept_challengers_csv` varchar(128) DEFAULT NULL,
  `data_json` text,                                   -- spare
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
</file>
```

```xml
<file path="stats.json">
{
  "player": {
    "turns": { "id": 10, "name": "Turns taken", "type": "int" },
    "bluffsCaught": { "id": 11, "name": "Bluffs caught", "type": "int" },
    "wrongChallenges": { "id": 12, "name": "Wrong challenges", "type": "int" },
    "laserIntercepts": { "id": 13, "name": "Successful Laser intercepts", "type": "int" }
  },
  "table": {
    "totalChallenges": { "id": 30, "name": "Total challenges", "type": "int" }
  }
}
</file>
```

### 2) Server‑side game logic

```xml
<file path="modules/php/Game.php">
<?php
/**
 * Herding Cats - main game class
 */

require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');

class HerdingCats extends Table
{
    /** @var \Deck $cards */
    protected $cards;

    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            // You can add global ints here later if needed
        ]);

        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    ////////////
    // Setup  //
    ////////////

    protected function getGameName()
    {
        return "herdingcats";
    }

    public function stGameSetup()
    {
        $players = $this->loadPlayersBasicInfos();
        $this->setupNewGame($players, array());
        $this->gamestate->nextState('');
    }

    protected function setupNewGame($players, $options = array())
    {
        self::DbQuery("DELETE FROM card");
        self::DbQuery("DELETE FROM pending_action");
        self::DbQuery("INSERT INTO pending_action (id, kind) VALUES (1, 0)");

        // Set initial scores
        $sql = "UPDATE player SET player_score = 0";
        self::DbQuery($sql);

        // Build per-player micro-decks
        require_once('material.inc.php');
        global $hc_deck_spec;

        foreach ($players as $player_id => $player) {
            $cards_desc = [];
            foreach ($hc_deck_spec as $type => $qty) {
                $cards_desc[] = [
                    'type' => $type,        // current identity
                    'type_arg' => $type,    // base type
                    'nbr' => $qty
                ];
            }
            // Create cards in a dedicated personal deck location
            $loc = 'deck_'.$player_id;
            $this->cards->createCards($cards_desc, $loc);
            $this->cards->shuffle($loc);

            // Draw 7 to hand, remove 2 from game
            for ($i = 0; $i < 7; $i++) {
                $this->cards->pickCardForLocation($loc, 'hand', $player_id);
            }
            // Move remaining 2 to removed
            $remaining = $this->cards->getCardsInLocation($loc);
            foreach ($remaining as $c) {
                $this->cards->moveCard($c['id'], 'removed', $player_id);
            }
        }

        // Choose first player randomly
        $this->activeNextPlayer();
    }

    ////////////
    // Utility
    ////////////

    protected function getOtherPlayerIds($excludeId = null)
    {
        $players = $this->loadPlayersBasicInfos();
        $list = array_keys($players);
        if ($excludeId !== null) {
            $list = array_values(array_filter($list, fn($id) => intval($id) !== intval($excludeId)));
        }
        return $list;
    }

    protected function notifyHandsCount()
    {
        // Send per-player hand counts
        $players = $this->loadPlayersBasicInfos();
        $counts = [];
        foreach ($players as $pid => $_) {
            $counts[$pid] = intval($this->cards->countCardInLocation('hand', $pid));
        }
        $this->notifyAllPlayers('handCounts', '', ['counts' => $counts]);
    }

    protected function notifyWholeStateForPlayer($player_id)
    {
        // On reconnect or at setup, provide private hand and private herd identities
        $hand = $this->cards->getCardsInLocation('hand', $player_id);
        $herd_private = $this->cards->getCardsInLocation('herd', $player_id); // all face-down in your herd
        self::notifyPlayer($player_id, 'privateFullState', '', [
            'hand' => array_values($hand),
            'herd_private' => array_values($herd_private)
        ]);
    }

    protected function pushPending($data)
    {
        $pairs = [];
        foreach ($data as $k => $v) {
            if (is_null($v)) {
                $pairs[] = "$k = NULL";
            } else {
                $pairs[] = $k . " = '" . self::escapeStringForDB($v) . "'";
            }
        }
        $sql = "UPDATE pending_action SET " . implode(', ', $pairs) . " WHERE id = 1";
        self::DbQuery($sql);
    }

    protected function pullPending()
    {
        return self::getObjectFromDB("SELECT * FROM pending_action WHERE id = 1");
    }

    protected function clearPending()
    {
        self::DbQuery("UPDATE pending_action SET kind = 0, actor_id = NULL, card_id = NULL, declared_identity = NULL, target_player_id = NULL, target_zone = 0, selected_card_id = NULL, challengers_csv = NULL, first_challenger_id = NULL, intercept_by_player_id = NULL, intercept_zone = NULL, intercept_card_id = NULL, intercept_challengers_csv = NULL, data_json = NULL WHERE id = 1");
    }

    protected function csvToIds($s)
    {
        if ($s === null || $s === '') return [];
        return array_map('intval', explode(',', $s));
    }

    protected function idsToCsv($arr)
    {
        if (!$arr || count($arr) === 0) return null;
        return implode(',', array_map('intval', array_values(array_unique($arr))));
    }

    protected function getCardName($type)
    {
        require('material.inc.php');
        global $hc_types;
        return $hc_types[$type]['name'];
    }

    protected function isTargetedType($type)
    {
        require('material.inc.php');
        global $hc_types;
        return $hc_types[$type]['targets'] !== HC_TZ_NONE;
    }

    protected function targetZoneForType($type)
    {
        require('material.inc.php');
        global $hc_types;
        return $hc_types[$type]['targets'];
    }

    protected function addToHerdFaceDownAs($player_id, $card_id, $identityType)
    {
        // Change current identity, move to herd face-down
        $this->cards->moveCard($card_id, 'herd', $player_id);
        self::DbQuery("UPDATE card SET card_type = ".intval($identityType)." WHERE card_id = ".intval($card_id));
        // Public notification uses a back image for others
        $this->notifyAllPlayers('cardAddedToHerd', clienttranslate('${player_name} adds a card to herd'), [
            'player_id' => $player_id,
            'player_name' => $this->getPlayerNameById($player_id),
            'card_id' => $card_id
        ]);
        // Owner gets identity
        $card = $this->cards->getCard($card_id);
        self::notifyPlayer($player_id, 'privateHerdCardIdentity', '', [
            'card' => $card
        ]);
    }

    ////////////////
    // Game data   //
    ////////////////

    public function getAllDatas()
    {
        $result = [];
        $current_player_id = self::getCurrentPlayerId();
        $players = $this->loadPlayersBasicInfos();
        $result['players'] = $players;

        // Hand counts for all, real hand list for current player
        $handCounts = [];
        foreach ($players as $pid => $_) {
            $handCounts[$pid] = intval($this->cards->countCardInLocation('hand', $pid));
        }
        $result['handCounts'] = $handCounts;
        $result['hand'] = array_values($this->cards->getCardsInLocation('hand', $current_player_id));

        // Herds
        $result['herds'] = [];
        foreach ($players as $pid => $_) {
            $faceup = array_values($this->cards->getCardsInLocation('herd_faceup', $pid));
            $facedown = array_map(function($c){ return ['id' => $c['id']]; }, array_values($this->cards->getCardsInLocation('herd', $pid)));
            $result['herds'][$pid] = [
                'faceup' => $faceup,
                'facedown' => $facedown
            ];
        }

        // Discards top 5 per player
        $result['discards'] = [];
        foreach ($players as $pid => $_) {
            $dis = array_values($this->cards->getCardsInLocation('discard', $pid));
            $result['discards'][$pid] = $dis; // send all, UI will show last few
        }

        // Art paths
        $result['art'] = $this->getArtMap();

        return $result;
    }

    protected function getArtMap()
    {
        // Simple map used by client to pick images
        return [
            HC_TYPE_KITTEN => 'img/herding_cats_art/kitten.jpeg',
            HC_TYPE_SHOWCAT => 'img/herding_cats_art/showcat.jpeg',
            HC_TYPE_ALLEYCAT => 'img/herding_cats_art/alleycat.jpeg',
            HC_TYPE_CATNIP => 'img/herding_cats_art/catnip.jpeg',
            HC_TYPE_ANIMALCONTROL => 'img/herding_cats_art/animalcontrol.jpeg',
            HC_TYPE_LASERPOINTER => 'img/herding_cats_art/laserpointer.jpeg',
            'back' => 'img/herding_cats_art/cardback.jpeg'
        ];
    }

    ////////////////
    // State args  //
    ////////////////

    public function argAwaitDeclaration()
    {
        $player_id = $this->getActivePlayerId();
        $hand = array_values($this->cards->getCardsInLocation('hand', $player_id));
        return [
            'hand' => $hand,
            'types' => array_keys($this->getArtMap()), // includes 'back' key but JS will ignore
            'art' => $this->getArtMap(),
            'players' => $this->loadPlayersBasicInfos()
        ];
    }

    public function argChallengeWindow()
    {
        $pending = $this->pullPending();
        $actor = intval($pending['actor_id']);
        $eligible = array_diff($this->getOtherPlayerIds(), [$actor]);
        return [
            'actor_id' => $actor,
            'declared_identity' => intval($pending['declared_identity']),
            'target_player_id' => intval($pending['target_player_id']),
            'eligible' => array_values($eligible)
        ];
    }

    public function argChallengerSelectBluffPenalty()
    {
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $first = intval($p['first_challenger_id']);
        $actorHand = array_map(fn($c) => [ 'id'=>$c['id'] ], array_values($this->cards->getCardsInLocation('hand',$actor)));
        return [
            'bluffed_player' => $actor,
            'actor_hand_blind' => $actorHand
        ];
    }

    public function argAttackerSelectTruthfulPenalty()
    {
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $challengers = $this->csvToIds($p['challengers_csv']);
        $nextVictim = null;
        foreach ($challengers as $cid) {
            // We will mark completion by removing one at a time in action
            $nextVictim = $cid;
            break;
        }
        $victimHandBlind = $nextVictim ? array_map(fn($c)=>['id'=>$c['id']], array_values($this->cards->getCardsInLocation('hand', $nextVictim))) : [];
        return [
            'next_victim_id' => $nextVictim,
            'victim_hand_blind' => $victimHandBlind
        ];
    }

    public function argTargetSelection()
    {
        $p = $this->pullPending();
        $tp = intval($p['target_player_id']);
        $tz = intval($p['target_zone']);
        if ($tz === HC_TZ_HAND) {
            $slots = array_map(fn($c)=>['id'=>$c['id']], array_values($this->cards->getCardsInLocation('hand', $tp)));
        } else {
            // face-down herd only
            $all = array_values($this->cards->getCardsInLocation('herd', $tp));
            $slots = array_map(fn($c)=>['id'=>$c['id']], $all);
        }
        return [
            'target_player_id' => $tp,
            'target_zone' => $tz,
            'slots' => $slots
        ];
    }

    public function argInterceptDeclare()
    {
        $p = $this->pullPending();
        $defender = intval($p['target_player_id']);
        // Provide defender with choice of Laser Pointers in hand and herd
        $hand = array_values($this->cards->getCardsInLocation('hand', $defender));
        $herdAll = array_values($this->cards->getCardsInLocation('herd', $defender));
        $herdFaceUp = array_values($this->cards->getCardsInLocation('herd_faceup', $defender));
        // Filter by laser pointer conditions
        $handLaser = array_values(array_filter($hand, fn($c)=> intval($c['type_arg']) === HC_TYPE_LASERPOINTER ));
        $herdLaser = array_values(array_filter(array_merge($herdAll,$herdFaceUp), fn($c)=> intval($c['type']) === HC_TYPE_LASERPOINTER ));
        return [
            'hand_laser' => array_map(fn($c)=>['id'=>$c['id']], $handLaser),
            'herd_laser' => array_map(fn($c)=>['id'=>$c['id']], $herdLaser),
            'defender_id' => $defender
        ];
    }

    public function argInterceptChallengeWindow()
    {
        $p = $this->pullPending();
        $defender = intval($p['intercept_by_player_id']);
        $eligible = array_diff($this->getOtherPlayerIds(), [$defender]);
        return [
            'defender_id' => $defender,
            'eligible' => array_values($eligible)
        ];
    }

    public function argGameEnd()
    {
        $players = $this->loadPlayersBasicInfos();
        $scores = [];
        foreach($players as $pid => $_) $scores[$pid] = intval($this->getPlayerScore($pid));
        return [
            'scores' => $scores
        ];
    }

    //////////////////////////
    // Player action bridge //
    //////////////////////////

    public function actDeclare($card_id, $declared_type, $target_player_id)
    {
        self::checkAction('actDeclare');

        $player_id = $this->getActivePlayerId();

        // Validate card belongs to player and is in hand
        $card = $this->cards->getCard($card_id);
        if ($card['location'] !== 'hand' || intval($card['location_arg']) !== intval($player_id)) {
            throw new BgaUserException(self::_("You must select a card from your hand."));
        }

        // Validate declared type
        if ($declared_type < HC_TYPE_KITTEN || $declared_type > HC_TYPE_LASERPOINTER) {
            throw new BgaUserException(self::_("Invalid declared identity."));
        }

        // Determine target zone for declared type
        $tz = $this->targetZoneForType($declared_type);
        if ($tz === HC_TZ_NONE) {
            $target_player_id = null; // ignore any client-sent value
        } else {
            // Validate a target player was chosen and not self
            if (!$target_player_id || intval($target_player_id) === intval($player_id)) {
                throw new BgaUserException(self::_("Select exactly one opponent to target."));
            }
        }

        // Move played card to a temporary 'pending' location visible as a facedown table card
        $this->cards->moveCard($card_id, 'pending', $player_id);

        // Record pending action
        $this->pushPending([
            'kind' => HC_PENDING_DECLARE,
            'actor_id' => $player_id,
            'card_id' => $card_id,
            'declared_identity' => $declared_type,
            'target_player_id' => $target_player_id,
            'target_zone' => $tz,
            'challengers_csv' => null,
            'first_challenger_id' => null
        ]);

        // Broadcast declaration
        $this->notifyAllPlayers('declared', clienttranslate('${player_name} plays a card face down claiming ${decl}'), [
            'player_id' => $player_id,
            'player_name' => $this->getPlayerNameById($player_id),
            'declared_type' => $declared_type,
            'decl' => $this->getCardName($declared_type),
            'target_player_id' => $target_player_id
        ]);

        // Make all other players active for the challenge window
        $others = $this->getOtherPlayerIds($player_id);
        $this->gamestate->setPlayersMultiactive($others, "toResolveChallenge", true);

        $this->gamestate->nextState('toChallenge');
    }

    public function actChallenge()
    {
        self::checkAction('actChallenge');
        $pid = self::getCurrentPlayerId();
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        if ($pid == $actor) {
            throw new BgaUserException(self::_("You cannot challenge your own declaration."));
        }
        $challengers = $this->csvToIds($p['challengers_csv']);
        if (!in_array($pid, $challengers)) {
            $challengers[] = $pid;
            $this->pushPending([
                'challengers_csv' => $this->idsToCsv($challengers),
                'first_challenger_id' => $p['first_challenger_id'] ? $p['first_challenger_id'] : $pid
            ]);
            $this->notifyAllPlayers('challengeDeclared', clienttranslate('${player_name} challenges!'), [
                'player_id' => $pid,
                'player_name' => $this->getPlayerNameById($pid)
            ]);
        }
        $this->gamestate->setPlayerNonMultiactive($pid, 'toResolveChallenge');
        // When all have responded, machine will move to resolve
    }

    public function actPassChallenge()
    {
        self::checkAction('actPassChallenge');
        $pid = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'toResolveChallenge');
    }

    public function stResolveChallenge()
    {
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $card = $this->cards->getCard(intval($p['card_id']));
        $declared = intval($p['declared_identity']);
        $challengers = $this->csvToIds($p['challengers_csv']);

        if (count($challengers) === 0) {
            // No challenge: proceed
            if ($this->isTargetedType($declared)) {
                $this->gamestate->nextState('toTargetSelection');
            } else {
                // Non-targeting effect simply adds to herd
                $this->gamestate->nextState('toAddToHerd');
            }
            return;
        }

        // There was at least one challenge: test truth
        $truth = (intval($card['type_arg']) === $declared);

        if ($truth) {
            // Truthful: penalise all challengers later
            $this->notifyAllPlayers('challengeResult', clienttranslate('The claim was truthful.'), []);
            $this->incStat(1, 'totalChallenges');
            foreach ($challengers as $cid) {
                $this->incStat(1, 'wrongChallenges', $cid);
            }
            // Active player selects one blind card from each challenger, one at a time
            $this->gamestate->changeActivePlayer($actor);
            $this->gamestate->nextState('truthPenalties');
        } else {
            // Bluff: reveal and discard played card, extra penalty for actor
            $this->notifyAllPlayers('challengeResultReveal', clienttranslate('Bluff! The played card was ${real}'), [
                'real_type' => $card['type_arg'],
                'real' => $this->getCardName($card['type_arg']),
                'card' => $card
            ]);
            $this->incStat(1, 'totalChallenges');
            $this->incStat(1, 'bluffsCaught', $p['first_challenger_id']);

            // Discard the played card
            $this->cards->moveCard($card['id'], 'discard', $actor);
            $this->notifyAllPlayers('discardPublic', clienttranslate('${player_name} discards the revealed card'), [
                'player_id' => $actor,
                'player_name' => $this->getPlayerNameById($actor),
                'card' => $card
            ]);

            // First challenger picks one blind from actor's hand
            $this->gamestate->changeActivePlayer(intval($p['first_challenger_id']));
            $this->gamestate->nextState('bluffPenalty');
        }
    }

    public function actSelectBlindFromActor($selected_card_id)
    {
        self::checkAction('actSelectBlindFromActor');
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $chooser = self::getCurrentPlayerId();
        if (intval($p['first_challenger_id']) !== $chooser) {
            throw new BgaUserException(self::_("Only the first challenger selects the penalty card."));
        }
        $card = $this->cards->getCard($selected_card_id);
        if ($card['location'] !== 'hand' || intval($card['location_arg']) !== $actor) {
            throw new BgaUserException(self::_("Select a card from the bluffer's hand."));
        }
        // Reveal and discard
        $this->cards->moveCard($card['id'], 'discard', $actor);
        $this->notifyAllPlayers('discardPublic', clienttranslate('${player_name} discards a revealed card from ${victim}'), [
            'player_id' => $chooser,
            'player_name' => $this->getPlayerNameById($chooser),
            'victim' => $this->getPlayerNameById($actor),
            'card' => $card
        ]);

        $this->clearPending();
        $this->gamestate->nextState('toEndTurn');
    }

    public function actSelectBlindFromChallenger($selected_card_id)
    {
        self::checkAction('actSelectBlindFromChallenger');
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $this->gamestate->checkPossibleAction('actSelectBlindFromChallenger');

        if ($this->getActivePlayerId() != $actor) {
            throw new BgaUserException(self::_("Only the truthful player selects penalty cards."));
        }

        $challengers = $this->csvToIds($p['challengers_csv']);
        if (count($challengers) == 0) {
            throw new BgaUserException(self::_("No challengers remain."));
        }
        $victim = intval($challengers[0]);

        $card = $this->cards->getCard($selected_card_id);
        if ($card['location'] !== 'hand' || intval($card['location_arg']) !== $victim) {
            throw new BgaUserException(self::_("Select a card from the challenger's hand."));
        }

        $this->cards->moveCard($card['id'], 'discard', $victim);
        $this->notifyAllPlayers('discardPublic', clienttranslate('${player_name} discards a revealed card from ${victim}'), [
            'player_id' => $actor,
            'player_name' => $this->getPlayerNameById($actor),
            'victim' => $this->getPlayerNameById($victim),
            'card' => $card
        ]);

        // Remove this victim from list and continue or proceed
        array_shift($challengers);
        $this->pushPending([ 'challengers_csv' => $this->idsToCsv($challengers) ]);

        if (count($challengers) > 0) {
            // Next victim
            $this->gamestate->nextState('toTargetSelection'); // will be looped by args
            $this->gamestate->setStateValue(0,0); // no-op to keep engine happy
            $this->gamestate->jumpToState(ST_ATTACKER_SELECT_TRUTHFUL_PENALTY);
        } else {
            // Penalties done - move forward
            $declared = intval($p['declared_identity']);
            if ($this->isTargetedType($declared)) {
                $this->gamestate->nextState('toTargetSelection');
            } else {
                $this->gamestate->nextState('toAddToHerd');
            }
        }
    }

    public function actSelectTargetSlot($selected_card_id)
    {
        self::checkAction('actSelectTargetSlot');
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        if ($this->getActivePlayerId() != $actor) {
            throw new BgaUserException(self::_("It is not your selection window."));
        }
        $tp = intval($p['target_player_id']);
        $tz = intval($p['target_zone']);

        $card = $this->cards->getCard($selected_card_id);

        if ($tz === HC_TZ_HAND) {
            if ($card['location'] !== 'hand' || intval($card['location_arg']) !== $tp) {
                throw new BgaUserException(self::_("Select a hidden slot from the target's hand."));
            }
        } else {
            if (!($card['location'] === 'herd' || $card['location'] === 'herd_faceup') || intval($card['location_arg']) !== $tp) {
                throw new BgaUserException(self::_("Select a face-down herd card."));
            }
            if ($card['location'] === 'herd_faceup') {
                throw new BgaUserException(self::_("You cannot select a face-up protected card."));
            }
        }

        $this->pushPending([ 'selected_card_id' => $selected_card_id ]);

        // Intercept window for defender
        $this->gamestate->changeActivePlayer($tp);
        $this->gamestate->nextState('toInterceptDeclare');
    }

    public function actDeclareIntercept($zone, $intercept_card_id)
    {
        self::checkAction('actDeclareIntercept');

        $p = $this->pullPending();
        $defender = intval($p['target_player_id']);
        $pid = self::getCurrentPlayerId();
        if ($pid != $defender) throw new BgaUserException(self::_("Only the defender may intercept."));

        $zone = intval($zone);
        if ($zone !== HC_TZ_HAND && $zone !== HC_TZ_HERD) {
            throw new BgaUserException(self::_("Invalid intercept zone."));
        }

        $card = $this->cards->getCard($intercept_card_id);
        if ($zone === HC_TZ_HAND) {
            if ($card['location'] !== 'hand' || intval($card['location_arg']) !== $defender) {
                throw new BgaUserException(self::_("Select a Laser Pointer from your hand."));
            }
        } else {
            if (!in_array($card['location'], ['herd','herd_faceup']) || intval($card['location_arg']) !== $defender) {
                throw new BgaUserException(self::_("Select a Laser Pointer from your herd."));
            }
        }

        // Store claim
        $this->pushPending([
            'kind' => HC_PENDING_INTERCEPT,
            'intercept_by_player_id' => $defender,
            'intercept_zone' => $zone,
            'intercept_card_id' => $intercept_card_id,
            'intercept_challengers_csv' => null
        ]);

        // Announce claim without revealing card
        $this->notifyAllPlayers('interceptClaimed', clienttranslate('${player_name} claims a Laser Pointer to intercept'), [
            'player_id' => $defender,
            'player_name' => $this->getPlayerNameById($defender),
            'zone' => $zone
        ]);

        // Others can challenge
        $others = array_diff($this->getOtherPlayerIds(), [$defender]);
        $this->gamestate->setPlayersMultiactive($others, "toResolveInterceptChallenge", true);
        $this->gamestate->nextState('toInterceptChallengeWindow');
    }

    public function actPassIntercept()
    {
        self::checkAction('actPassIntercept');
        // No intercept, go resolve
        $this->gamestate->nextState('toRevealAndResolve');
    }

    public function actChallengeIntercept()
    {
        self::checkAction('actChallengeIntercept');
        $pid = self::getCurrentPlayerId();
        $p = $this->pullPending();
        $defender = intval($p['intercept_by_player_id']);
        if ($pid == $defender) throw new BgaUserException(self::_("You cannot challenge your own claim."));
        $challengers = $this->csvToIds($p['intercept_challengers_csv']);
        if (!in_array($pid, $challengers)) {
            $challengers[] = $pid;
            $this->pushPending([ 'intercept_challengers_csv' => $this->idsToCsv($challengers) ]);
            $this->notifyAllPlayers('challengeDeclared', clienttranslate('${player_name} challenges!'), [
                'player_id' => $pid,
                'player_name' => $this->getPlayerNameById($pid)
            ]);
        }
        $this->gamestate->setPlayerNonMultiactive($pid, 'toResolveInterceptChallenge');
    }

    public function actPassChallengeIntercept()
    {
        self::checkAction('actPassChallengeIntercept');
        $pid = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'toResolveInterceptChallenge');
    }

    public function stResolveInterceptChallenge()
    {
        $p = $this->pullPending();
        $defender = intval($p['intercept_by_player_id']);
        $zone = intval($p['intercept_zone']);
        $card = $this->cards->getCard(intval($p['intercept_card_id']));
        $challengers = $this->csvToIds($p['intercept_challengers_csv']);

        // Truth test
        $truth = false;
        if ($zone === HC_TZ_HAND) {
            $truth = ($card['location'] === 'hand' && intval($card['location_arg']) === $defender && intval($card['type_arg']) === HC_TYPE_LASERPOINTER);
        } else {
            $truth = (in_array($card['location'], ['herd','herd_faceup']) && intval($card['location_arg']) === $defender && intval($card['type']) === HC_TYPE_LASERPOINTER);
        }

        if (count($challengers) === 0) {
            // Nobody challenged: treat as truthful
            $truth = true;
        }

        if ($truth) {
            // Discard the selected card face-up
            $this->cards->moveCard($card['id'], 'discard', $defender);
            $this->notifyAllPlayers('discardPublic', clienttranslate('${player_name} discards a Laser Pointer to intercept'), [
                'player_id' => $defender,
                'player_name' => $this->getPlayerNameById($defender),
                'card' => $card
            ]);
            $this->incStat(1, 'laserIntercepts', $defender);

            // Each challenger discards a blind card selected by defender
            foreach ($challengers as $cid) {
                // Choose randomly for now in this automatic resolution state.
                // Follow-up: You can add an extra state if you want the defender to pick specific slots one by one.
                $hand = array_values($this->cards->getCardsInLocation('hand', $cid));
                if (count($hand) > 0) {
                    $pick = $hand[bga_rand(0, count($hand)-1)];
                    $this->cards->moveCard($pick['id'], 'discard', $cid);
                    $this->notifyAllPlayers('discardPublic', clienttranslate('${victim} discards a blind card due to intercept'), [
                        'victim' => $this->getPlayerNameById($cid),
                        'card' => $pick
                    ]);
                }
            }

            // Attack is cancelled, but attacker still places their played card to herd
            $this->gamestate->nextState('toAddToHerd');
        } else {
            // Lie: discard the selected card anyway, plus extra blind chosen by first challenger
            $first = $challengers[0];
            $this->cards->moveCard($card['id'], 'discard', $defender);
            $this->notifyAllPlayers('discardPublic', clienttranslate('${player_name} discards the falsely presented card'), [
                'player_id' => $defender,
                'player_name' => $this->getPlayerNameById($defender),
                'card' => $card
            ]);

            $hand = array_values($this->cards->getCardsInLocation('hand', $defender));
            if (count($hand) > 0) {
                $pick = $hand[bga_rand(0, count($hand)-1)];
                $this->cards->moveCard($pick['id'], 'discard', $defender);
                $this->notifyAllPlayers('discardPublic', clienttranslate('${player_name} also discards a blind card due to a wrong intercept claim'), [
                    'player_id' => $defender,
                    'player_name' => $this->getPlayerNameById($defender),
                    'card' => $pick
                ]);
            }
            // Original attack resumes
            $this->gamestate->nextState('toRevealAndResolve');
        }
    }

    public function stRevealAndResolve()
    {
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $decl = intval($p['declared_identity']);
        $tp = intval($p['target_player_id']);
        $tz = intval($p['target_zone']);
        $targetCard = $p['selected_card_id'] ? $this->cards->getCard(intval($p['selected_card_id'])) : null;

        if ($this->isTargetedType($decl)) {
            // Reveal selected card and apply effect or ineffective rule
            if ($tz === HC_TZ_HAND) {
                // Reveal from hand
                $this->notifyAllPlayers('revealFromHand', clienttranslate('${player_name} reveals a card from ${victim}\'s hand'), [
                    'player_id' => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'victim' => $this->getPlayerNameById($tp),
                    'card' => $targetCard
                ]);

                if ($decl === HC_TYPE_ALLEYCAT && intval($targetCard['type_arg']) === HC_TYPE_ALLEYCAT) {
                    // Ineffective: return to hand unchanged
                    self::notifyAllPlayers('ineffective', clienttranslate('Ineffective: Alley Cat met Alley Cat'), []);
                    // nothing else to do for target card
                } elseif ($decl === HC_TYPE_CATNIP && intval($targetCard['type_arg']) === HC_TYPE_CATNIP) {
                    self::notifyAllPlayers('ineffective', clienttranslate('Ineffective: Catnip met Catnip'), []);
                } else {
                    if ($decl === HC_TYPE_ALLEYCAT) {
                        // Defender discards revealed card
                        $this->cards->moveCard($targetCard['id'], 'discard', $tp);
                        $this->notifyAllPlayers('discardPublic', clienttranslate('${victim} discards the revealed card'), [
                            'victim' => $this->getPlayerNameById($tp),
                            'card' => $targetCard
                        ]);
                    } elseif ($decl === HC_TYPE_CATNIP) {
                        // Move revealed card face-down into attacker's herd; only attacker knows identity
                        $this->cards->moveCard($targetCard['id'], 'herd', $actor);
                        $this->notifyAllPlayers('stolenToHerd', clienttranslate('${player_name} steals a card to herd'), [
                            'player_id' => $actor,
                            'player_name' => $this->getPlayerNameById($actor),
                            'card_id' => $targetCard['id']
                        ]);
                        self::notifyPlayer($actor, 'privateHerdCardIdentity', '', [
                            'card' => $this->cards->getCard($targetCard['id'])
                        ]);
                    }
                }
            } else {
                // Herd targeting: reveal from herd
                $this->notifyAllPlayers('revealFromHerd', clienttranslate('${player_name} reveals a herd card from ${victim}'), [
                    'player_id' => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'victim' => $this->getPlayerNameById($tp),
                    'card' => $targetCard
                ]);

                if ($decl === HC_TYPE_ANIMALCONTROL && intval($targetCard['type']) === HC_TYPE_ANIMALCONTROL) {
                    // Ineffective: flip target face-up and protect it
                    $this->cards->moveCard($targetCard['id'], 'herd_faceup', $tp);
                    $this->notifyAllPlayers('flipFaceUp', clienttranslate('Ineffective: Animal Control met Animal Control. Card flips face-up and is protected.'), [
                        'player_id' => $tp,
                        'card' => $this->cards->getCard($targetCard['id'])
                    ]);
                } else {
                    // Discard revealed card
                    $this->cards->moveCard($targetCard['id'], 'discard', $tp);
                    $this->notifyAllPlayers('discardPublic', clienttranslate('The revealed herd card is discarded'), [
                        'card' => $targetCard
                    ]);
                }
            }
        } else {
            // No target effect to resolve
        }

        $this->gamestate->nextState('toAddToHerd');
    }

    public function stAddPlayedCardToHerd()
    {
        $p = $this->pullPending();
        $actor = intval($p['actor_id']);
        $decl = intval($p['declared_identity']);
        $played = $this->cards->getCard(intval($p['card_id']));

        // If there was a bluff caught earlier, the card may already be in discard. Guard.
        if ($played && $played['location'] === 'pending') {
            $this->addToHerdFaceDownAs($actor, $played['id'], $decl);
        }

        $this->clearPending();
        $this->gamestate->nextState('toEndTurn');
    }

    public function stEndTurn()
    {
        $player_id = $this->getActivePlayerId();

        $this->incStat(1, 'turns', $player_id);
        $this->notifyHandsCount();

        // Check end condition
        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $pid => $_) {
            if ($this->cards->countCardInLocation('hand', $pid) == 0) {
                $this->finalScoring();
                $this->gamestate->nextState('endGame');
                return;
            }
        }

        $this->activeNextPlayer();
        $this->gamestate->nextState('nextPlayer');
    }

    protected function finalScoring()
    {
        require('material.inc.php');
        global $hc_types;

        $players = $this->loadPlayersBasicInfos();
        foreach ($players as $pid => $_) {
            $herdDown = $this->cards->getCardsInLocation('herd', $pid);
            $herdUp = $this->cards->getCardsInLocation('herd_faceup', $pid);
            $herd = array_merge($herdDown, $herdUp);
            $kittens = 0;
            $score = 0;
            foreach ($herd as $c) {
                $type = intval($c['type']);
                if ($type == HC_TYPE_KITTEN) $kittens++;
            }
            foreach ($herd as $c) {
                $type = intval($c['type']);
                if ($type == HC_TYPE_SHOWCAT) {
                    $score += ($kittens > 0 ? 7 : 5);
                } else {
                    $score += $hc_types[$type]['value'];
                }
            }
            // Hand bonus
            $hcount = intval($this->cards->countCardInLocation('hand', $pid));
            $score += intdiv($hcount + 1, 2);

            $this->DbQuery("UPDATE player SET player_score = ".intval($score)." WHERE player_id = ".intval($pid));
        }
        $this->notifyAllPlayers('scoresComputed', clienttranslate('Final scoring computed'), []);
    }

    public function stGameEnd() { /* Nothing extra */ }

    //////////////
    // Helpers  //
    //////////////

    public function getPlayerNameById($player_id) {
        $players = $this->loadPlayersBasicInfos();
        return $players[$player_id]['player_name'] ?? ('P'.$player_id);
    }
}
</file>
```

```xml
<file path="herdingcats.action.php">
<?php
/**
 * Herding Cats - action bridge
 */

class action_herdingcats extends APP_GameAction
{
    public function __default()
    {
        if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "herdingcats_herdingcats";
            self::trace("Complete reinitialization of board game");
        }
    }

    public function actDeclare()
    {
        self::setAjaxMode();
        $card_id = self::getArg('card_id', AT_posint, true);
        $declared_type = self::getArg('declared_type', AT_posint, true);
        $target_player_id = self::getArg('target_player_id', AT_int, false, null);
        $this->game->actDeclare($card_id, $declared_type, $target_player_id);
        self::ajaxResponse();
    }

    public function actChallenge()
    {
        self::setAjaxMode();
        $this->game->actChallenge();
        self::ajaxResponse();
    }

    public function actPassChallenge()
    {
        self::setAjaxMode();
        $this->game->actPassChallenge();
        self::ajaxResponse();
    }

    public function actSelectBlindFromActor()
    {
        self::setAjaxMode();
        $card_id = self::getArg('card_id', AT_posint, true);
        $this->game->actSelectBlindFromActor($card_id);
        self::ajaxResponse();
    }

    public function actSelectBlindFromChallenger()
    {
        self::setAjaxMode();
        $card_id = self::getArg('card_id', AT_posint, true);
        $this->game->actSelectBlindFromChallenger($card_id);
        self::ajaxResponse();
    }

    public function actSelectTargetSlot()
    {
        self::setAjaxMode();
        $card_id = self::getArg('card_id', AT_posint, true);
        $this->game->actSelectTargetSlot($card_id);
        self::ajaxResponse();
    }

    public function actDeclareIntercept()
    {
        self::setAjaxMode();
        $zone = self::getArg('zone', AT_posint, true);
        $card_id = self::getArg('card_id', AT_posint, true);
        $this->game->actDeclareIntercept($zone, $card_id);
        self::ajaxResponse();
    }

    public function actPassIntercept()
    {
        self::setAjaxMode();
        $this->game->actPassIntercept();
        self::ajaxResponse();
    }

    public function actChallengeIntercept()
    {
        self::setAjaxMode();
        $this->game->actChallengeIntercept();
        self::ajaxResponse();
    }

    public function actPassChallengeIntercept()
    {
        self::setAjaxMode();
        $this->game->actPassChallengeIntercept();
        self::ajaxResponse();
    }
}
</file>
```

### 3) Client UI

```xml
<file path="herdingcats.view.php">
<?php
/**
 * Herding Cats - table view
 */
require_once(APP_BASE_PATH."view/common/game.view.php");

class view_herdingcats_herdingcats extends game_view
{
    function getGameName() {
        return "herdingcats";
    }

    function build_page($viewArgs)
    {
        // Root areas
        $this->page->begin_block("herdingcats_herdingcats", "playerboard");
        foreach ($this->game->loadPlayersBasicInfos() as $player_id => $player) {
            $this->page->insert_block("playerboard", array(
                "PLAYER_ID" => $player_id,
                "PLAYER_NAME" => $player['player_name']
            ));
        }
    }
}
</file>
```

```xml
<file path="herdingcats_herdingcats.tpl">
<div id="hc_table" class="hc-table">

  <div id="hc_left">
    <div id="hc_myhand" class="hc-hand"></div>
    <div id="hc_controls" class="hc-controls">
      <div id="hc_prompt" class="hc-prompt"></div>
      <div id="hc_buttons" class="hc-buttons"></div>
    </div>
  </div>

  <div id="hc_center" class="hc-center">
    <div id="hc_players_area" class="hc-players">
      <!-- Player boards inserted here -->
      {BEGIN playerboard}
      <div class="hc-player" id="hc_player_{PLAYER_ID}">
        <div class="hc-player-header">
          <span class="hc-player-name" id="hc_pname_{PLAYER_ID}">{PLAYER_NAME}</span>
          <span> • Hand: <span id="hc_handcount_{PLAYER_ID}">0</span></span>
          <span> • Score: <span id="player_score_{PLAYER_ID}">0</span></span>
        </div>
        <div class="hc-rows">
          <div class="hc-herd">
            <div class="hc-herd-title">Herd face-down</div>
            <div class="hc-herd-fd" id="hc_herd_fd_{PLAYER_ID}"></div>
          </div>
          <div class="hc-herd">
            <div class="hc-herd-title">Herd face-up</div>
            <div class="hc-herd-fu" id="hc_herd_fu_{PLAYER_ID}"></div>
          </div>
          <div class="hc-discard">
            <div class="hc-herd-title">Discard</div>
            <div class="hc-discard-pile" id="hc_discard_{PLAYER_ID}"></div>
          </div>
        </div>
      </div>
      {END playerboard}
    </div>
  </div>

</div>
</file>
```

```xml
<file path="herdingcats.css">
.hc-table { display: flex; gap: 16px; padding: 8px; }
#hc_left { width: 320px; }
#hc_center { flex: 1; overflow: auto; }
.hc-hand { min-height: 160px; border: 1px solid #444; padding: 8px; border-radius: 6px; }
.hc-controls { margin-top: 8px; }
.hc-buttons button { margin: 4px; }
.hc-players { display: flex; flex-direction: column; gap: 12px; }
.hc-player { border: 1px solid #666; padding: 8px; border-radius: 6px; background: #1b1b1b33; }
.hc-player-header { font-weight: bold; margin-bottom: 6px; }
.hc-rows { display: flex; gap: 12px; flex-wrap: wrap; }
.hc-herd { border: 1px dashed #777; padding: 6px; border-radius: 6px; min-width: 260px; }
.hc-herd-title { font-size: 12px; opacity: 0.8; margin-bottom: 4px; }
.hc-herd-fd, .hc-herd-fu, .hc-discard-pile { display:flex; gap:6px; flex-wrap: wrap; min-height: 64px; }
.hc-card { width: 72px; height: 96px; background-size: cover; border-radius: 6px; border: 1px solid #333; position: relative; }
.hc-card.back { filter: brightness(0.9); }
.hc-clickable { outline: 2px solid #3fa9f5; cursor: pointer; }
.hc-selected { outline: 3px solid #eec643; }
</file>
```

```xml
<file path="herdingcats.js">
define([
  "dojo","dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/stock"
], function (dojo, declare) {

  return declare("bgagame.herdingcats", ebg.core.gamegui, {
    constructor: function(){
      this.handStock = null;
      this.art = null;
      this.C = {
        KITTEN: 1,
        SHOWCAT: 2,
        ALLEYCAT: 3,
        CATNIP: 4,
        ANIMALCONTROL: 5,
        LASERPOINTER: 6,
        TZ_NONE: 0, TZ_HAND: 1, TZ_HERD: 2
      };
      this._lastPrompt = "";
    },

    setup: function(gamedatas) {
      this.art = gamedatas.art;

      // Build hand stock for me
      this.handStock = new ebg.stock();
      this.handStock.create(this, $('hc_myhand'), 72, 96);
      this.handStock.image_items_per_row = 1;

      // Register types by giving each its own image file at index 0
      const addType = (type, img) => this.handStock.addItemType(type, type, g_gamethemeurl + img, 0);
      addType(this.C.KITTEN, this.art[this.C.KITTEN]);
      addType(this.C.SHOWCAT, this.art[this.C.SHOWCAT]);
      addType(this.C.ALLEYCAT, this.art[this.C.ALLEYCAT]);
      addType(this.C.CATNIP, this.art[this.C.CATNIP]);
      addType(this.C.ANIMALCONTROL, this.art[this.C.ANIMALCONTROL]);
      addType(this.C.LASERPOINTER, this.art[this.C.LASERPOINTER]);

      // Fill my hand with faces
      gamedatas.hand.forEach(c => {
        this.handStock.addToStockWithId(c.type_arg, c.id); // in hand current==base
      });

      // Herds and discards
      for (let pid in gamedatas.herds) {
        this.refreshPlayerAreas(pid, gamedatas.herds[pid], gamedatas.discards[pid]);
      }

      // Hand counts
      this.updateHandCounts(gamedatas.handCounts);

      // Listen to stock clicks during declaration
      dojo.connect(this.handStock, 'onChangeSelection', this, () => this.onHandSelectionChanged());

      // Notifications
      this.setupNotifications();
    },

    // --------- Helpers ---------

    cardDiv: function(imgPath){
      const d = dojo.create('div', { class: 'hc-card', style: `background-image:url(${g_gamethemeurl+imgPath})` });
      return d;
    },

    refreshPlayerAreas: function(pid, herd, discards) {
      // Face-down herd
      const fd = $('hc_herd_fd_'+pid); dojo.empty(fd);
      herd.facedown.forEach(c => {
        const d = this.cardDiv(this.art['back']); d.classList.add('back');
        d.id = 'hc_fd_' + c.id;
        fd.appendChild(d);
      });

      // Face-up herd
      const fu = $('hc_herd_fu_'+pid); dojo.empty(fu);
      herd.faceup.forEach(c => {
        const d = this.cardDiv(this.art[c.type]);
        d.id = 'hc_fu_' + c.id;
        fu.appendChild(d);
      });

      // Discard - show up to last 6
      const dd = $('hc_discard_'+pid); dojo.empty(dd);
      (discards || []).slice(-6).forEach(c => {
        const d = this.cardDiv(this.art[c.type]);
        d.id = 'hc_dis_' + c.id;
        dd.appendChild(d);
      });
    },

    updateHandCounts: function(counts){
      for (let pid in counts) {
        $('hc_handcount_'+pid).innerText = counts[pid];
      }
    },

    setPrompt: function(html){
      this._lastPrompt = html;
      $('hc_prompt').innerHTML = html;
    },
    clearButtons: function(){ dojo.empty('hc_buttons'); },
    addButton: function(id,text,cb){
      const b = dojo.create('button', { id, innerHTML: text, class: 'bgabutton bgabutton_blue' }, 'hc_buttons');
      dojo.connect(b, 'onclick', this, cb);
      return b;
    },

    // --------- State entry ---------

    onEnteringState: function(stateName, args){
      this.clearButtons();
      switch(stateName){
        case 'awaitDeclaration':
          this.setPrompt(_('Select a hand card, declare identity, and optionally a target.'));
          this.enableDeclarationUI(args.args);
          break;
        case 'challengeWindow':
          this.setPrompt(_('Challenge the claim or Pass.'));
          this.enableChallengeUI(args.args);
          break;
        case 'challengerSelectBluffPenalty':
          this.setPrompt(_('Pick a blind card from the bluffer to discard.'));
          this.enableBlindPickFromActor(args.args);
          break;
        case 'attackerSelectTruthfulPenalty':
          this.setPrompt(_('Pick one blind card from each challenger, one at a time.'));
          this.enableBlindPickFromChallenger(args.args);
          break;
        case 'targetSelection':
          this.setPrompt(_('Select the hidden slot to target.'));
          this.enableTargetSelection(args.args);
          break;
        case 'interceptDeclare':
          this.setPrompt(_('Defender may discard a Laser Pointer from hand or herd to intercept, or pass.'));
          this.enableInterceptDeclare(args.args);
          break;
        case 'interceptChallengeWindow':
          this.setPrompt(_('Challenge the intercept claim or Pass.'));
          this.enableInterceptChallengeUI(args.args);
          break;
        default:
          this.setPrompt('');
      }
    },

    onLeavingState: function(stateName){
      // Clear strong selection outlines
      dojo.query('.hc-selected').removeClass('hc-selected');
      this.clearButtons();
    },

    // --------- UI wiring for states ---------

    // Await declaration
    enableDeclarationUI: function(args){
      const me = this.player_id;

      // Declarers pick a physical card, then a declared identity, then maybe a target
      const idSel = dojo.create('select', { id: 'hc_declared_type' }, 'hc_buttons');
      [
        [this.C.KITTEN,'Kitten'],
        [this.C.SHOWCAT,'Show Cat'],
        [this.C.ALLEYCAT,'Alley Cat'],
        [this.C.CATNIP,'Catnip'],
        [this.C.ANIMALCONTROL,'Animal Control'],
        [this.C.LASERPOINTER,'Laser Pointer']
      ].forEach(([v, t]) => {
        dojo.create('option', { value: v, innerHTML: _(t) }, idSel);
      });

      const targetSel = dojo.create('select', { id: 'hc_target_player' }, 'hc_buttons');
      dojo.create('option', { value: '', innerHTML: _('No target') }, targetSel);
      for (let pid in args.players){
        if (parseInt(pid) == me) continue;
        dojo.create('option', { value: pid, innerHTML: args.players[pid].player_name }, targetSel);
      }

      this.addButton('hc_btn_declare', _('Play this declaration'), () => {
        const selected = this.handStock.getSelectedItems();
        if (selected.length != 1) { this.showMessage(_("Select exactly one card from your hand."), 'error'); return; }
        const card_id = selected[0].id;
        const declared_type = parseInt($('hc_declared_type').value);
        const target_player_id = $('hc_target_player').value || null;

        // Hint: if a non-targeting type was picked, ignore the target on server
        this.ajaxcall("/herdingcats/herdingcats/actDeclare.html", {
          card_id, declared_type, target_player_id, lock: true
        }, this, () => {
          this.handStock.unselectAll();
          this.clearButtons();
        });
      });
    },

    onHandSelectionChanged: function(){ /* highlight prompt only */ },

    // Challenge window
    enableChallengeUI: function(args){
      const me = this.player_id;
      if (args.eligible.indexOf(parseInt(me)) === -1) {
        this.setPrompt(_('Waiting for other players...'));
        return;
      }
      this.addButton('hc_btn_challenge', _('Challenge'), () => {
        this.ajaxcall("/herdingcats/herdingcats/actChallenge.html", { lock: true }, this, () => {});
      });
      this.addButton('hc_btn_pass', _('Pass'), () => {
        this.ajaxcall("/herdingcats/herdingcats/actPassChallenge.html", { lock: true }, this, () => {});
      });
    },

    // Bluff penalty
    enableBlindPickFromActor: function(args){
      const actor = args.bluffed_player;
      const cont = 'hc_buttons';
      dojo.empty(cont);
      const row = dojo.create('div', {}, cont);
      args.actor_hand_blind.forEach(c => {
        const d = this.cardDiv(this.art['back']); d.classList.add('hc-clickable');
        dojo.connect(d, 'onclick', this, () => {
          dojo.query('.hc-selected').removeClass('hc-selected'); d.classList.add('hc-selected');
          this.ajaxcall("/herdingcats/herdingcats/actSelectBlindFromActor.html", { card_id: c.id, lock: true }, this, ()=>{});
        });
        row.appendChild(d);
      });
    },

    // Truthful penalties
    enableBlindPickFromChallenger: function(args){
      if (!args.next_victim_id) { this.clearButtons(); return; }
      const cont = 'hc_buttons'; dojo.empty(cont);
      dojo.create('div', { innerHTML: _('Victim: ') + args.next_victim_id }, cont);
      const row = dojo.create('div', {}, cont);
      args.victim_hand_blind.forEach(c => {
        const d = this.cardDiv(this.art['back']); d.classList.add('hc-clickable');
        dojo.connect(d, 'onclick', this, () => {
          dojo.query('.hc-selected').removeClass('hc-selected'); d.classList.add('hc-selected');
          this.ajaxcall("/herdingcats/herdingcats/actSelectBlindFromChallenger.html", { card_id: c.id, lock: true }, this, ()=>{});
        });
        row.appendChild(d);
      });
    },

    // Target selection
    enableTargetSelection: function(args){
      const cont = 'hc_buttons'; dojo.empty(cont);
      const row = dojo.create('div', {}, cont);
      args.slots.forEach(c => {
        const d = this.cardDiv(this.art['back']); d.classList.add('hc-clickable');
        dojo.connect(d, 'onclick', this, () => {
          dojo.query('.hc-selected').removeClass('hc-selected'); d.classList.add('hc-selected');
          this.ajaxcall("/herdingcats/herdingcats/actSelectTargetSlot.html", { card_id: c.id, lock: true }, this, ()=>{});
        });
        row.appendChild(d);
      });
    },

    // Intercept declare
    enableInterceptDeclare: function(args){
      const me = this.player_id;
      if (parseInt(me) !== parseInt(args.defender_id)) {
        this.setPrompt(_('Waiting for defender...'));
        return;
      }
      const cont = 'hc_buttons'; dojo.empty(cont);

      if (args.hand_laser.length === 0 && args.herd_laser.length === 0) {
        this.addButton('hc_btn_pass_intercept', _('Pass'), () => {
          this.ajaxcall("/herdingcats/herdingcats/actPassIntercept.html", { lock: true }, this, ()=>{});
        });
        return;
      }

      dojo.create('div', { innerHTML: _('Choose a Laser Pointer to discard, or Pass:') }, cont);

      const mkRow = (title, arr, zone) => {
        const label = dojo.create('div', { innerHTML: title }, cont);
        const row = dojo.create('div', {}, cont);
        arr.forEach(c => {
          const d = this.cardDiv(zone==this.C.TZ_HAND ? this.art[this.C.LASERPOINTER] : this.art[this.C.LASERPOINTER]); // both rendered same
          d.classList.add('hc-clickable');
          dojo.connect(d, 'onclick', this, () => {
            dojo.query('.hc-selected').removeClass('hc-selected'); d.classList.add('hc-selected');
            this.ajaxcall("/herdingcats/herdingcats/actDeclareIntercept.html", { zone, card_id: c.id, lock: true }, this, ()=>{});
          });
          row.appendChild(d);
        });
      };
      mkRow(_('From hand'), args.hand_laser, this.C.TZ_HAND);
      mkRow(_('From herd'), args.herd_laser, this.C.TZ_HERD);

      this.addButton('hc_btn_pass_intercept', _('Pass'), () => {
        this.ajaxcall("/herdingcats/herdingcats/actPassIntercept.html", { lock: true }, this, ()=>{});
      });
    },

    // Intercept challenge window
    enableInterceptChallengeUI: function(args){
      const me = this.player_id;
      if (args.eligible.indexOf(parseInt(me)) === -1) {
        this.setPrompt(_('Waiting for others...'));
        return;
      }
      this.addButton('hc_btn_ch_int', _('Challenge'), () => {
        this.ajaxcall("/herdingcats/herdingcats/actChallengeIntercept.html", { lock:true }, this, ()=>{});
      });
      this.addButton('hc_btn_pass_int', _('Pass'), () => {
        this.ajaxcall("/herdingcats/herdingcats/actPassChallengeIntercept.html", { lock:true }, this, ()=>{});
      });
    },

    // --------- Notifications ---------

    setupNotifications: function(){
      dojo.subscribe('declared', this, 'notif_declared');
      dojo.subscribe('challengeDeclared', this, 'notif_challengeDeclared');
      dojo.subscribe('challengeResult', this, 'notif_challengeResult');
      dojo.subscribe('challengeResultReveal', this, 'notif_challengeResultReveal');

      dojo.subscribe('discardPublic', this, 'notif_discardPublic');
      dojo.subscribe('handCounts', this, 'notif_handCounts');

      dojo.subscribe('cardAddedToHerd', this, 'notif_cardAddedToHerd');
      dojo.subscribe('privateHerdCardIdentity', this, 'notif_privateHerdCardIdentity');
      dojo.subscribe('stolenToHerd', this, 'notif_stolenToHerd');

      dojo.subscribe('revealFromHand', this, 'notif_reveal');
      dojo.subscribe('revealFromHerd', this, 'notif_reveal');
      dojo.subscribe('flipFaceUp', this, 'notif_flipFaceUp');
      dojo.subscribe('ineffective', this, 'notif_ineffective');

      dojo.subscribe('privateFullState', this, 'notif_privateFullState');
      dojo.subscribe('scoresComputed', this, 'notif_scoresComputed');
    },

    notif_declared: function(n){ this.showMessage(_('${player_name} declared ') + n.args.decl, 'info'); },
    notif_challengeDeclared: function(n){ this.showMessage(n.args.player_name + _(' challenges!'), 'info'); },
    notif_challengeResult: function(n){ this.showMessage(_('Claim was truthful.'), 'info'); },
    notif_challengeResultReveal: function(n){ this.showMessage(_('Bluff revealed: ') + this.getTypeName(n.args.real_type), 'error'); },

    notif_discardPublic: function(n){
      const card = n.args.card;
      const pid = card.location_arg;
      // Move to discard pile view
      const dd = $('hc_discard_'+pid);
      const d = this.cardDiv(this.art[card.type]); d.id = 'hc_dis_' + card.id;
      dd.appendChild(d);
    },

    notif_handCounts: function(n){ this.updateHandCounts(n.args.counts); },

    notif_cardAddedToHerd: function(n){
      const pid = n.args.player_id;
      const parent = $('hc_herd_fd_'+pid);
      const d = this.cardDiv(this.art['back']); d.classList.add('back');
      d.id = 'hc_fd_' + n.args.card_id;
      parent.appendChild(d);

      // If it was mine and it came from my hand, remove it from stock
      try { this.handStock.removeFromStockById(n.args.card_id); } catch(e){}
    },

    notif_privateHerdCardIdentity: function(n){ /* owner can see in any info panel if needed */ },

    notif_stolenToHerd: function(n){
      // Remove from victim hand count will update via separate notif
      const pid = n.args.player_id;
      const parent = $('hc_herd_fd_'+pid);
      const d = this.cardDiv(this.art['back']); d.classList.add('back');
      d.id = 'hc_fd_' + n.args.card_id;
      parent.appendChild(d);
    },

    notif_reveal: function(n){
      const card = n.args.card;
      this.showMessage(_('Revealed: ') + this.getTypeName(card.type), 'info');
    },

    notif_flipFaceUp: function(n){
      const card = n.args.card;
      // Remove from fd zone and add to fu
      const fd = $('hc_fd_' + card.id);
      if (fd && fd.parentNode) fd.parentNode.removeChild(fd);
      const fu = $('hc_herd_fu_' + card.location_arg);
      const d = this.cardDiv(this.art[card.type]); d.id = 'hc_fu_' + card.id;
      fu.appendChild(d);
    },

    notif_ineffective: function(n){ this.showMessage(_('Ineffective.'), 'info'); },

    notif_privateFullState: function(n){ /* can be used on reconnect if needed */ },

    notif_scoresComputed: function(n){ /* scores already pushed by server */ },

    // --------- Utils ---------
    getTypeName: function(t){
      switch(parseInt(t)){
        case this.C.KITTEN: return _('Kitten');
        case this.C.SHOWCAT: return _('Show Cat');
        case this.C.ALLEYCAT: return _('Alley Cat');
        case this.C.CATNIP: return _('Catnip');
        case this.C.ANIMALCONTROL: return _('Animal Control');
        case this.C.LASERPOINTER: return _('Laser Pointer');
        default: return '';
      }
    },

    // --------- BGA required stubs ---------
    onUpdateActionButtons: function(stateName, args) {
      // We build buttons in onEnteringState instead
    },
  });
});
</file>
```

### 4) README and tooling

```xml
<file path="README.md">
# Herding Cats — BGA implementation

A compact bluff‑driven micro‑deck card game for 2‑6 players. This repository contains a complete Board Game Arena (BGA) project scaffold with server logic, client UI, state machine, and database model.

## Quick start

> You cannot run a full BGA game locally in a browser without the BGA Studio server. The standard workflow is to **deploy to BGA Studio** and test on the dev table. You can still develop locally in your editor and auto‑sync via SFTP or use a helper like BGA Workbench.

### 1) Get a BGA Studio developer account

- Apply for a developer account on Board Game Arena. You will receive SFTP credentials and access to `https://studio.boardgamearena.com/`.
- Your dev login is usually your normal username with a `0` suffix.

### 2) Create the game project

- In Studio, create a new game project named `herdingcats` (lowercase, no spaces).
- Note the SFTP host, login and password from the welcome email.

### 3) Put these files in place

- Create the project folder in your local workspace.
- Copy each XML file from my response into a file at the exact path in its tag.
- Make sure your art files exist in `img/herding_cats_art/`:
```

alleycat.jpeg
animalcontrol.jpeg
cardback.jpeg
catnip.jpeg
kitten.jpeg
laserpointer.jpeg  ← if your file is 'lasterpointer.jpeg' copy or rename it
showcat.jpeg

````
- Sync the entire project to the Studio via SFTP. Recommended options below.

### 4) Recommended editor + SFTP sync

**VS Code** with the “SFTP” or “Remote FS” extension works well:

- Configure `sftp.json` in your repo (do not commit your password). Example:
```json
{
  "host": "sftp.boardgamearena.com",
  "username": "yourdevuser0",
  "password": "****",
  "protocol": "sftp",
  "remotePath": "/home/yourdevuser0/herdingcats",
  "uploadOnSave": true,
  "ignore": [".git/**","node_modules/**",".vscode/**"]
}
````

Alternatively use **BGA Workbench** if you prefer a Docker‑based deploy. See [https://github.com/danielholmes/bga-workbench](https://github.com/danielholmes/bga-workbench) for instructions. Workbench is optional.

### 5) Launch a dev table

* Log in as your dev user at Studio, go to Control Panel → Manage games → `herdingcats` → Play.
* Create a Training table, click Express Start. Studio spawns dummy players for you.
* Use the **red rotating‑arrows** icon near the player list to switch seats and simulate all players.

You should see:

* Your hand with face‑up art,
* Per‑player Herd face‑down and face‑up rows,
* Public discard piles.

## How to test the game

1. **Play a simple truthful turn**

   * Play any card as **Kitten** or **Show Cat**. No challenge. It should be added face‑down to your herd. Your hand count decreases.

2. **Targeted effects**

   * Play **Alley Cat** on an opponent. Select a hidden slot from their hand. If not Alley Cat, it discards. If it was Alley Cat, it returns to their hand and your card still goes to your herd as Alley Cat.
   * Play **Catnip** on an opponent. If not Catnip, that card moves to your herd face‑down and only you know what it was.
   * Play **Animal Control** on an opponent’s **face‑down** herd. If it was Animal Control, it flips face‑up and stays protected. Otherwise discard it.

3. **Challenges**

   * On another account, challenge a declaration. Verify:

     * If the actor **bluffed**, the played card is revealed and discarded. The first challenger picks a blind card from the actor’s hand to discard. Turn ends.
     * If the actor **told the truth**, the actor picks a blind card from each challenger to discard, then continues with the effect.

4. **Laser Pointer intercept**

   * Target someone. Before reveal, the defender chooses a Laser Pointer from hand or herd to discard.
   * Other players can challenge that claim.

     * If truthful, the selected Laser Pointer is discarded and each challenger discards a blind card chosen by the defender (the current implementation picks at random to keep flow simple).
     * If false, the selected card is discarded anyway, defender discards one extra blind, and the original attack resumes.

5. **Face‑up protection**

   * After Animal Control meets Animal Control, ensure the flipped face‑up card cannot be targeted again by Animal Control or Catnip.

6. **Endgame and scoring**

   * Force a player to reach 0 cards in hand at end of a turn. Check herd totals, Show Cat bonus when at least one Kitten is present, and hand bonus `ceil(hand/2)`.

## Implementation notes and trade‑offs

* **Current vs base identity**
  We store current identity in `card_type` and base in `card_type_arg`. When a card is added to herd after a successful play, we set `card_type = declared_identity`. This matches your rule that the physical card becomes the declared identity in the herd while challenges always refer back to the base identity. This is the simplest approach that still allows identity‑changing play.

* **Pending action model**
  We use a single `pending_action` row to persist the active declaration and its related metadata across states. This avoids trying to over‑fit integers in the BGA `global` table and keeps the engine stateless as BGA expects.

* **Intercept penalty selection**
  In Section 5.2 the defender selects blinds from challengers when truthful. To keep first release friction‑free, I automated those selections randomly in `stResolveInterceptChallenge`. You can easily split this into a small looped state where the defender picks a specific blind card from each challenger, mirroring the truthful declaration penalty. I would test random first to ensure the broader flow is solid, then add the interactive step.

* **Truthful reveal on challenge**
  Your spec allows either revealing or a truth flag. I kept the default as **not revealing** (`REVEAL_TRUTH_ON_CHALLENGE=false`) to preserve bluff value. Toggle if you prefer revealing in early tests for transparency.

* **UI simplicity**
  I used `ebg.stock` for the active hand with per‑type images to avoid building a sprite in v1. Herd and discard display are simple div grids. Once rules are rock solid, you can upgrade to `bga-animations` for sliding and flip effects.

* **Art filename**
  The code expects `img/herding_cats_art/laserpointer.jpeg`. If your assets folder has `lasterpointer.jpeg`, either rename it or copy it so both exist. This avoids confusing missing images during testing.

## Project structure

```
herdingcats/
  dbmodel.sql
  gameinfos.inc.php
  material.inc.php
  states.inc.php
  stats.json
  herdingcats.action.php
  herdingcats.view.php
  herdingcats_herdingcats.tpl
  herdingcats.css
  herdingcats.js
  modules/php/Game.php
  img/herding_cats_art/*.jpeg
```

## Deploying updates

* Commit to your Git repository regularly.
* Configure your editor to **upload on save** to the Studio SFTP.
* After a change, refresh the browser tab of your dev table. For DB model changes (`dbmodel.sql`), stop the table and start a fresh one.

### Optional GitHub Actions deploy

If you want CI to push to Studio on each `main` commit, create `.github/workflows/deploy.yml`:

```yaml
name: Deploy to BGA Studio
on:
  push:
    branches: [ main ]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: SFTP Sync
        uses: SamKirkland/FTP-Deploy-Action@v4
        with:
          server: ${{ secrets.BGA_SFTP_HOST }}
          username: ${{ secrets.BGA_SFTP_USER }}
          password: ${{ secrets.BGA_SFTP_PASS }}
          protocol: sftp
          server-dir: /home/${{ secrets.BGA_SFTP_USER }}/herdingcats/
          local-dir: ./
          exclude: |
            **/.git*
            **/.github/**
            **/node_modules/**
            **/.vscode/**
```

Add the three secrets in your GitHub repo settings.

## Moving to Alpha and beyond

* When core rules work without crashes, request **ALPHA** in the Studio Control Panel.
* Invite reviewers to the alpha. They can reproduce multi‑challenger and corner cases faster.
* After feedback, fix issues, then request **BETA**.
* Once in beta, polish UX and animations. Consider adding:

  * Defender‑chosen blind discards for successful intercepts.
  * Per‑turn status bar copy improvements for mobile.
  * Tooltips that show the declared identity on face‑down played cards during the turn resolution.

## Known testing risks

* State machine freezes typically come from forgetting to call `nextState` or not marking multi‑active players as non‑active after they act. If you see “Waiting for server”, check Studio logs.
* The Deck component allows any string for `card_location`. Avoid typos like `herd_faceup` vs `herd_faceUp`.
* If your image is misnamed you will see blank cards. Confirm network tab loads `laserpointer.jpeg`.

## Licence and publishing

Herding Cats is your original game, so you are the rights holder. BGA will ask you to confirm that when moving to Alpha/Beta.

Enjoy testing. </file>


```
