<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * HerdingCats implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\HerdingCats;

class Game extends \Bga\GameFramework\Table
{
    /** @var mixed Deck component for card management */
    protected $cards;
    private static array $CARD_TYPES;
    private const G_PENDING_CARD = 'g_pending_card';
    private const G_PENDING_DECL = 'g_pending_decl';
    private const G_PENDING_ACTUAL = 'g_pending_actual';
    private const G_ACTOR = 'g_actor';
    private const G_TARGET_PLAYER = 'g_target_player';
    private const G_TARGET_ZONE = 'g_target_zone'; // 1 = hand, 2 = herd
    private const G_CHALLENGER = 'g_challenger';
    private const G_PENALTY_TO_RESOLVE = 'g_penalty_to_resolve';
    // Flag set when a targeted effect resolves as "ineffective" (e.g., Alley Cat vs Alley Cat)
    private const G_EFFECT_INEFFECTIVE = 'g_effect_ineffective';
    // Intercept globals
    private const G_INTERCEPT_BY = 'g_intercept_by';
    private const G_INTERCEPT_ZONE = 'g_intercept_zone'; // 1 = hand, 2 = herd, 0 = none
    private const G_INTERCEPT_CARD = 'g_intercept_card'; // card id used for intercept (may be dummy when no real deck)
    // Effect selected slot index (1-based) within the defender's hand/herd for resolution
    private const G_SELECTED_SLOT_INDEX = 'g_selected_slot_index';

    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If you want to store any type instead of int, use $this->globals instead.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        $this->initGameStateLabels([
            self::G_PENDING_CARD => 10,
            self::G_PENDING_DECL => 11,
            self::G_PENDING_ACTUAL => 17,
            self::G_ACTOR => 12,
            self::G_TARGET_PLAYER => 13,
            self::G_TARGET_ZONE => 14,
            self::G_CHALLENGER => 15,
            self::G_PENALTY_TO_RESOLVE => 16,
            self::G_EFFECT_INEFFECTIVE => 18,
            self::G_INTERCEPT_BY => 19,
            self::G_INTERCEPT_ZONE => 20,
            self::G_INTERCEPT_CARD => 21,
            self::G_SELECTED_SLOT_INDEX => 22,
        ]);

        // Initialize BGA Deck component (safe even if no real cards are used)
        try {
            $this->cards = $this->getNew('module.common.deck');
            if ($this->cards) {
                $this->cards->init('card');
            }
        } catch (\Throwable $e) {
            // Leave $cards null; code using it guards with fallbacks
            $this->cards = null;
        }

        self::$CARD_TYPES = [
            1 => [
                "card_name" => clienttranslate('Troll'), // ...
            ],
            2 => [
                "card_name" => clienttranslate('Goblin'), // ...
            ],
            // ...
        ];

        /* example of notification decorator.
        // automatically complete notification args when needed
        $this->notify->addDecorator(function(string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
        
            if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
                $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
                $args['i18n'][] = ['card_name'];
            }
            
            return $args;
        });*/
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    public function actPlayCard(int $card_id): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // check input values
        $args = $this->argPlayerTurn();
        $playableCardsIds = $args['playableCardsIds'];
        if (!in_array($card_id, $playableCardsIds)) {
            throw new \BgaUserException('Invalid card choice');
        }

        // Add your game logic to play a card here.
        $card_name = self::$CARD_TYPES[$card_id]['card_name'];

        // Notify all players about the card played.
        $this->notify->all("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
            "card_name" => $card_name, // remove this line if you uncomment notification decorator
            "card_id" => $card_id,
            "i18n" => ['card_name'], // remove this line if you uncomment notification decorator
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("playCard");
    }

