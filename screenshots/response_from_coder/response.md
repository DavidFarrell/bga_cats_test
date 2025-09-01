**Diagnosis**

* The server’s bluff adjudication is correct. The wrong card id is sometimes sent at declare-time because the client relies on a cached `this.selectedCard`. If the overlay opens with a stale selection, the click on a declare button posts the wrong id.
* There is also a premature herd update: `actDeclare` moves the card directly to `HERD_DOWN` and notifies `herdUpdate`, so the UI can show a face-down card before the challenge resolves.

**Fix - high level**

1. **Client - robust selection and clear confirmation**

* Always re-read the current hand stock selection when the player clicks a Declare button - do not trust any cached `this.selectedCard`.
* Add a small **selected-card preview** to the declaration overlay to show the exact card that will be played.
* Block declare if nothing is selected and show a clear message.
* Challenge window continues to use the **real** `declared_type` from server state args (keeps your previous improvement). Client messaging prefers numeric ids and falls back to strings.

2. **Server - safer posture and cleaner flow**

* `actDeclare`: move the card from HAND to **CARD\_LOCATION\_LIMBO** instead of `HERD_DOWN`. Do **not** emit `herdUpdate` here.
* `stResolveChallenge`: at resolution

  * If **unchallenged** or **truthful** - move from LIMBO to `HERD_DOWN` and notify `herdUpdate` (face down).
  * If **bluff caught** - move from LIMBO to `DISCARD` and notify `discardUpdate`.
* Add concise debug logs:

  * On declare: logs `(actor_id, played_card_id, declared_type, actual_type)`.
  * On resolve: logs the same plus challengers.
* Standardise pending data persisted to DB:

  * Store `declared_identity` and `phase` as **integers**. Provide constants (`PENDING_PHASE_*`) so schema and code stay aligned.

3. **Notification and args standardisation**

* Keep `challengeResult`’s structured keys: `was_bluffing`, `declared_type`, `actual_card_type` and maintain legacy text fields for backward compatibility.
* `argChallengeWindow` returns numeric `declared_type` and a consistent `actor_name`.

4. **Schema alignment**

* No SQL migration required - server now writes integer `phase` and `declared_identity` to match the existing table definition.
* Safe-defines for constants added in `material.inc.php` in case of partial environments.

**Acceptance criteria mapping**

* **Repro - Show Cat declared as “Kitten,” opponent challenges**

  * If actually Show Cat: server now computes `was_bluffing: true`, moves card from LIMBO to DISCARD, emits `challengeResult` with the standard keys, and no herd update happens before resolution.
  * If the player actually selected a Kitten: server computes `was_bluffing: false`, moves from LIMBO to HERD\_DOWN, and the client narrates “truthful.”
  * The overlay shows the exact selected card to reduce misclicks and confusion.

* **Visibility and UX**

  * Declaration overlay includes a live card preview.
  * Challenge window preview uses the numeric `declared_type` coming from pending action.

* **Logs and consistency**

  * `challengeResult` includes the structured keys and client messaging reflects them.
  * Debug logs at declare and resolve include `(actor_id, played_card_id, declared_type, actual_type)`.

---

## Updated code - drop-in XML

[Download the updated XML](sandbox:/mnt/data/consolidated_codebase.updated.xml)