    public function actPass(): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Notify all players about the choice to pass.
        $this->notify->all("pass", clienttranslate('${player_name} passes'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("pass");
    }

    /**
     * Game state arguments, example content.
     *
     * This method returns some additional information that is very specific to the `playerTurn` game state.
     *
     * @return array
     * @see ./states.inc.php
     */
    public function argPlayerTurn(): array
    {
        // Get some values from the current game situation from the database.

        return [
            "playableCardsIds" => [1, 2],
        ];
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function stNextPlayer(): void {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Give some extra time to the active player when he completed an action
        $this->giveExtraTime($player_id);
        
        $this->activeNextPlayer();

        // Go to another gamestate
        $gameEnd = false; // Here, we would detect if the game is over to make the appropriate transition
        if ($gameEnd) {
            $this->gamestate->nextState("endScore");
        } else {
            $this->gamestate->nextState("nextPlayer");
        }
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `stEndScore` is called just before the end of the game, 
     * if you keep `98 => GameStateBuilder::endScore()->build()` in the states.inc.php
     */
    public function stEndScore(): void {
        // Here, we would compute scores if they are not updated live, and compute average statistics

        $this->gamestate->nextState();
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the game to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use `DBPREFIX_<table_name>` for all tables
//
//            $sql = "ALTER TABLE `DBPREFIX_xxxxxxx` ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use `DBPREFIX_<table_name>` for all tables
//
//            $sql = "CREATE TABLE `DBPREFIX_xxxxxxx` ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    public function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        // Minimal placeholders for client UI; expand when rules are wired up
        $result['handCounts'] = $this->getHandCounts();
        $result['hand_counts'] = $result['handCounts']; // maintain legacy key expected by view

        // Provide the current player's hand in the exact order (real or stored dummy)
        $result['hand'] = $this->getHandList($current_player_id);

        // Minimal empty structures for herds/discards expected by client
        $result['herds'] = [];
        foreach ($result['players'] as $pid => $_p) {
            $result['herds'][(int)$pid] = [
                'face_down' => [],
                'face_up' => [],
            ];
        }
        $result['discards'] = [];
        foreach ($result['players'] as $pid => $_p) {
            $result['discards'][(int)$pid] = [];
        }

        return $result;
    }

    /**
     * Persistent hand counts stored in globals. In new framework, use $this->globals->get()/set().
     */
    private function getHandCounts(): array
    {
        $stored = $this->globals->get('hand_counts');
        if (!is_array($stored)) {
            $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
            $init = [];
            foreach ($players as $p) { $init[(int)$p['id']] = 7; }
            $this->globals->set('hand_counts', $init);
            return $init;
        }
        return $stored;
    }

    private function setHandCounts(array $counts): void
    {
        $this->globals->set('hand_counts', $counts);
    }

    private function adjustHandCount(int $playerId, int $delta): array
    {
        $counts = $this->getHandCounts();
        $current = $counts[$playerId] ?? 0;
        $counts[$playerId] = max(0, $current + $delta);
        $this->setHandCounts($counts);
        return $counts;
    }

    /**
     * Hand order tracking for dummy/no-deck mode: persists per-player hand arrays in UI order.
     */
    private function getHandOrders(): array
    {
        $orders = $this->globals->get('hand_orders');
        return is_array($orders) ? $orders : [];
    }

    private function setHandOrders(array $orders): void
    {
        $this->globals->set('hand_orders', $orders);
    }

    private function getHandList(int $playerId): array
    {
        // Prefer real deck when available
        if (is_object($this->cards)) {
            $assoc = $this->cards->getCardsInLocation('hand', $playerId);
            if (!empty($assoc)) {
                $list = array_values($assoc);
                usort($list, function ($a, $b) {
                    $pa = isset($a['location_arg']) ? (int)$a['location_arg'] : 0;
                    $pb = isset($b['location_arg']) ? (int)$b['location_arg'] : 0;
                    if ($pa === $pb) {
                        return ((int)$a['id']) <=> ((int)$b['id']);
                    }
                    return $pa <=> $pb;
                });
                return $list;
            }
        }
        // Fallback to stored dummy order initialized to current hand count
        $orders = $this->getHandOrders();
        if (!isset($orders[$playerId]) || !is_array($orders[$playerId]) || count($orders[$playerId]) === 0) {
            $counts = $this->getHandCounts();
            $n = $counts[$playerId] ?? 7;
            $orders[$playerId] = $this->getDummyHandFor($playerId, $n);
            $this->setHandOrders($orders);
        }
        // Normalize location_arg sequence
        $list = array_values($orders[$playerId]);
        $pos = 1;
        foreach ($list as &$c) { $c['location_arg'] = $pos++; }
        return $list;
    }

    private function setHandList(int $playerId, array $list): void
    {
        $orders = $this->getHandOrders();
        $norm = [];
        $pos = 1;
        foreach ($list as $c) {
            $cid = (int)$c['id'];
            $ctype = isset($c['type']) ? (int)$c['type'] : 0;
            $norm[(string)$cid] = [ 'id' => $cid, 'type' => $ctype, 'location_arg' => $pos++ ];
        }
        $orders[$playerId] = $norm;
        $this->setHandOrders($orders);
    }

    /**
     * Return target player's hand in the exact UI order the owner sees.
     * - If real cards exist in DB: sort by card_location_arg asc then card_id asc.
     * - If using placeholder dummy hands: return the dummy list in insertion order.
     */
    private function getOrderedHandFor(int $playerId): array
    {
        // Try real deck first
        $assoc = is_object($this->cards) ? $this->cards->getCardsInLocation('hand', $playerId) : [];
        if (!empty($assoc)) {
            $list = array_values($assoc);
            usort($list, function ($a, $b) {
                $pa = isset($a['location_arg']) ? (int)$a['location_arg'] : 0;
                $pb = isset($b['location_arg']) ? (int)$b['location_arg'] : 0;
                if ($pa === $pb) {
                    return ((int)$a['id']) <=> ((int)$b['id']);
                }
                return $pa <=> $pb;
            });
            return $list;
        }
        // Fallback to dummy hand sized to the tracked count
        $counts = $this->getHandCounts();
        $n = $counts[$playerId] ?? 0;
        return array_values($this->getDummyHandFor($playerId, $n));
    }

    private function getDummyHandFor(int $playerId, ?int $count = null): array
    {
        // Provide N dummy cards with ids and types for UI selection
        // Use stable default order matching the current UI expectation:
        // [Kitten, Kitten, Show Cat, Alley Cat, Catnip, Animal Control, Laser Pointer]
        $hand = [];
        $n = ($count === null) ? 7 : max(0, (int)$count);
        $base = 100 + ($playerId % 10) * 1000; // reduce cross-player collisions
        $defaultOrder = [1, 1, 2, 3, 4, 5, 6];
        for ($i = 0; $i < $n; $i++) {
            $cardId = $base + $i;
            $type = $defaultOrder[$i % count($defaultOrder)];
            $hand[(string)$cardId] = [ 'id' => $cardId, 'type' => $type, 'location_arg' => ($i + 1) ];
        }
        return $hand;
    }

    // =====================
    // Args methods (stubs) 
    // =====================

    public function argAwaitDeclaration(): array
    {
        // Provide minimal data to avoid framework errors on state entry
        return [
            'canDeclare' => true,
        ];
    }

    public function argChallengeWindow(): array
    {
        // All non-active players are eligible to challenge
        $active = (int) $this->getActivePlayerId();
        $players = $this->getCollectionFromDb("SELECT `player_id` `id`, `player_name` FROM `player`");
        $eligible = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            if ($pid !== $active) {
                $eligible[] = $pid;
            }
        }

        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        return [
            'eligible' => $eligible,
            'eligible_challengers' => $eligible,
            'can_challenge' => $eligible,
            'actor_id' => $active,
            'actor_name' => $this->getPlayerNameById($active),
            'acting_player_name' => $this->getPlayerNameById($active),
            'declared_card' => $decl,
        ];
    }

    public function argChallengerSelectBluffPenalty(): array
    {
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $hand = $this->getHandList($actor);
        return [
            'target_player_id' => $actor,
            'hand_count' => count($hand),
        ];
    }

    public function argAttackerSelectTruthfulPenalty(): array
    {
        // On a failed challenge (truthful declaration), the attacker selects a blind penalty
        // from the challenger(s). Use the stored challenger id, not the targeting value.
        $actor      = (int)$this->getGameStateValue(self::G_ACTOR);
        $challenger = (int)$this->getGameStateValue(self::G_CHALLENGER);

        // If there was no actual challenge but we have a pending effect penalty
        // (e.g., Alley Cat after defender passes intercept), target the selected defender.
        if ($challenger === 0 && (int)$this->getGameStateValue(self::G_PENALTY_TO_RESOLVE) === 1) {
            $challenger = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        }

        // Defensive guard: still unset → return minimal args (client will show a generic prompt)
        if ($challenger === 0) {
            return [
                'actor_id'    => $actor,
                'actor_name'  => $this->getPlayerNameById($actor),
                'challengers' => [],
            ];
        }

        $hand = $this->getHandList($challenger);
        $args = [
            // legacy fields some clients expect
            'target_player_id' => $challenger,
            'hand_count'       => count($hand),

            // primary fields
            'actor_id'        => $actor,
            'actor_name'      => $this->getPlayerNameById($actor),
            'challenger_id'   => $challenger,
            'challenger_name' => $this->getPlayerNameById($challenger),
        ];

        // Provide a challengers array for UIs that iterate
        $args['challengers'] = [[
            'player_id'  => $challenger,
            'hand_count' => count($hand),
        ]];

        return $args;
    }

    // removed placeholder argSelectTarget (see the fully implemented version below)

    // =====================
    // Minimal action flow  
    // =====================

    public function actDeclare(int $card_id, $declared_type, $target_player_id = null): void
    {
        $player_id = (int)$this->getActivePlayerId();
        $decl = (int)$declared_type;

        // Notify card played and persist hand counts
        $handCounts = $this->adjustHandCount($player_id, -1);

        $this->notify->all('cardPlayed', clienttranslate('${player_name} plays a card'), [
            'player_id' => $player_id,
            'player_name' => $this->getActivePlayerName(),
            'declared_type' => $decl,
            'card_id' => $card_id,
            'hand_counts' => $handCounts,
        ]);

        // Determine the actual card type from the player's current hand list (dummy or real)
        $actualType = 0;
        $handList = $this->getHandList($player_id);
        foreach ($handList as $c) {
            if ((int)$c['id'] === (int)$card_id) {
                $actualType = isset($c['type']) ? (int)$c['type'] : 0;
                break;
            }
        }

        // Store pending declaration info for downstream states
        $this->setGameStateValue(self::G_PENDING_CARD, $card_id);
        $this->setGameStateValue(self::G_PENDING_DECL, $decl);
        $this->setGameStateValue(self::G_PENDING_ACTUAL, $actualType);
        $this->setGameStateValue(self::G_ACTOR, $player_id);
        $this->setGameStateValue(self::G_TARGET_PLAYER, 0);
        $this->setGameStateValue(self::G_TARGET_ZONE, 0);
        $this->setGameStateValue(self::G_CHALLENGER, 0);
        $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 0);
        $this->setGameStateValue(self::G_EFFECT_INEFFECTIVE, 0);

        // Branch: targeted cards select opponent first; others go straight to challenge window
        if ($this->requiresTarget($decl)) {
            $this->gamestate->nextState('declaredToTarget');
        } else {
            $this->gamestate->nextState('declaredToChallenge');
        }

        // Maintain dummy hand order store: remove the selected card id from player's list
        $list = $this->getHandList($player_id);
        $index = null;
        foreach ($list as $i => $c) {
            if ((int)$c['id'] === (int)$card_id) { $index = $i; break; }
        }
        if ($index !== null) {
            array_splice($list, $index, 1);
            $this->setHandList($player_id, $list);
        }
    }

    public function stEnterChallengeWindow(): void
    {
        // Set all non-active players as multiactive challengers
        $active = (int) $this->getActivePlayerId();
        $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
        $eligible = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            if ($pid !== $active) {
                $eligible[] = $pid;
            }
        }
        if (!empty($eligible)) {
            $this->gamestate->setPlayersMultiactive($eligible, 'unchallenged', true);
        } else {
            // No challengers possible
            $this->gamestate->nextState('unchallenged');
        }
    }

    public function actChallenge(): void
    {
        // First challenger triggers the challenged transition
        $this->checkAction('actChallenge');
        // The challenger is the current player (not the active actor!)
        $challenger_id = (int)$this->getCurrentPlayerId();
        // Store challenger so we can reference in resolve/penalty state
        $this->setGameStateValue(self::G_CHALLENGER, $challenger_id);
        // Close the challenge window and proceed to resolve
        $this->gamestate->setAllPlayersNonMultiactive('challenged');
    }

    public function actPassChallenge(): void
    {
        $this->checkAction('actPassChallenge');
        $pid = (int)$this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'unchallenged');
    }

    // =====================
    // Target selection phase
    // =====================

    private function requiresTarget(int $declType): bool
    {
        // 1=Kitten, 2=ShowCat, 3=AlleyCat, 4=Catnip, 5=AnimalControl, 6=LaserPointer
        // Non-targeting: Kitten, Show Cat, Laser Pointer
        return in_array($declType, [3, 4, 5], true);
    }

    private function zoneCodeFromString(string $zone): int
    {
        return $zone === 'herd' ? 2 : 1; // default to hand=1
    }

    private function applyInterceptSubstitution(string $zone, int $defender, int $lpCardId): void
    {
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $decl  = (int)$this->getGameStateValue(self::G_PENDING_DECL);

        // Emit interceptApplied summary (no sensitive info)
        $this->notify->all('interceptApplied', '', [
            'effect_type' => $decl,
            'mode' => 'substitution',
            'zone' => $zone,
            'actor_id' => $actor,
            'defender_id' => $defender,
        ]);

        if ($decl === 3) {
            // Alley Cat: defender discards from hand; played Alley Cat goes to herd in stAddPlayedCardToHerd
            // Show the actual card type that was presented (even if bluffing), per current UX requirement.
            if ($zone === 'hand') {
                // Find actual type of the presented card id (fallback to LP if not found)
                $dtype = 6;
                $list = $this->getHandList($defender);
                foreach ($list as $i => $c) {
                    if ((int)$c['id'] === $lpCardId) {
                        $dtype = (int)($c['type'] ?? 6);
                        break;
                    }
                }
                $this->notify->all('cardRemoved', '', [ 'player_id' => $defender, 'card_id' => $lpCardId, 'from_zone' => 'hand' ]);
                $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $lpCardId, 'type' => $dtype ] ]);
                $counts = $this->adjustHandCount($defender, -1);
                $this->notify->all('handCountUpdate', '', [ 'hand_counts' => $counts ]);
                // Remove from dummy hand list if present
                foreach ($list as $i => $c) { if ((int)$c['id'] === $lpCardId) { array_splice($list, $i, 1); break; } }
                $this->setHandList($defender, $list);
            }
        } elseif ($decl === 4) {
            // Catnip: treat interception as a steal of the presented card from defender's hand.
            // Publicly it is id-only; privately the attacker learns the actual type of the presented card
            // (which may or may not be a real Laser Pointer if no one challenged).
            if ($zone === 'hand') {
                // Determine actual type of the presented card id (default to LP for safety)
                $dtype = 6;
                $list = $this->getHandList($defender);
                foreach ($list as $i => $c) {
                    if ((int)$c['id'] === $lpCardId) {
                        $dtype = (int)($c['type'] ?? 6);
                        break;
                    }
                }
                $this->notify->all('cardRemoved', '', [ 'player_id' => $defender, 'card_id' => $lpCardId, 'from_zone' => 'hand' ]);
                $this->notify->all('cardStolen', '', [
                    'from_player_id' => $defender,
                    'to_player_id' => $actor,
                    'card' => [ 'id' => $lpCardId ],
                    'hand_counts' => $this->adjustHandCount($defender, -1)
                ]);
                $this->notifyPlayer($actor, 'cardStolenPrivate', '', [
                    'from_player_id' => $defender,
                    'to_player_id' => $actor,
                    'card' => [ 'id' => $lpCardId, 'type' => $dtype ]
                ]);
                // Stats: Catnip steal via intercept substitution
                $this->incStat(1, 'cards_stolen_by_catnip', $actor);
                $this->incStat(1, 'cards_lost_to_catnip', $defender);
                $this->incStat(1, 'cards_stolen_total');
                foreach ($list as $i => $c) { if ((int)$c['id'] === $lpCardId) { array_splice($list, $i, 1); break; } }
                $this->setHandList($defender, $list);
            }
        } elseif ($decl === 5) {
            // Animal Control: discard defender LP from herd
            if ($zone === 'herd') {
                $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $lpCardId, 'type' => 6 ] ]);
            }
        }

        // Clear intercept globals (slot remains hidden; no reveal)
        $this->setGameStateValue(self::G_INTERCEPT_BY, 0);
        $this->setGameStateValue(self::G_INTERCEPT_ZONE, 0);
        $this->setGameStateValue(self::G_INTERCEPT_CARD, 0);
    }

    public function stEnterSelectTarget(): void
    {
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        if (!$this->requiresTarget($decl)) {
            $this->gamestate->nextState('noTargeting');
        }
        // otherwise, wait for player action
    }

    // =====================
    // Minimal state resolvers so flow proceeds
    // =====================

    public function stResolveChallenge(): void
    {
        // Use stored pending data to resolve challenge truthfully
        $actor      = (int)$this->getGameStateValue(self::G_ACTOR);
        $declared   = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $actualType = (int)$this->getGameStateValue(self::G_PENDING_ACTUAL);
        $challenger = (int)$this->getGameStateValue(self::G_CHALLENGER); // set in actChallenge

        if ($challenger === 0) {
            // Nobody challenged: route depending on whether target is required/selected
            if ($this->requiresTarget($declared)) {
                $currentTarget = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
                if ($currentTarget !== 0) {
                    // Proceed to effect slot selection (attacker chooses slot), then interceptor may react
                    $this->gamestate->changeActivePlayer($actor);
                    $this->gamestate->nextState('goToIntercept');
                } else {
                    $this->gamestate->nextState('goToTarget');
                }
            } else {
                $this->gamestate->nextState('goToResolve');
            }
            return;
        }

        $wasBluffing = ($declared !== $actualType);
        $this->notify->all('challengeResult', '', [
            'player_name'      => $this->getPlayerNameById($actor),
            'declared_type'    => $declared,
            'actual_card_type' => $actualType,
            'was_bluffing'     => $wasBluffing,
        ]);

        if ($wasBluffing) {
            // Discard the played card (no herd placement)
            $cardId = (int)$this->getGameStateValue(self::G_PENDING_CARD);
            if ($cardId > 0) {
                $this->notify->all('discardUpdate', '', [
                    'player_id' => $actor,
                    'card'      => [ 'id' => $cardId, 'type' => $actualType ],
                ]);
            }
            // Challenger wins: they pick a blind penalty from actor
            $this->gamestate->changeActivePlayer($challenger);
            $this->gamestate->nextState('bluffCaught');
        } else {
            // Actor was truthful: actor picks a blind penalty from challenger
            $this->gamestate->changeActivePlayer($actor);
            $this->gamestate->nextState('challengeFailed');
        }
    }

    public function stResolveInterceptChallenge(): void
    {
        $defender = (int)$this->getGameStateValue(self::G_INTERCEPT_BY);
        $zoneCode = (int)$this->getGameStateValue(self::G_INTERCEPT_ZONE); // 1=hand,2=herd
        $zone = $zoneCode === 2 ? 'herd' : 'hand';
        $cardId = (int)$this->getGameStateValue(self::G_INTERCEPT_CARD);
        $challenger = (int)$this->getGameStateValue(self::G_CHALLENGER); // single challenger model

        // Determine truth: for hand zone, check the presented card type; for herd, accept truthful in dummy mode
        $truth = true;
        if ($zone === 'hand') {
            $truth = false;
            $hand = $this->getHandList($defender);
            foreach ($hand as $c) {
                if ((int)$c['id'] === $cardId) { $truth = ((int)($c['type'] ?? 0) === 6); break; }
            }
        }
        if ($challenger === 0) {
            // No challengers → treat as truthful
            $truth = true;
        }

        // Publish a compact result notification for logs/tests (only when a challenge actually occurred)
        if ($challenger !== 0) {
            $this->notify->all('interceptChallengeResult', '', [
                'defender_id'        => $defender,
                'challenger_id'      => $challenger,
                'zone'               => $zone,
                'presented_card_id'  => $cardId,
                'was_bluffing'       => !$truth,
            ]);
        }

        if ($truth) {
            // Truthful intercept
            if ($challenger !== 0) {
                // Let defender select a blind penalty from the challenger
                $this->gamestate->changeActivePlayer($defender);
                $this->gamestate->nextState('interceptTruthPenalty');
                return;
            }
            // No challenger → apply substitution immediately
            $this->applyInterceptSubstitution($zone, $defender, $cardId);
            $this->gamestate->nextState('interceptSubstitutionApplied');
        } else {
            // Bluff caught: discard the falsely presented card; resume original effect (no extra blind penalty)
            if ($zone === 'hand') {
                // Remove from defender's hand
                $dhand = $this->getHandList($defender);
                $dtype = 0; $foundIdx = null;
                foreach ($dhand as $i => $c) { if ((int)$c['id'] === $cardId) { $dtype = (int)($c['type'] ?? 0); $foundIdx = $i; break; } }
                $this->notify->all('cardRemoved', '', [ 'player_id' => $defender, 'card_id' => $cardId, 'from_zone' => 'hand' ]);
                $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $cardId, 'type' => $dtype ] ]);
                if ($foundIdx !== null) {
                    $list = $this->getHandList($defender);
                    array_splice($list, $foundIdx, 1);
                    $this->setHandList($defender, $list);
                }
                $counts = $this->adjustHandCount($defender, -1);
                $this->notify->all('handCountUpdate', '', [ 'hand_counts' => $counts ]);
            } else {
                // Herd: just publish a discard for a Laser Pointer
                $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $cardId, 'type' => 6 ] ]);
            }

            // Clear intercept globals
            $this->setGameStateValue(self::G_INTERCEPT_BY, 0);
            $this->setGameStateValue(self::G_INTERCEPT_ZONE, 0);
            $this->setGameStateValue(self::G_INTERCEPT_CARD, 0);
            // Resume original effect with reveal
            $this->gamestate->nextState('interceptGoToResolve');
        }
    }

    public function argInterceptTruthfulPenalty(): array
    {
        $defender = (int)$this->getGameStateValue(self::G_INTERCEPT_BY);
        $challenger = (int)$this->getGameStateValue(self::G_CHALLENGER);
        $hand = $this->getHandList($challenger);
        return [
            'defender_id' => $defender,
            'defender' => $this->getPlayerNameById($defender),
            'challenger_id' => $challenger,
            'challenger' => $this->getPlayerNameById($challenger),
            'target_player_id' => $challenger,
            'hand_count' => count($hand),
            'challengers' => [[ 'player_id' => $challenger, 'hand_count' => count($hand) ]],
        ];
    }

    public function stRevealAndResolve(): void
    {
        // Always use the original actor for resolution/logs (active player may be defender or others)
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $decl  = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $targetPlayer = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        $targetZone   = (int)$this->getGameStateValue(self::G_TARGET_ZONE); // 1=hand,2=herd
        $selectedIdx  = (int)$this->getGameStateValue(self::G_SELECTED_SLOT_INDEX);
        $interceptor  = (int)$this->getGameStateValue(self::G_INTERCEPT_BY);

        // If an effect was marked ineffective (e.g., Alley Cat vs Alley Cat), skip effect previews
        $ineff = (int)$this->getGameStateValue(self::G_EFFECT_INEFFECTIVE) === 1;

        // If an intercept stands (unchallenged path routed here), apply substitution and skip reveal
        if ($interceptor !== 0) {
            $zone = $targetZone === 2 ? 'herd' : 'hand';
            $cardId = (int)$this->getGameStateValue(self::G_INTERCEPT_CARD);
            $this->applyInterceptSubstitution($zone, $interceptor, $cardId);
            // Continue to add played card to herd via next state
            $this->gamestate->nextState('effectResolved');
            return;
        }

        switch ($decl) {
            case 3: // Alley Cat
                if (!$ineff) {
                    $this->notify->all('alleyCatEffect', clienttranslate('${player_name} forces a discard from ${target_name}\'s hand'), [
                        'player_id'   => $actor,
                        'player_name' => $this->getPlayerNameById($actor),
                        'target_id'   => $targetPlayer,
                        'target_name' => $this->getPlayerNameById($targetPlayer),
                    ]);
                    // Reveal the selected slot and apply ineffective-if-Alley-Cat rule
                    if ($selectedIdx > 0) {
                        $hand = $this->getHandList($targetPlayer);
                        $idx = $selectedIdx - 1;
                        if (isset($hand[$idx])) {
                            $card = $hand[$idx];
                            $ctype = (int)($card['type'] ?? 0);
                            $cid = (int)$card['id'];
                            if ($ctype === 3) {
                                // Ineffective against itself
                                $this->notify->all('alleyCatIneffective', clienttranslate('Ineffective: ${target_name}\'s card is Alley Cat and returns to hand'), [
                                    'player_id'   => $actor,
                                    'player_name' => $this->getPlayerNameById($actor),
                                    'target_id'   => $targetPlayer,
                                    'target_name' => $this->getPlayerNameById($targetPlayer),
                                    'card'        => [ 'id' => $cid, 'type' => $ctype ],
                                ]);
                                $this->incStat(1, 'ineffective_attacks_due_to_matching', $actor);
                                $this->setGameStateValue(self::G_EFFECT_INEFFECTIVE, 1);
                            } else {
                                // Discard the revealed card
                                $this->notify->all('cardRemoved', '', [ 'player_id' => $targetPlayer, 'card_id' => $cid, 'from_zone' => 'hand' ]);
                                $this->notify->all('discardUpdate', '', [ 'player_id' => $targetPlayer, 'card' => [ 'id' => $cid, 'type' => $ctype ] ]);
                                $counts = $this->adjustHandCount($targetPlayer, -1);
                                $this->notify->all('handCountUpdate', '', [ 'hand_counts' => $counts ]);
                                // Update dummy list
                                $list = $this->getHandList($targetPlayer);
                                array_splice($list, $idx, 1);
                                $this->setHandList($targetPlayer, $list);
                            }
                        }
                    }
                }
                break;
            case 4: // Catnip
                $this->notify->all('catnipEffect', clienttranslate('${player_name} steals a card from ${target_name}\'s hand to herd'), [
                    'player_id'   => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'target_id'   => $targetPlayer,
                    'target_name' => $this->getPlayerNameById($targetPlayer),
                ]);
                if ($selectedIdx > 0) {
                    $hand = $this->getHandList($targetPlayer);
                    $idx = $selectedIdx - 1;
                    if (isset($hand[$idx])) {
                        $card = $hand[$idx];
                        $ctype = (int)($card['type'] ?? 0);
                        $cid = (int)$card['id'];
                        if ($ctype === 4) {
                            // Ineffective against itself: publicly reveal defender's Catnip; do not remove; attacker discards their played card later
                            $this->notify->all('catnipIneffective', clienttranslate('Ineffective: ${target_name}\'s card is Catnip and returns to hand'), [
                                'player_id'   => $actor,
                                'player_name' => $this->getPlayerNameById($actor),
                                'target_id'   => $targetPlayer,
                                'target_name' => $this->getPlayerNameById($targetPlayer),
                                'card'        => [ 'id' => $cid, 'type' => $ctype ],
                                'selected_slot_index' => $selectedIdx,
                            ]);
                            $this->incStat(1, 'ineffective_attacks_due_to_matching', $actor);
                            $this->setGameStateValue(self::G_EFFECT_INEFFECTIVE, 1);
                        } else {
                            // Normal steal: remove from defender, publish id-only publicly, send type privately to attacker
                            $this->notify->all('cardRemoved', '', [ 'player_id' => $targetPlayer, 'card_id' => $cid, 'from_zone' => 'hand' ]);
                            $this->notify->all('cardStolen', '', [
                                'from_player_id' => $targetPlayer,
                                'to_player_id' => $actor,
                                'card' => [ 'id' => $cid ],
                                'hand_counts' => $this->adjustHandCount($targetPlayer, -1)
                            ]);
                            $this->notifyPlayer($actor, 'cardStolenPrivate', '', [
                                'from_player_id' => $targetPlayer,
                                'to_player_id' => $actor,
                                'card' => [ 'id' => $cid, 'type' => $ctype ]
                            ]);
                            // Stats: Catnip steal (normal)
                            $this->incStat(1, 'cards_stolen_by_catnip', $actor);
                            $this->incStat(1, 'cards_lost_to_catnip', $targetPlayer);
                            $this->incStat(1, 'cards_stolen_total');
                            // Update dummy list
                            $list = $this->getHandList($targetPlayer);
                            array_splice($list, $idx, 1);
                            $this->setHandList($targetPlayer, $list);
                        }
                    }
                }
                break;
            case 5: // Animal Control
                $this->notify->all('animalControlEffect', clienttranslate('${player_name} removes a card from ${target_name}\'s herd'), [
                    'player_id'   => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'target_id'   => $targetPlayer,
                    'target_name' => $this->getPlayerNameById($targetPlayer),
                ]);
                // Fallback: without herd tracking, publish a generic discard from herd
                if ($selectedIdx > 0) {
                    $fakeId = 500000 + $selectedIdx;
                    $this->notify->all('discardUpdate', '', [ 'player_id' => $targetPlayer, 'card' => [ 'id' => $fakeId, 'type' => 0 ] ]);
                }
                break;
            default:
                // Non-targeting: no effect to apply here in this minimal pass
                break;
        }

        $this->gamestate->nextState('effectResolved');
    }

    public function stAddPlayedCardToHerd(): void
    {
        // Add the played card to herd (face-down); notify so all clients render it now
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $card  = (int)$this->getGameStateValue(self::G_PENDING_CARD);
        $decl  = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $actual = (int)$this->getGameStateValue(self::G_PENDING_ACTUAL);
        $ineff  = (int)$this->getGameStateValue(self::G_EFFECT_INEFFECTIVE) === 1;

        if ($ineff) {
            // Ineffective vs itself: discard the played card face-up instead of adding to herd
            $this->notify->all('discardUpdate', '', [
                'player_id' => $actor,
                'card' => [ 'id' => $card, 'type' => $actual ],
            ]);
        } else {
            // For face-down herd entries, do not include the type in public payloads
            $this->notify->all('herdUpdate', clienttranslate('Card added to herd'), [
                'player_id' => $actor,
                'player_name' => $this->getPlayerNameById($actor),
                'card' => [ 'id' => $card ],
                'visible' => false,
            ]);
        }

        // Clear intercept globals at end of resolution
        $this->setGameStateValue(self::G_INTERCEPT_BY, 0);
        $this->setGameStateValue(self::G_INTERCEPT_ZONE, 0);
        $this->setGameStateValue(self::G_INTERCEPT_CARD, 0);
        $this->setGameStateValue(self::G_SELECTED_SLOT_INDEX, 0);

        $this->gamestate->nextState('cardAdded');
    }

    public function actSelectBlindFromChallenger(int $player_id, int $card_index): void
    {
        $this->checkAction('actSelectBlindFromChallenger');
        $actor = (int)$this->getActivePlayerId();

        // If we are in one of the effect selection states, only record the slot (no reveal yet)
        $state = $this->gamestate->state();
        $stateName = is_array($state) && isset($state['name']) ? $state['name'] : '';
        if (in_array($stateName, ['alleyCatEffectSelect','catnipEffectSelect','animalControlEffectSelect'], true)) {
            $defender = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
            // Defensive: make sure the provided player_id matches defender
            if ($player_id !== $defender) {
                $player_id = $defender;
            }
            $this->setGameStateValue(self::G_SELECTED_SLOT_INDEX, (int)$card_index);
            $zoneCode = (int)$this->getGameStateValue(self::G_TARGET_ZONE); // 1=hand,2=herd
            $zone = $zoneCode === 2 ? 'herd' : 'hand';

            // Determine selected type for defender-only preview (hands only)
            $selectedType = null;
            if ($zone === 'hand') {
                $hand = $this->getHandList($defender);
                $idx = max(1, (int)$card_index) - 1;
                if (isset($hand[$idx]) && isset($hand[$idx]['type'])) {
                    $selectedType = (int)$hand[$idx]['type'];
                }
            }
            // Private defender preview
            $this->notifyPlayer($defender, 'defenderTargetPreview', '', [
                'selected_slot_index' => (int)$card_index,
                'selected_slot_type' => $selectedType,
                'zone' => $zone,
            ]);
            // Public announcement without type
            $this->notify->all('targetSlotSelected', '', [
                'target_player_id' => $defender,
                'selected_slot_index' => (int)$card_index,
                'zone' => $zone,
            ]);

            // Handover to defender to declare/pass intercept happens in a GAME router state
            // (changing active player during an ACTIVE_PLAYER state is forbidden by the engine).
            $this->gamestate->nextState('toIntercept');
            return;
        }

        // Determine the ordered hand as seen by the owner
        $realAssoc = is_object($this->cards) ? $this->cards->getCardsInLocation('hand', $player_id) : [];
        $hand = $this->getHandList($player_id);
        $n = count($hand);
        // Expect 1-based index from client (slot numbers 1..N)
        if ($card_index < 1 || $card_index > $n) {
            throw new \BgaUserException(self::_("Invalid slot index."));
        }
        $picked = $hand[$card_index - 1];
        $card_id = (int)$picked['id'];
        $card_type = isset($picked['type']) ? (int)$picked['type'] : 0;

        // Special-case: Alley Cat ineffective-vs-same-identity rule (legacy penalty/effect path)
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        // Detect Alley Cat main-effect selection (not a challenge penalty):
        // either flagged via G_PENALTY_TO_RESOLVE or implied by having no challenger
        $isAlleyEffect = ($decl === 3) && (
            ((int)$this->getGameStateValue(self::G_PENALTY_TO_RESOLVE) === 1)
            || ((int)$this->getGameStateValue(self::G_CHALLENGER) === 0)
        );

        if ($isAlleyEffect && $card_type === 3) {
            // Ineffective: reveal matches Alley Cat → return to hand, no discard
            $this->notify->all('alleyCatIneffective', clienttranslate('Ineffective: ${target_name}\'s card is Alley Cat and returns to hand'), [
                'player_id'   => $actor,
                'player_name' => $this->getPlayerNameById($actor),
                'target_id'   => $player_id,
                'target_name' => $this->getPlayerNameById($player_id),
                'card'        => [ 'id' => $card_id, 'type' => $card_type ],
            ]);
            // Track stat for actor
            $this->incStat(1, 'ineffective_attacks_due_to_matching', $actor);

            // Proceed to main resolution without changing defender hand
            $this->setGameStateValue(self::G_EFFECT_INEFFECTIVE, 1);
            $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 0);
            $this->gamestate->nextState('toResolve');
            return;
        }

        // If a real deck exists, move the card to discard; otherwise simulate with notifications
        if (!empty($realAssoc) && is_object($this->cards)) {
            $this->cards->moveCard($card_id, 'discard', $player_id);
        }

        // Notify removal and discard
        $this->notify->all('cardRemoved', '', [
            'player_id' => $player_id,
            'card_id'   => $card_id,
            'from_zone' => 'hand',
        ]);
        $this->notify->all('discardUpdate', '', [
            'player_id' => $player_id,
            'card' => [ 'id' => $card_id, 'type' => $card_type ],
        ]);

        // Hand counts (persisted placeholder)
        $counts = $this->adjustHandCount($player_id, -1);
        $this->notify->all('handCountUpdate', '', [ 'hand_counts' => $counts ]);

        // Update stored hand order list (dummy mode)
        if (empty($realAssoc)) {
            $list = $this->getHandList($player_id);
            array_splice($list, $card_index - 1, 1);
            $this->setHandList($player_id, $list);
        }

        // Log and state progression
        // For unchallenged Alley Cat effect selection, do not emit a "truthPenaltyApplied" log
        // to avoid confusing test flows and reviewers. Keep it for truthful-challenge penalties only.
        $declNow = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $isAlleyEffectLog = ($declNow === 3) && (
            ((int)$this->getGameStateValue(self::G_PENALTY_TO_RESOLVE) === 1)
            || ((int)$this->getGameStateValue(self::G_CHALLENGER) === 0)
        );
        if (!$isAlleyEffectLog) {
            $this->notify->all('truthPenaltyApplied', clienttranslate('${player_name} selects a penalty card from ${target_name}'), [
                'player_id'   => $actor,
                'player_name' => $this->getPlayerNameById($actor),
                'target_id'   => $player_id,
                'target_name' => $this->getPlayerNameById($player_id),
                'card_index'  => $card_index,
            ]);
        }
        if ($isAlleyEffect) {
            // Count as Alley Cat forced discard for the victim
            $this->incStat(1, 'cards_discarded_by_alley_cat', $player_id);
        }

        // Route depending on whether the declared card requires targeting
        if ($isAlleyEffect) {
            $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 0);
            $this->gamestate->nextState('toResolve');
            return;
        }
        if (!$this->requiresTarget($decl)) {
            $this->gamestate->nextState('toResolve');
        } else {
            $this->gamestate->nextState('penaltyApplied');
        }
    }

    public function actSelectBlindFromActor(int $card_index): void
    {
        $this->checkAction('actSelectBlindFromActor');
        $picker = (int)$this->getActivePlayerId();
        $fromPlayer = (int)$this->getGameStateValue(self::G_ACTOR);

        $realAssoc = is_object($this->cards) ? $this->cards->getCardsInLocation('hand', $fromPlayer) : [];
        $hand = $this->getHandList($fromPlayer);
        $n = count($hand);
        // Expect 1-based index from client (slot numbers 1..N)
        if ($card_index < 1 || $card_index > $n) {
            throw new \BgaUserException(self::_("Invalid slot index."));
        }
        $picked = $hand[$card_index - 1];
        $card_id = (int)$picked['id'];
        $card_type = isset($picked['type']) ? (int)$picked['type'] : 0;

        if (!empty($realAssoc) && is_object($this->cards)) {
            $this->cards->moveCard($card_id, 'discard', $fromPlayer);
        }

        $this->notify->all('cardRemoved', '', [
            'player_id' => $fromPlayer,
            'card_id'   => $card_id,
            'from_zone' => 'hand',
        ]);
        $this->notify->all('discardUpdate', '', [
            'player_id' => $fromPlayer,
            'card' => [ 'id' => $card_id, 'type' => $card_type ],
        ]);
        $counts = $this->adjustHandCount($fromPlayer, -1);
        $this->notify->all('handCountUpdate', '', [ 'hand_counts' => $counts ]);

        // Update stored hand order list (dummy mode)
        if (empty($realAssoc)) {
            $list = $this->getHandList($fromPlayer);
            array_splice($list, $card_index - 1, 1);
            $this->setHandList($fromPlayer, $list);
        }

        $this->notify->all('bluffPenaltyApplied', clienttranslate('${player_name} selects a penalty card from ${target_name}'), [
            'player_id'   => $picker,
            'player_name' => $this->getPlayerNameById($picker),
            'target_id'   => $fromPlayer,
            'target_name' => $this->getPlayerNameById($fromPlayer),
            'card_index'  => $card_index,
        ]);
        $this->gamestate->nextState('penaltyApplied');
    }

    public function argSelectTarget(): array
    {
        $active = (int)$this->getActivePlayerId();
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);

        if (!$this->requiresTarget($decl)) {
            return [
                'valid_targets' => [],
                'canSkip' => true,
                'declared_card' => $decl,
                'acting_player_id' => $active,
                'acting_player_name' => $this->getPlayerNameById($active),
            ];
        }

        $players = $this->getCollectionFromDb("SELECT `player_id` `id`, `player_name` `name` FROM `player`");
        $targets = [];

        foreach ($players as $p) {
            $pid = (int)$p['id'];
            $pname = $p['name'];

            if ($decl === 2) { // Show Cat: target own herd (placeholder)
                if ($pid === $active) {
                    $targets[] = [
                        'id' => $pid,
                        'player_id' => $pid,
                        'zone' => 'herd',
                        'name' => $pname . ' (Herd)'
                    ];
                }
                continue;
            }

            if ($pid === $active) continue; // other cards target opponents

            if (in_array($decl, [3, 4], true)) {
                // Alley Cat / Catnip: target opponent hand (blind)
                $targets[] = [
                    'id' => $pid,
                    'player_id' => $pid,
                    'zone' => 'hand',
                    'name' => $pname . ' (Hand)'
                ];
            } elseif ($decl === 5) {
                // Animal Control: target opponent herd
                $targets[] = [
                    'id' => $pid,
                    'player_id' => $pid,
                    'zone' => 'herd',
                    'name' => $pname . ' (Herd)'
                ];
            }
        }

        return [
            'valid_targets' => $targets,
            'canSkip' => false,
            'declared_card' => $decl,
            'acting_player_id' => $active,
            'acting_player_name' => $this->getPlayerNameById($active),
        ];
    }

    public function actSelectTargetSlot(int $id, string $zone): void
    {
        $this->checkAction('actSelectTargetSlot');
        $active = (int)$this->getActivePlayerId();

        // Only allow selecting a target during the selectTarget state.
        $state = $this->gamestate->state();
        $stateName = is_array($state) && isset($state['name']) ? $state['name'] : '';
        if ($stateName !== 'selectTarget') {
            throw new \BgaUserException(self::_("Invalid timing for target selection."));
        }

        // Validate that provided id corresponds to a seated player (defender), not a slot index.
        $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
        $validIds = array_map(function($p){ return (int)$p['id']; }, array_values($players));
        if (!in_array((int)$id, $validIds, true)) {
            throw new \BgaUserException(self::_("Invalid target player."));
        }

        // Validate zone is consistent with the declared card type
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $zone = ($zone === 'herd') ? 'herd' : 'hand';
        if (in_array($decl, [3,4], true) && $zone !== 'hand') {
            throw new \BgaUserException(self::_("Invalid target zone for this card."));
        }
        if ($decl === 5 && $zone !== 'herd') {
            throw new \BgaUserException(self::_("Invalid target zone for this card."));
        }

        // Persist target (player + zone)
        $this->setGameStateValue(self::G_TARGET_PLAYER, (int)$id);
        $this->setGameStateValue(self::G_TARGET_ZONE, $this->zoneCodeFromString($zone));

        // Notify selection
        $this->notify->all('targetSelected', clienttranslate('${player_name} selected a target'), [
            'player_id' => $active,
            'player_name' => $this->getPlayerNameById($active),
            'target_player_id' => (int)$id,
            'target_zone' => $zone,
        ]);

        $this->gamestate->nextState('targetSelected');
    }

    public function actSkipTargeting(): void
    {
        $this->checkAction('actSkipTargeting');
        $this->gamestate->nextState('noTargeting');
    }

    // Minimal end turn handler to rotate to next player
    public function stEndTurn(): void
    {
        // Rotate relative to the actor whose turn just ended, not whoever
        // happens to be active at this moment (e.g., challenger during penalties).
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        if ($actor > 0) {
            $this->gamestate->changeActivePlayer($actor);
        }

        // Clear pending turn data to avoid leakage across turns
        $this->setGameStateValue(self::G_PENDING_CARD, 0);
        $this->setGameStateValue(self::G_PENDING_DECL, 0);
        $this->setGameStateValue(self::G_PENDING_ACTUAL, 0);
        $this->setGameStateValue(self::G_ACTOR, 0);
        $this->setGameStateValue(self::G_TARGET_PLAYER, 0);
        $this->setGameStateValue(self::G_TARGET_ZONE, 0);
        $this->setGameStateValue(self::G_CHALLENGER, 0);
        $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 0);
        $this->setGameStateValue(self::G_EFFECT_INEFFECTIVE, 0);
        $this->setGameStateValue(self::G_INTERCEPT_BY, 0);
        $this->setGameStateValue(self::G_INTERCEPT_ZONE, 0);
        $this->setGameStateValue(self::G_INTERCEPT_CARD, 0);
        $this->setGameStateValue(self::G_SELECTED_SLOT_INDEX, 0);

        // Now advance to the next player in turn order
        $this->activeNextPlayer();
        $this->gamestate->nextState('nextPlayer');
    }

    // removed duplicate actSkipTargeting()

    public function argInterceptDeclare(): array
    {
        $target = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        $name = $target > 0 ? $this->getPlayerNameById($target) : '';
        $idx = (int)$this->getGameStateValue(self::G_SELECTED_SLOT_INDEX);
        $zoneCode = (int)$this->getGameStateValue(self::G_TARGET_ZONE);
        $zone = $zoneCode === 2 ? 'herd' : 'hand';
        return [
            'target_player' => $name,
            'defender_id' => $target,
            // Defender-only prompt will rely on a private notification, but include generic index
            'selected_slot_index' => $idx,
            'zone' => $zone,
        ];
    }

    public function argInterceptChallengeWindow(): array
    {
        // Defender is the player who declared the intercept; fallback to the targeted player
        $defenderId = (int)$this->getGameStateValue(self::G_INTERCEPT_BY);
        if ($defenderId === 0) {
            $defenderId = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        }

        // Any seated player other than the defender may challenge
        $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
        $eligible = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            if ($pid !== $defenderId) {
                $eligible[] = $pid;
            }
        }

        return [
            'defender' => $defenderId ? $this->getPlayerNameById($defenderId) : '',
            'defender_id' => $defenderId,
            'eligible' => $eligible,
            'eligible_challengers' => $eligible,
        ];
    }

    public function argInterceptChallengerSelectPenalty(): array
    {
        return [];
    }

    public function argEffectSelectCommon(): array
    {
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $defender = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        $zoneCode = (int)$this->getGameStateValue(self::G_TARGET_ZONE); // 1=hand,2=herd
        $handCount = 0;
        if ($zoneCode === 1) {
            $handCount = count($this->getHandList($defender));
        } else {
            // Fallback when herd model is not tracked: show at least 1 slot for selection UI
            $handCount = 1;
        }
        return [
            'actor_id' => $actor,
            'actor_name' => $this->getPlayerNameById($actor),
            'challengers' => [[ 'player_id' => $defender, 'hand_count' => $handCount ]],
        ];
    }

    // =============
    // Intercept actions (minimal)
    // =============
    public function actPassIntercept(): void
    {
        $this->checkAction('actPassIntercept');
        $this->gamestate->nextState('noIntercept');
    }

    public function stPrepareAttackerPenalty(): void
    {
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $this->gamestate->changeActivePlayer($actor);
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $target = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);

        // If we have a targeted effect with a selected defender and no effect slot picked yet,
        // route to the appropriate effect selection state; otherwise proceed to truthful penalty flow.
        if ($this->requiresTarget($decl) && $target !== 0 && (int)$this->getGameStateValue(self::G_SELECTED_SLOT_INDEX) === 0) {
            if ($decl === 3) {
                $this->gamestate->nextState('toEffectAlley');
                return;
            } elseif ($decl === 4) {
                $this->gamestate->nextState('toEffectCatnip');
                return;
            } elseif ($decl === 5) {
                $this->gamestate->nextState('toEffectAnimal');
                return;
            }
        }
        $this->gamestate->nextState('toPenalty');
    }

    /**
     * Router: switch active player to the defender after the attacker selected
     * a blind slot for a targeted effect (e.g., Alley Cat, Catnip, Animal Control),
     * then enter the intercept declaration state.
     *
     * Important: changeActivePlayer must not be called from an ACTIVE_PLAYER state
     * action handler. Using a GAME-type state avoids the engine error
     * "Impossible to change active player during activeplayer type state".
     */
    public function stPrepareInterceptDeclare(): void
    {
        $defender = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        if ($defender > 0) {
            $this->gamestate->changeActivePlayer($defender);
        }
        $this->gamestate->nextState('toIntercept');
    }

    public function actDeclareIntercept(int $card_id, string $zone): void
    {
        $this->checkAction('actDeclareIntercept');

        // Normalize zone
        $zone = ($zone === 'herd') ? 'herd' : 'hand';

        // Defender is the current active player at this state
        $defender = (int)$this->getActivePlayerId();

        // Validate for hand zone only (herd zone is accepted without strict validation in dummy mode)
        if ($zone === 'hand') {
            $hand = $this->getHandList($defender);
            $found = null;
            foreach ($hand as $c) {
                if ((int)$c['id'] === (int)$card_id) { $found = $c; break; }
            }
            if ($found === null) {
                throw new \BgaUserException(self::_("Selected card not found in hand"));
            }
            // Do not enforce Laser Pointer type here; truth is resolved in challenge window
        }

        // Store intercept globals; resolution happens in intercept challenge window or reveal
        $this->setGameStateValue(self::G_INTERCEPT_BY, $defender);
        $this->setGameStateValue(self::G_INTERCEPT_ZONE, $this->zoneCodeFromString($zone));
        $this->setGameStateValue(self::G_INTERCEPT_CARD, (int)$card_id);

        $this->gamestate->nextState('interceptDeclared');
    }

    public function actChallengeIntercept(): void
    {
        $this->checkAction('actChallengeIntercept');
        // First challenger only (single-challenger model)
        if ((int)$this->getGameStateValue(self::G_CHALLENGER) === 0) {
            $this->setGameStateValue(self::G_CHALLENGER, (int)$this->getCurrentPlayerId());
        }
        $this->gamestate->nextState('interceptChallenged');
    }

    public function actPassChallengeIntercept(): void
    {
        $this->checkAction('actPassChallengeIntercept');
        $pid = (int)$this->getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'interceptUnchallenged');
    }

    /**
     * MULTI router: set the eligible challenger(s) active for the intercept challenge window.
     */
    public function stEnterInterceptChallengeWindow(): void
    {
        // Defender is the one who declared intercept; any other seated player can challenge
        $defender = (int)$this->getGameStateValue(self::G_INTERCEPT_BY);
        if ($defender === 0) {
            $defender = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        }

        $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
        $eligible = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            if ($pid !== $defender) {
                $eligible[] = $pid;
            }
        }

        if (!empty($eligible)) {
            $this->gamestate->setPlayersMultiactive($eligible, 'interceptUnchallenged', true);
        } else {
            // No challengers: proceed as unchallenged
            $this->gamestate->nextState('interceptUnchallenged');
        }
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code. The default below is red/green/blue/orange/brown. The
        // number of colors defined here must correspond to the maximum number of players allowed for the gams.
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        // Create players based on generic information.
        //
        // NOTE: You can add extra field on player table in the database (see dbmodel.sql) and initialize
        // additional fields directly here.
        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        // Init global values with their initial values.

        // Initialize our pending/target labels
        $this->setGameStateInitialValue(self::G_PENDING_CARD, 0);
        $this->setGameStateInitialValue(self::G_PENDING_DECL, 0);
        $this->setGameStateInitialValue(self::G_ACTOR, 0);
        $this->setGameStateInitialValue(self::G_TARGET_PLAYER, 0);
        $this->setGameStateInitialValue(self::G_TARGET_ZONE, 0);
        $this->setGameStateInitialValue(self::G_EFFECT_INEFFECTIVE, 0);
        $this->setGameStateInitialValue(self::G_INTERCEPT_BY, 0);
        $this->setGameStateInitialValue(self::G_INTERCEPT_ZONE, 0);
        $this->setGameStateInitialValue(self::G_INTERCEPT_CARD, 0);
        $this->setGameStateInitialValue(self::G_SELECTED_SLOT_INDEX, 0);

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.

        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);

        // TODO: Setup the initial game situation here.

        // Activate first player once everything has been initialized and ready.
        $this->activeNextPlayer();
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    /**
     * Example of debug function.
     * Here, jump to a state you want to test (by default, jump to next player state)
     * You can trigger it on Studio using the Debug button on the right of the top bar.
     */
    public function debug_goToState(int $state = 3) {
        $this->gamestate->jumpToState($state);
    }

    /*
    Another example of debug function, to easily create situations you want to test.
    Here, put a card you want to test in your hand (assuming you use the Deck component).

    public function debug_setCardInHand(int $cardType, int $playerId) {
        $card = array_values($this->cards->getCardsOfType($cardType))[0];
        $this->cards->moveCard($card['id'], 'hand', $playerId);
    }
    */
}