```xml
<codebase>
  <file path="src/herdingcats.action.php"><?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * herdingcats.action.php
 *
 * HerdingCats main action entry point
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
...
     * @param int|null $target_player_id - Target player for targeted effects (null for non-targeting cards)
     */
    public function actDeclare()
    {
        self::setAjaxMode();
        
        // Retrieve arguments from JavaScript call
        $card_id = self::getArg("card_id", AT_posint, true);
        $declared_type = self::getArg("declared_type", AT_posint, true);
        $target_player_id = self::getArg("target_player_id", AT_posint, false);
        
        // Check action is valid
        $this->game->checkAction( 'actDeclare' ); 
        
        // Call game logic
        $this->game->actDeclare($card_id, $declared_type, $target_player_id);
        self::ajaxResponse();
    }

    /**
     * After a failed challenge (actor was truthful), actor selects penalty cards from challengers
     * 
     * @param int $player_id - The challenger to penalize
     * @param int $card_index - Index of card in challenger's hand (0-based, for blind selection)
     */
    public function actSelectBlindFromChallenger()
    {
        self::setAjaxMode();
        
        $player_id = self::getArg("player_id", AT_posint, true);
        $card_index = self::getArg("card_index", AT_posint, true);
        
        // Check action is valid
        $this->game->checkAction( 'actSelectBlindFromChallenger' );
        $this->game->actSelectBlindFromChallenger($player_id, $card_index);
        self::ajaxResponse();
    }
...
?></file>
  <file path="src/material.inc.php"><?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * HerdingCats game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
...

/*
 * Game constants
 */
define('MIN_PLAYERS', 2);
define('MAX_PLAYERS', 6);

/*
 * Show Cat Scoring Logic
 * Show Cat normally worth 5 points, but worth 7 points if player has at least one Kitten in their herd at scoring
 */
define('SHOWCAT_BASE_VALUE', 5);
define('SHOWCAT_KITTEN_BONUS_VALUE', 7);

/*
 * Additional constants - safe defines to avoid redeclaration and keep schema aligned
 */
if (!defined('CARD_TYPE_KITTEN')) define('CARD_TYPE_KITTEN', 1);
if (!defined('CARD_TYPE_SHOWCAT')) define('CARD_TYPE_SHOWCAT', 2);
if (!defined('CARD_TYPE_ALLEYCAT')) define('CARD_TYPE_ALLEYCAT', 3);
if (!defined('CARD_TYPE_CATNIP')) define('CARD_TYPE_CATNIP', 4);
if (!defined('CARD_TYPE_ANIMALCONTROL')) define('CARD_TYPE_ANIMALCONTROL', 5);
if (!defined('CARD_TYPE_LASERPOINTER')) define('CARD_TYPE_LASERPOINTER', 6);

if (!defined('CARD_LOCATION_DECK')) define('CARD_LOCATION_DECK', 'deck');
if (!defined('CARD_LOCATION_HAND')) define('CARD_LOCATION_HAND', 'hand');
if (!defined('CARD_LOCATION_HERD_DOWN')) define('CARD_LOCATION_HERD_DOWN', 'herd_down');
if (!defined('CARD_LOCATION_HERD_UP')) define('CARD_LOCATION_HERD_UP', 'herd_up');
if (!defined('CARD_LOCATION_DISCARD')) define('CARD_LOCATION_DISCARD', 'discard');
if (!defined('CARD_LOCATION_REMOVED')) define('CARD_LOCATION_REMOVED', 'removed');
if (!defined('CARD_LOCATION_LIMBO')) define('CARD_LOCATION_LIMBO', 'limbo'); // temporary holding area during challenges

// Pending action phases (store as integers to match DB schema)
if (!defined('PENDING_PHASE_DECLARATION')) define('PENDING_PHASE_DECLARATION', 1);
if (!defined('PENDING_PHASE_CHALLENGE')) define('PENDING_PHASE_CHALLENGE', 2);
if (!defined('PENDING_PHASE_TARGET')) define('PENDING_PHASE_TARGET', 3);
if (!defined('PENDING_PHASE_RESOLVE')) define('PENDING_PHASE_RESOLVE', 4);

?></file>
  <file path="src/herdingcats.game.php"><?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * herdingcats.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to define the rules of the game.
 */
...
        // If your game has options (variants), you also have to associate here a label to
        // the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variable...ith getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels([
            "current_declared_card" => 10,
            "current_declared_identity" => 11,
            "current_target_player" => 12,
            "current_action_id" => 13,
            "game_phase" => 14,
        ]);

        // Create deck component for managing cards
        
    /**
     * Debug logger - writes to Studio server logs without exposing to players
     */
    protected function debugLog($tag, $fields = []) {
        $prefix = '[HerdingCats ' . $tag . '] ';
        $payload = json_encode($fields);
        if (method_exists($this, 'trace')) {
            $this->trace($prefix . $payload);
        } else {
            error_log($prefix . $payload);
        }
    }
...
    function pushPending($data)
    {
        // Get the current game ID
        $game_id = intval(self::getGameId());
        
        // Prepare SQL values using correct database field names - store as integers where appropriate
        $actor_id = intval($data['actor_id']);
        $declared_identity = isset($data['declared_type']) ? intval($data['declared_type']) : 'NULL';
        $played_card_id = isset($data['card_id']) ? intval($data['card_id']) : 'NULL';
        $target_player_id = isset($data['target_player_id']) ? intval($data['target_player_id']) : 'NULL';
        $target_zone = isset($data['target_zone']) ? "'" . addslashes($data['target_zone']) . "'" : 'NULL';
        $phase = isset($data['phase']) ? intval($data['phase']) : PENDING_PHASE_DECLARATION;
        
        $sql = "INSERT INTO pending_action (game_id, actor_player_id, declared_identity, played_card_id, target_player_id, target_zone, phase) "
             . "VALUES ($game_id, $actor_id, $declared_identity, $played_card_id, $target_player_id, $target_zone, $phase)";
        self::DbQuery($sql);
        
        // Get the auto-generated action_id
        $action_id = self::DbGetLastId();
        
        // Store action ID in global state for tracking
        self::setGameStateValue('current_action_id', $action_id);
        
        return $action_id;
    }
...
    function actDeclare($card_id, $declared_type, $target_player_id = null)
    {
        // Match states.inc.php possible action name
        self::checkAction('actDeclare');
        $player_id = self::getActivePlayerId();
        
        // Validate card is in player's hand
        $card = $this->cards->getCard($card_id);
        if (!$card || $card['location'] != CARD_LOCATION_HAND || $card['location_arg'] != $player_id) {
            throw new feException("Card not in your hand");
        }
        
                // Debug - log declaration with actual card type
        $this->debugLog('DECLARE', [
            'actor_id' => $player_id,
            'played_card_id' => $card_id,
            'declared_type' => intval($declared_type),
            'actual_type' => intval($card['type'])
        ]);

        // Create a pending action for the challenge system
        $action_id = $this->pushPending([
            'actor_id' => $player_id,  // Changed from actor_player_id to actor_id
            'declared_type' => $declared_type,  // Changed from declared_identity to declared_type
            'card_id' => $card_id,
            'target_player_id' => $target_player_id,
            'phase' => PENDING_PHASE_CHALLENGE
        ]);
        
        // Store the action ID for later retrieval
        self::setGameStateValue('current_action_id', $action_id);
        
        // Move the played card to LIMBO until challenge resolves
        $this->cards->moveCard($card_id, CARD_LOCATION_LIMBO, $player_id);
        
        // Notify all players that a card was played (remove from hand, show prompt)
        self::notifyAllPlayers('cardPlayed', 
            clienttranslate('${player_name} plays a card to their herd'), 
            [
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card_id' => $card_id,
                'card' => $card,
                'declared_type' => $declared_type,
                'hand_counts' => $this->getHandCounts()
            ]
        );
        
        // herdUpdate moved to challenge resolution
        // Notify hand count update
        $this->notifyHandCounts();
        
        // Decide next state: if declared type requires targeting, go pick target first; else go to challenge window
        if ($this->cardRequiresTargeting(intval($declared_type))) {
            $this->gamestate->nextState('declaredToTarget');
        } else {
            $this->gamestate->nextState('declaredToChallenge');
        }
    }
...
    function argChallengeWindow()
    {
        // Build challenge window args from actual pending action data
        $active_player = self::getActivePlayerId();
        $players = self::loadPlayersBasicInfos();
        $eligible = [];

        foreach ($players as $player_id => $player_info) {
            if ($player_id != $active_player) {
                $eligible[] = intval($player_id);
            }
        }

        $pending = $this->pullPending();
        $declared_type = null;
        if ($pending && isset($pending['declared_identity'])) {
            // Ensure numeric int for client mapping
            $declared_type = intval($pending['declared_identity']);
        }

        return [
            'eligible' => $eligible,
            'eligible_challengers' => $eligible,
            'can_challenge' => $eligible,
            'actor_id' => $active_player,
            // Provide both keys for client robustness
            'declared_type' => $declared_type,
            'declared_card' => $declared_type,
            'actor_name' => self::getActivePlayerName()
        ];
    }
...
    function stResolveChallenge()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            throw new feException("No pending action to resolve challenge");
        }
        
        // Get card details
        $card = $this->cards->getCard($pending['played_card_id']);
        $declared_type = $pending['declared_identity'];
        $actual_type = $card['type'];
        $actor_id = $pending['actor_player_id'];
        $challengers = isset($pending['challengers']) ? $pending['challengers'] : [];
        $this->debugLog('RESOLVE_CHALLENGE', [
            'actor_id' => intval($actor_id),
            'played_card_id' => intval($pending['played_card_id']),
            'declared_type' => intval($declared_type),
            'actual_type' => intval($actual_type),
            'challengers' => $challengers
        ]);
        
        if (empty($challengers)) {
            // No challenges: move card from limbo to herd (face-down), then continue
            if ($card && $card['location'] === CARD_LOCATION_LIMBO) {
                $this->cards->moveCard($card['id'], CARD_LOCATION_HERD_DOWN, $actor_id);
                self::notifyAllPlayers('herdUpdate',
                    clienttranslate('Card added to herd'),
                    [
                        'player_id' => $actor_id,
                        'player_name' => $this->getPlayerName($actor_id),
                        'card' => ['id' => $card['id'], 'type' => $card['type'], 'type_arg' => $card['type_arg']],
                        'visible' => false
                    ]
                );
            }
            // No challenges: route depending on targeting/selection
            if ($this->cardRequiresTargeting($declared_type)) {
                if (!empty($pending['target_player_id']) || !empty($pending['selected_slot_index'])) {
                    $this->gamestate->nextState('goToIntercept');
                } else {
                    $this->gamestate->nextState('goToTarget');
                }
            } else {
                $this->gamestate->nextState('noChallenge');
            }
        } else {
            // Determine truth or bluff
            $was_bluffing = (intval($actual_type) !== intval($declared_type));
            
            if ($was_bluffing) {
                // Player was bluffing - actor pays penalty, card to discard
                $this->notifyAllPlayers('challengeResult', 
                    clienttranslate('Bluff caught! ${player_name} lied about ${declared_card}.'), 
                    [
                        'player_name' => $this->getPlayerName($actor_id),
                        // Legacy/text fields
                        'declared_card' => $this->getCardTypeName($declared_type),
                        'actual_card' => $this->getCardTypeName($actual_type),
                        'bluffing' => true,
                        // Structured fields expected by client
                        'was_bluffing' => true,
                        'declared_type' => intval($declared_type),
                        'actual_card_type' => intval($actual_type)
                    ]
                );
                
                // Move played card from limbo to discard since bluff was caught
                if ($card && $card['location'] === CARD_LOCATION_LIMBO) {
                    $this->cards->moveCard($card['id'], CARD_LOCATION_DISCARD, $actor_id);
                    self::notifyAllPlayers('discardUpdate', '', [
                        'player_id' => $actor_id,
                        'card' => ['id' => $card['id'], 'type' => $card['type'], 'type_arg' => $card['type_arg']]
                    ]);
                }
                
                // Choose first challenger to apply penalty
                $challenger_id = $challengers[0];
                $this->gamestate->changeActivePlayer($challenger_id);
                $this->gamestate->nextState('bluffCaught');
            } else {
                // Player was truthful - challengers pay penalty
                $this->notifyAllPlayers('challengeResult', 
                    clienttranslate('Challenge failed! ${player_name} was truthful about ${declared_card}.'), 
                    [
                        'player_name' => $this->getPlayerName($actor_id),
                        // Legacy/text fields
                        'declared_card' => $this->getCardTypeName($declared_type),
                        'bluffing' => false,
                        // Structured fields expected by client
                        'was_bluffing' => false,
                        'declared_type' => intval($declared_type),
                        'actual_card_type' => intval($actual_type)
                    ]
                );
                
                // Move played card from limbo to herd (face-down)
                if ($card && $card['location'] === CARD_LOCATION_LIMBO) {
                    $this->cards->moveCard($card['id'], CARD_LOCATION_HERD_DOWN, $actor_id);
                    self::notifyAllPlayers('herdUpdate', '', [
                        'player_id' => $actor_id,
                        'player_name' => $this->getPlayerName($actor_id),
                        'card' => ['id' => $card['id'], 'type' => $card['type'], 'type_arg' => $card['type_arg']],
                        'visible' => false
                    ]);
                }
                
                // Actor can choose to penalize one challenger
                $this->gamestate->changeActivePlayer($actor_id);
                $this->gamestate->nextState('challengeFailed');
            }
        }
    }
...
?></file>
  <file path="src/herdingcats.js">/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * herdingcats.js
 *
 * HerdingCats user interface script
 * 
 * Complete JavaScript game client for Herding Cats bluffing card game
 *
 */
...
        constructor: function(){
            console.log('HerdingCats constructor');
              
            // Card Type Constants
            this.CARD_TYPE_KITTEN = 1;
            this.CARD_TYPE_SHOWCAT = 2;  
            this.CARD_TYPE_ALLEYCAT = 3;
            this.CARD_TYPE_CATNIP = 4;
            this.CARD_TYPE_ANIMALCONTROL = 5;
            this.CARD_TYPE_LASERPOINTER = 6;

            // Stock components
            this.playerHand = null;
            this.playerHerds = {};
            this.playerDiscards = 
...
        //// Player's action
        
        onPlayerHandSelectionChanged: function() {
            const selection = this.playerHand.getSelectedItems();
            if (selection.length > 0) {
                const cardId = selection[0].id;
                // Show bluff/declaration dialog so player can choose any identity
                if (this.gamedatas.gamestate && this.gamedatas.gamestate.name === 'awaitDeclaration') {
                    this.showDeclarationDialog(cardId);
                }
            }
        },

        onCardTypeDeclared: function(evt) {
            const declaredType = parseInt(dojo.attr(evt.currentTarget, 'data-type'));
            
            // Always re-read the current stock selection at click time - do not rely on cached this.selectedCard
            const sel = this.playerHand.getSelectedItems();
            if (!sel || sel.length === 0) {
                this.showMessage(_('Please select a card from your hand first'), 'error');
                return;
            }
            const cardId = parseInt(sel[0].id);
            
            // Update overlay preview just before sending
            this.renderDeclarationOverlayPreview(sel[0]);
            this.hideDeclarationDialog();
            
            // Send declaration to server
            this.ajaxcall(this._actionUrl("actDeclare"), { 
                card_id: cardId,
                declared_type: declaredType,
                lock: true
            }, this, function(result) {
                // Success handled by notifications
            }

        },

        onCancelDeclaration: function() {
            this.hideDeclarationDialog();
            // Deselect card
            this.playerHand.unselectAll();
        },
...
        /**
         * Render a small preview of the selected card inside the declaration overlay
         * @param {{id:number, type:number}} selectedItem
         */
        renderDeclarationOverlayPreview: function(selectedItem) {
            const container = $('hc_declare_preview');
            if (!container) return;
            container.innerHTML = '';
            if (!selectedItem) return;
            const cardEl = dojo.create('div', {
                className: 'hc_card hc_declare_preview_card',
                'data-card-type': selectedItem.type
            }, container);
            // Small label
            dojo.create('div', { className: 'hc_declare_preview_label', innerHTML: _('You are about to play this card') }, container);
        },

        showDeclarationDialog: function(cardId) {
            this.selectedCard = cardId;
            dojo.style('hc_declare_overlay', 'display', 'flex');
            // Try to find the selected item in stock to render preview
            const sel = this.playerHand.getSelectedItems();
            if (sel && sel.length > 0) {
                this.renderDeclarationOverlayPreview(sel[0]);
            } else {
                // Clear preview if nothing selected
                const container = $('hc_declare_preview');
                if (container) container.innerHTML = '';
            }
        },
...
        // Notification handlers
        
        notif_cardPlayed: async function( args )
        {
            console.log( 'notif_cardPlayed', args );
            
            const playerId = args.player_id;
            const cardId = args.card_id;
            const declaredType = args.declared_type;
            
            // Remove card from player's hand if it's current player
            if (playerId == this.player_id) {
                this.playerHand.removeFromStockById(cardId);
            }
            
            // Update hand counts
            this.updateHandCounts(args.hand_counts);
            
            // Update action prompts
            this.updateActionPrompts('challengeWindow', {
                declared_card: declaredType,
                acting_player_name: args.player_name
            });
        },
...
        notif_challengeResult: async function( args )
        {
            console.log( 'notif_challengeResult', args );

            // Accept both legacy and structured keys
            const wasBluffing = (args.was_bluffing !== undefined) ? args.was_bluffing : args.bluffing;
            const playerName = args.player_name || _('Player');

            // Determine declared and actual labels (prefer numeric types → mapped names; fallback to provided strings)
            const declaredType = (args.declared_type !== undefined && args.declared_type !== null)
                ? args.declared_type : args.declared_card;
            const actualType = (args.actual_card_type !== undefined && args.actual_card_type !== null)
                ? args.actual_card_type : args.actual_card;

            const typeLabel = (t) => {
                if (t === undefined || t === null) return _('unknown');
                // Numeric id
                if (typeof t === 'number' || /^[0-9]+$/.test(String(t))) {
                    const key = parseInt(t);
                    return (this.cardTypeNames && this.cardTypeNames[key]) ? this.cardTypeNames[key] : _('unknown');
                }
                // String label from server
                return t;
            };

            if (wasBluffing) {
                this.showMessage(dojo.string.substitute(_('${player} was bluffing! Card was ${actual} not ${declared}'), {
                    player: playerName,
                    actual: typeLabel(actualType),
                    declared: typeLabel(declaredType)
                }), 'info');
            } else {
                this.showMessage(dojo.string.substitute(_('${player} was truthful! Card was indeed ${declared}'), {
                    player: playerName,
                    declared: typeLabel(declaredType)
                }), 'info');
            }
        },
...
</file>
  <file path="src/herdingcats_herdingcats.tpl">{OVERALL_GAME_HEADER}

<!-- 
--------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- HerdingCats implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----
--
-- herdingcats_herdingcats.tpl
--
-- This is the HTML template of your game.
--
-- Everything you are writing in this file will be displayed in the HTML page of your game user interface,
...
    <!-- Card Declaration Overlay (hidden by default) -->
    <div id="hc_declare_overlay" class="hc_overlay" style="display: none;">
        <div class="hc_declaration_panel">
            <h3>Declare Card Type</h3>
            <div id="hc_declare_preview" class="hc_declare_preview"></div>
            <div id="hc_card_type_buttons">
                <button class="hc_card_type_btn" data-type="1">Kitten</button>
                <button class="hc_card_type_btn" data-type="2">Show Cat</button>
                <button class="hc_card_type_btn" data-type="3">Alley Cat</button>
                <button class="hc_card_type_btn" data-type="4">Catnip</button>
                <button class="hc_card_type_btn" data-type="5">Animal Control</button>
                <button class="hc_card_type_btn" data-type="6">Laser Pointer</button>
            </div>
            <button id="hc_cancel_declare" class="hc_button hc_cancel_button">Cancel</button>
        </div>
    </div>

</div>

<script type="text/javascript">
// Javascript HTML templates
var jstpl_hand_card = '<div class="hc_card hc_hand_card" id="hc_...data-card-id="${card_id}" data-card-type="${card_type}"></div>';

var jstpl_herd_card_face_down = '<div class="hc_card hc_herd_car...d-id="${card_id}" data-declared-type="${declared_type}"></div>';

var jstpl_herd_card_face_up = '<div class="hc_card hc_herd_card ...data-card-id="${card_id}" data-card-type="${card_type}"></div>';

var jstpl_discard_card = '<div class="hc_card hc_discard_card hc...data-card-id="${card_id}" data-card-type="${card_type}"></div>';

var jstpl_target_button = '<button class="hc_target_btn" data-ta...id}" data-target-zone="${target_zone}">${target_name}</button>';

var jstpl_challenge_prompt = '<div class="hc_challenge_prompt">D...challenge ${player_name}\'s declaration of ${card_name}?</div>';
</script>

{OVERALL_GAME_FOOTER}</file>
  <file path="src/herdingcats.css">/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * herdingcats.css
 *
 * HerdingCats stylesheet
 *
 */

/*
...
    background-image: url('img/herding_cats_art/laserpointer.jpeg');
    background-color: #e1baff; /* Light purple fallback */
}

/* Face-down cards show cardback */
.hc_face_down {
    background-image: url('img/herding_cats_art/cardback.jpeg');
    background-color: #8B4513; /* Brown fallback */
    border-color: #654321;
}

.hc_face_down:hover {
    border-color: #8B4513;
}

/* Face-up herd cards (protected) */
.hc_herd_card.hc_face_up {
    border-color: #FF9800;
    box-shadow: 0 0 0 2px rgba(255,152,0,0.3);
}

/* Discard pile cards */
.hc_discard_card {
    opacity: 0.8

/* Declaration overlay preview */
.hc_declare_preview {
    margin: 8px 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.hc_declare_preview_card {
    width: 48px;
    height: 64px;
    border: 1px solid #555;
    border-radius: 6px;
}
.hc_declare_preview_label {
    font-size: 12px;
    opacity: 0.8;
}
</file>
</codebase>
```

---

## Quick test plan

1. **Truthful path**

* P1 selects a **Kitten** in hand and opens the declare overlay. The overlay shows the Kitten preview.
* P1 clicks **Declare “Kitten”**. UI removes the selected card from P1’s hand, no herd card appears yet.
* P2 challenges. Server resolves as truthful:

  * `challengeResult` shows `was_bluffing: false`, `declared_type: 1`, `actual_card_type: 1`.
  * Card moves from LIMBO to HERD\_DOWN, client receives `herdUpdate` for P1.

2. **Bluff path**

* P1 selects **Show Cat** in hand; overlay preview shows that card. P1 declares **“Kitten”**.
* P2 challenges. Server resolves as bluff:

  * `challengeResult` shows `was_bluffing: true`, `declared_type: 1`, `actual_card_type: 2`.
  * No herd update occurs before resolution; card moves from LIMBO to DISCARD, client receives `discardUpdate`.

3. **No selection guard**

* Click a Declare button without selecting a card. Client shows “Please select a card from your hand first” and does not call the server.

4. **Logging**

* Studio log shows:

  * `[HerdingCats DECLARE] {"actor_id":..., "played_card_id":..., "declared_type":..., "actual_type":...}`
  * `[HerdingCats RESOLVE_CHALLENGE] {..., "challengers":[...]}`
    These make reproductions trivial.

If you want this as a patch instead of XML, say so and I will output a unified diff.
