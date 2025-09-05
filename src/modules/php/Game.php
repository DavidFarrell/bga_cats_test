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
    private const G_SELECTED_SLOT_CARD_ID = 'g_selected_slot_card_id';

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
            self::G_SELECTED_SLOT_CARD_ID => 23,
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

        // Herds: expose FU with ids+types; FD with ids only (privacy)
        $result['herds'] = [];
        $store = $this->getHerds();
        foreach ($result['players'] as $pid => $_p) {
            $pid = (int)$pid;
            $entry = $store[$pid] ?? [ 'face_down' => [], 'face_up' => [] ];
            // Map FD to id-only
            $fd = [];
            foreach ($entry['face_down'] as $c) {
                $fd[] = [ 'id' => (int)$c['id'] ];
            }
            // Map FU to id+type (public)
            $fu = [];
            foreach ($entry['face_up'] as $c) {
                $fu[] = [ 'id' => (int)$c['id'], 'type' => (int)$c['type'] ];
            }
            $result['herds'][$pid] = [ 'face_down' => $fd, 'face_up' => $fu ];
        }
        $result['discards'] = [];
        foreach ($result['players'] as $pid => $_p) {
            $result['discards'][(int)$pid] = [];
        }

        // Owner-only: known identities for face-down herd cards (current viewer only)
        $known = $this->getKnownMap();
        $result['known_identities'] = isset($known[$current_player_id]) ? $known[$current_player_id] : [];

        // Convenience: id => score map
        $scores = [];
        foreach ($result['players'] as $pid => $row) {
            $scores[(int)$pid] = (int)($row['score'] ?? 0);
        }
        $result['scores'] = $scores;

        // Include final scoring payload during final presentation for reconnects
        $final = $this->globals->get('final_scoring');
        if (is_array($final) && !empty($final)) {
            $result['final_scoring'] = $final;
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
                // If a card has just been declared by this player and is pending resolution,
                // it is no longer selectable from hand on the client. Exclude it from the
                // server-side hand list as well to keep counts/slots in sync during penalties.
                $pendingOwner = (int)$this->getGameStateValue(self::G_ACTOR);
                $pendingId    = (int)$this->getGameStateValue(self::G_PENDING_CARD);
                if ($pendingId > 0 && $playerId === $pendingOwner) {
                    $list = array_values(array_filter($list, function($c) use ($pendingId) {
                        return (int)($c['id'] ?? 0) !== $pendingId;
                    }));
                }
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
    // Herd persistence helpers
    // =====================

    private function getHerds(): array
    {
        $store = $this->globals->get('herds');
        if (!is_array($store)) {
            $store = [];
            $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
            foreach ($players as $p) {
                $store[(int)$p['id']] = [ 'face_down' => [], 'face_up' => [] ];
            }
            $this->globals->set('herds', $store);
        }
        return $store;
    }

    private function setHerds(array $store): void
    {
        $this->globals->set('herds', $store);
    }

    private function ensureHerdEntry(array &$store, int $playerId): void
    {
        if (!isset($store[$playerId]) || !is_array($store[$playerId])) {
            $store[$playerId] = [ 'face_down' => [], 'face_up' => [] ];
        }
        if (!isset($store[$playerId]['face_down']) || !is_array($store[$playerId]['face_down'])) {
            $store[$playerId]['face_down'] = [];
        }
        if (!isset($store[$playerId]['face_up']) || !is_array($store[$playerId]['face_up'])) {
            $store[$playerId]['face_up'] = [];
        }
    }

    private function addHerdCard(int $playerId, int $cardId, int $type, bool $faceUp = false): void
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        // Remove pre-existing occurrences of the id to keep the store clean
        $store[$playerId]['face_down'] = array_values(array_filter($store[$playerId]['face_down'], fn($c) => (int)$c['id'] !== (int)$cardId));
        $store[$playerId]['face_up']   = array_values(array_filter($store[$playerId]['face_up'],   fn($c) => (int)$c['id'] !== (int)$cardId));
        if ($faceUp) {
            $store[$playerId]['face_up'][] = [ 'id' => (int)$cardId, 'type' => (int)$type ];
        } else {
            $store[$playerId]['face_down'][] = [ 'id' => (int)$cardId, 'type' => (int)$type ];
        }
        $this->setHerds($store);

        $cardPayload = [ 'id' => (int)$cardId ];
        if ($faceUp) { $cardPayload['type'] = (int)$type; }
        $this->notify->all('herdUpdate', '', [
            'player_id' => $playerId,
            'card' => $cardPayload,
            'visible' => $faceUp,
        ]);

        // Record owner-only identity for face-down additions and notify privately for live labeling
        if (!$faceUp) {
            $this->addKnown($playerId, (int)$cardId, (int)$type);
        }
    }

    private function removeHerdCard(int $playerId, int $cardId): array
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        $removed = [ 'type' => 0, 'faceUp' => false ];
        // Search face-up first
        foreach ($store[$playerId]['face_up'] as $i => $c) {
            if ((int)$c['id'] === (int)$cardId) {
                $removed = [ 'type' => (int)$c['type'], 'faceUp' => true ];
                array_splice($store[$playerId]['face_up'], $i, 1);
                $this->setHerds($store);
                $this->notify->all('cardRemoved', '', [ 'player_id' => $playerId, 'card_id' => (int)$cardId, 'from_zone' => 'herd_up' ]);
                $this->clearKnownByCard((int)$cardId);
                return $removed;
            }
        }
        // Then face-down
        foreach ($store[$playerId]['face_down'] as $i => $c) {
            if ((int)$c['id'] === (int)$cardId) {
                $removed = [ 'type' => (int)$c['type'], 'faceUp' => false ];
                array_splice($store[$playerId]['face_down'], $i, 1);
                $this->setHerds($store);
                $this->notify->all('cardRemoved', '', [ 'player_id' => $playerId, 'card_id' => (int)$cardId, 'from_zone' => 'herd_down' ]);
                $this->clearKnownByCard((int)$cardId);
                return $removed;
            }
        }
        // not found → no-op
        return $removed;
    }

    private function flipHerdCardUp(int $playerId, int $cardId): void
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        foreach ($store[$playerId]['face_down'] as $i => $c) {
            if ((int)$c['id'] === (int)$cardId) {
                $type = (int)$c['type'];
                // Remove from FD
                array_splice($store[$playerId]['face_down'], $i, 1);
                $this->notify->all('cardRemoved', '', [ 'player_id' => $playerId, 'card_id' => (int)$cardId, 'from_zone' => 'herd_down' ]);
                // Clear any stored owner-known identity
                $this->clearKnownByCard((int)$cardId);
                // Add to FU and notify
                $store[$playerId]['face_up'][] = [ 'id' => (int)$cardId, 'type' => $type ];
                $this->setHerds($store);
                $this->notify->all('herdUpdate', '', [ 'player_id' => $playerId, 'visible' => true, 'card' => [ 'id' => (int)$cardId, 'type' => $type ] ]);
                return;
            }
        }
        // If it was already FU or not found, no-op
    }

    private function countFaceDownHerd(int $playerId): int
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        return count($store[$playerId]['face_down']);
    }

    private function herdHasLP(int $playerId): bool
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        foreach ($store[$playerId]['face_up'] as $c) { if ((int)$c['type'] === 6) return true; }
        foreach ($store[$playerId]['face_down'] as $c) { if ((int)$c['type'] === 6) return true; }
        return false;
    }

    private function removeOneLaserPointer(int $playerId): array
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        // Prefer face-up
        foreach (['face_up', 'face_down'] as $zoneKey) {
            foreach ($store[$playerId][$zoneKey] as $i => $c) {
                if ((int)$c['type'] === 6) {
                    $id = (int)$c['id'];
                    $faceUp = ($zoneKey === 'face_up');
                    array_splice($store[$playerId][$zoneKey], $i, 1);
                    $this->setHerds($store);
                    $this->notify->all('cardRemoved', '', [ 'player_id' => $playerId, 'card_id' => $id, 'from_zone' => $faceUp ? 'herd_up' : 'herd_down' ]);
                    $this->clearKnownByCard($id);
                    $this->notify->all('discardUpdate', '', [ 'player_id' => $playerId, 'card' => [ 'id' => $id, 'type' => 6 ] ]);
                    return [ 'id' => $id, 'type' => 6, 'faceUp' => $faceUp ];
                }
            }
        }
        return [ 'id' => 0, 'type' => 0, 'faceUp' => false ];
    }

    private function getHerdSlotByIndex(int $playerId, int $index): array
    {
        $store = $this->getHerds();
        $this->ensureHerdEntry($store, $playerId);
        $idx = max(1, (int)$index) - 1;
        if (isset($store[$playerId]['face_down'][$idx])) {
            $c = $store[$playerId]['face_down'][$idx];
            return [ 'id' => (int)$c['id'], 'type' => (int)$c['type'] ];
        }
        return [];
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
            // Animal Control: consume the presented Laser Pointer from defender's herd (prefer the selected card)
            if ($zone === 'herd') {
                // Attempt to remove the presented card id; fallback to any LP if mismatch
                $store = $this->getHerds();
                $this->ensureHerdEntry($store, $defender);
                $ptype = null;
                foreach ($store[$defender]['face_up'] as $c) { if ((int)$c['id'] === $lpCardId) { $ptype = (int)$c['type']; break; } }
                if ($ptype === null) { foreach ($store[$defender]['face_down'] as $c) { if ((int)$c['id'] === $lpCardId) { $ptype = (int)$c['type']; break; } } }

                if ((int)$ptype === 6) {
                    // Presented card is actually a Laser Pointer → discard it
                    $this->removeHerdCard($defender, $lpCardId);
                    $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $lpCardId, 'type' => 6 ] ]);
                } else {
                    // Fallback: remove any Laser Pointer from herd; if none exists, discard the presented card
                    $removed = $this->removeOneLaserPointer($defender);
                    if ((int)($removed['id'] ?? 0) === 0) {
                        // No LP available in herd (unchallenged bluff). As a safety net, the presented card is
                        // sacrificed instead so the intercept is not free. Discard it face-up with its true type.
                        // This keeps the substitution model meaningful: the originally selected herd card remains
                        // hidden/untouched; the defender still loses a card.
                        $kind = null; $isFu = false;
                        foreach ($store[$defender]['face_up'] as $c) { if ((int)$c['id'] === $lpCardId) { $kind = (int)$c['type']; $isFu = true; break; } }
                        if ($kind === null) {
                            foreach ($store[$defender]['face_down'] as $c) { if ((int)$c['id'] === $lpCardId) { $kind = (int)$c['type']; break; } }
                        }
                        // Remove from herd store and notify
                        $this->removeHerdCard($defender, $lpCardId);
                        if ($kind !== null) {
                            $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $lpCardId, 'type' => (int)$kind ] ]);
                        }
                        // Optional: track a failed-intercept-without-LP stat
                        $this->incStat(1, 'failed_intercepts', $defender);
                    }
                }
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

        // Determine truth:
        // - hand zone: truthful iff the presented hand card is Laser Pointer
        // - herd zone: truthful iff the presented herd card is Laser Pointer
        $truth = true;
        if ($zone === 'hand') {
            $truth = false;
            $hand = $this->getHandList($defender);
            foreach ($hand as $c) {
                if ((int)$c['id'] === $cardId) { $truth = ((int)($c['type'] ?? 0) === 6); break; }
            }
        } else {
            $store = $this->getHerds();
            $this->ensureHerdEntry($store, $defender);
            $ptype = null;
            foreach ($store[$defender]['face_down'] as $c) { if ((int)$c['id'] === $cardId) { $ptype = (int)$c['type']; break; } }
            if ($ptype === null) { foreach ($store[$defender]['face_up'] as $c) { if ((int)$c['id'] === $cardId) { $ptype = (int)$c['type']; break; } } }
            $truth = ((int)$ptype === 6);
        }
        if ($challenger === 0) {
            // No challengers → treat as truthful
            $truth = true;
        }

        // Publish a compact result notification for logs/tests (only when a challenge actually occurred)
        if ($challenger !== 0) {
            $payload = [
                'defender_id'   => $defender,
                'challenger_id' => $challenger,
                'zone'          => $zone,
                'was_bluffing'  => !$truth,
            ];
            if ($zone === 'hand') {
                $payload['presented_card_id'] = $cardId;
            } else {
                $payload['presented_card_id'] = $cardId; // presented herd card id
                $payload['presented_slot_index'] = (int)$this->getGameStateValue(self::G_SELECTED_SLOT_INDEX);
            }
            $this->notify->all('interceptChallengeResult', '', $payload);
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
                // Herd bluff: remove the presented herd card id and discard it (penalty for failed bluff)
                $removed = $this->removeHerdCard($defender, $cardId);
                $rtype = (int)($removed['type'] ?? 0);
                if ($rtype > 0) {
                    $this->notify->all('discardUpdate', '', [ 'player_id' => $defender, 'card' => [ 'id' => $cardId, 'type' => $rtype ] ]);
                }
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
                            // Add stolen card to actor's herd face-down (public payload id-only)
                            $this->addHerdCard($actor, $cid, $ctype, false);
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
                // Announce the attempt before outcome is known
                $this->notify->all('animalControlEffect', clienttranslate('${player_name} attempts to remove a card from ${target_name}\'s herd'), [
                    'player_id'   => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'target_id'   => $targetPlayer,
                    'target_name' => $this->getPlayerNameById($targetPlayer),
                ]);
                if ($selectedIdx > 0) {
                    // Reveal the defender's face-down slot using stored card id when available to avoid index drift
                    $selCardId = (int)$this->getGameStateValue(self::G_SELECTED_SLOT_CARD_ID);
                    $slot = [];
                    if ($selCardId > 0) {
                        $store = $this->getHerds();
                        $this->ensureHerdEntry($store, $targetPlayer);
                        foreach ($store[$targetPlayer]['face_down'] as $c) { if ((int)$c['id'] === $selCardId) { $slot = [ 'id' => (int)$c['id'], 'type' => (int)$c['type'] ]; break; } }
                    }
                    if (empty($slot)) {
                        // Fallback to original index
                        $slot = $this->getHerdSlotByIndex($targetPlayer, $selectedIdx);
                    }
                    if (!empty($slot)) {
                        $cid = (int)$slot['id'];
                        $ctype = (int)$slot['type'];
                        if ($ctype === 5) {
                            // Ineffective vs itself: flip the selected card face up
                            $this->flipHerdCardUp($targetPlayer, $cid);
                            // Explicit ineffective notification to remove ambiguity in logs/UI
                            $this->notify->all('animalControlIneffective', clienttranslate('Ineffective: ${target_name}\'s card is Animal Control and becomes protected'), [
                                'player_id'   => $actor,
                                'player_name' => $this->getPlayerNameById($actor),
                                'target_id'   => $targetPlayer,
                                'target_name' => $this->getPlayerNameById($targetPlayer),
                                'card'        => [ 'id' => $cid, 'type' => $ctype ],
                                'ineffective_against_itself' => true,
                            ]);
                            $this->setGameStateValue(self::G_EFFECT_INEFFECTIVE, 1);
                        } else {
                            // Remove the FD herd card and discard it with actual type
                            $removed = $this->removeHerdCard($targetPlayer, $cid);
                            $this->notify->all('discardUpdate', '', [ 'player_id' => $targetPlayer, 'card' => [ 'id' => $cid, 'type' => (int)($removed['type'] ?? $ctype) ] ]);
                            // Explicit success notification to complement the initial "attempts" message
                            $this->notify->all('animalControlRemoved', clienttranslate('${player_name} removes a card from ${target_name}\'s herd'), [
                                'player_id'   => $actor,
                                'player_name' => $this->getPlayerNameById($actor),
                                'target_id'   => $targetPlayer,
                                'target_name' => $this->getPlayerNameById($targetPlayer),
                                'card'        => [ 'id' => $cid, 'type' => (int)($removed['type'] ?? $ctype) ],
                            ]);
                        }
                    }
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
            // Persist and notify via helper; FD adds do not reveal type
            $this->addHerdCard($actor, $card, $actual, false);
        }

        // Clear intercept globals at end of resolution
        $this->setGameStateValue(self::G_INTERCEPT_BY, 0);
        $this->setGameStateValue(self::G_INTERCEPT_ZONE, 0);
        $this->setGameStateValue(self::G_INTERCEPT_CARD, 0);
        $this->setGameStateValue(self::G_SELECTED_SLOT_INDEX, 0);
        $this->setGameStateValue(self::G_SELECTED_SLOT_CARD_ID, 0);

        $this->gamestate->nextState('cardAdded');
    }

    // ==============================
    // Owner-known FD identity store
    // ==============================
    private function getKnownMap(): array
    {
        $m = $this->globals->get('known_fd_identities');
        return is_array($m) ? $m : [];
    }

    private function setKnownMap(array $map): void
    {
        $this->globals->set('known_fd_identities', $map);
    }

    private function addKnown(int $ownerId, int $cardId, int $type): void
    {
        $map = $this->getKnownMap();
        if (!isset($map[$ownerId]) || !is_array($map[$ownerId])) { $map[$ownerId] = []; }
        $map[$ownerId][(int)$cardId] = (int)$type;
        $this->setKnownMap($map);
        // Private notify to owner so label appears live without refresh
        $this->notifyPlayer($ownerId, 'fdKnown', '', [ 'card' => [ 'id' => (int)$cardId, 'type' => (int)$type ] ]);
    }

    private function clearKnownByCard(int $cardId): void
    {
        $map = $this->getKnownMap();
        $changed = false;
        foreach ($map as $pid => $entries) {
            if (isset($entries[(int)$cardId])) { unset($map[$pid][(int)$cardId]); $changed = true; }
        }
        if ($changed) { $this->setKnownMap($map); }
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
            $zoneCode = (int)$this->getGameStateValue(self::G_TARGET_ZONE); // 1=hand,2=herd
            $zone = $zoneCode === 2 ? 'herd' : 'hand';

            // Guard: when targeting herd, must have at least one face-down card
            if ($zone === 'herd' && $this->countFaceDownHerd($defender) <= 0) {
                throw new \BgaUserException(self::_("No face-down herd cards to select."));
            }

            $this->setGameStateValue(self::G_SELECTED_SLOT_INDEX, (int)$card_index);

            // Determine selected card and type for defender-only preview
            // For hand: reveal the hand card type to the owner (defender) only.
            // For herd: reveal the actual FD herd card type to its owner (defender) only.
            $selectedType = null;
            $selectedCardId = null;
            if ($zone === 'hand') {
                $hand = $this->getHandList($defender);
                $idx = max(1, (int)$card_index) - 1;
                if (isset($hand[$idx])) {
                    $selectedCardId = isset($hand[$idx]['id']) ? (int)$hand[$idx]['id'] : null;
                    if (isset($hand[$idx]['type'])) {
                        $selectedType = (int)$hand[$idx]['type'];
                    }
                }
            } else { // herd
                $slot = $this->getHerdSlotByIndex($defender, (int)$card_index);
                if (!empty($slot)) {
                    $selectedCardId = isset($slot['id']) ? (int)$slot['id'] : null;
                    $selectedType = isset($slot['type']) ? (int)$slot['type'] : null;
                }
            }
            // Store the selected slot card id for stable targeting across penalties
            $this->setGameStateValue(self::G_SELECTED_SLOT_CARD_ID, (int)($selectedCardId ?? 0));

            // Private defender preview
            $this->notifyPlayer($defender, 'defenderTargetPreview', '', [
                'selected_slot_index' => (int)$card_index,
                'selected_slot_type' => $selectedType,
                'selected_slot_card_id' => $selectedCardId,
                'zone' => $zone,
                'target_player_id' => $defender,
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
        $this->setGameStateValue(self::G_SELECTED_SLOT_CARD_ID, 0);

        // End-of-game check: if any player has 0 cards in hand, trigger final scoring
        $handCounts = $this->getHandCounts();
        $anyZero = false;
        foreach ($handCounts as $pid => $n) {
            if ((int)$n === 0) { $anyZero = true; break; }
        }

        if ($anyZero) {
            // Compute final scoring and persist results
            $final = $this->computeFinalScoring();

            // Persist totals to DB so that BGA end screen shows correct scores
            if (isset($final['scores']) && is_array($final['scores'])) {
                foreach ($final['scores'] as $pid => $total) {
                    $pid = (int)$pid; $total = (int)$total;
                    self::DbQuery("UPDATE `player` SET `player_score` = {$total} WHERE `player_id` = {$pid}");
                }
            }

            // Persist to globals for reconnects
            $final['ended_at_ts'] = time();
            $this->globals->set('final_scoring', $final);
            $acks = [];
            foreach ($this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`") as $p) {
                $acks[(int)$p['id']] = false;
            }
            $this->globals->set('final_ack', $acks);

            // Notify clients with rich table + scores map (legacy handler updates counters)
            $this->notify->all('finalScoring', '', $final);
            $this->notify->all('gameEnd', clienttranslate('Game Over! Final scores calculated.'), [
                'scores' => $final['scores'] ?? []
            ]);

            // Transition to final presentation (acknowledgement) state
            $this->gamestate->nextState('gameEnd');
            return;
        }

        // Otherwise, advance to the next player in turn order
        $this->activeNextPlayer();
        $this->gamestate->nextState('nextPlayer');
    }

    // ========= Final Scoring helpers and state =========

    /**
     * Build per-player herd counts across both face-down and face-up cards.
     * @return array<int, array<int,int>> Map: playerId => { type => count }
     */
    private function computeHerdCounts(): array
    {
        $store = $this->getHerds();
        $players = $this->getCollectionFromDb("SELECT `player_id` `id` FROM `player`");
        $result = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            $result[$pid] = [1=>0,2=>0,3=>0,4=>0,5=>0,6=>0];
            $entry = $store[$pid] ?? [ 'face_down' => [], 'face_up' => [] ];
            foreach (['face_down','face_up'] as $zone) {
                foreach ($entry[$zone] as $c) {
                    $t = (int)($c['type'] ?? 0);
                    if ($t >= 1 && $t <= 6) { $result[$pid][$t]++; }
                }
            }
        }
        return $result;
    }

    /**
     * Compute herd points given counts per type. Show Cat is 5 each normally, or 7 each if player has ≥1 Kitten.
     */
    private function computeHerdPoints(array $counts): int
    {
        // Base values from CARD_POINTS (fallback if constant is not defined on server)
        $total = 0;
        $points = $this->getCardPoints();
        $kittens = (int)($counts[1] ?? 0);
        $showcats = (int)($counts[2] ?? 0);
        // Types: 1..6
        foreach ([3,4,5,6] as $t) {
            $n = (int)($counts[$t] ?? 0);
            $pts = (int)($points[$t] ?? 0);
            $total += $n * $pts;
        }
        // Kittens
        $total += $kittens * (int)($points[1] ?? 0);
        // Show Cats with conditional bonus
        if ($showcats > 0) {
            $showVal = ($kittens > 0) ? (defined('SHOWCAT_KITTEN_BONUS_VALUE') ? (int)SHOWCAT_KITTEN_BONUS_VALUE : 7)
                                      : (defined('SHOWCAT_BASE_VALUE') ? (int)SHOWCAT_BASE_VALUE : 5);
            $total += $showcats * $showVal;
        }
        return $total;
    }

    /**
     * Hand bonus: +1 point per 2 cards, rounded up.
     */
    private function computeHandBonus(int $handCount): int
    {
        if ($handCount <= 0) return 0;
        return (int)ceil($handCount / 2.0);
    }

    /**
     * Compute final scoring rows and totals for all players.
     * @return array{ rows: array, scores: array<int,int>, card_points: array<int,int> }
     */
    private function computeFinalScoring(): array
    {
        $players = $this->getCollectionFromDb("SELECT `player_id` `id`, `player_name` `name` FROM `player`");
        $countsMap = $this->computeHerdCounts();
        $handCounts = $this->getHandCounts();

        $rows = [];
        $scores = [];
        foreach ($players as $p) {
            $pid = (int)$p['id'];
            $name = (string)$p['name'];
            $counts = $countsMap[$pid] ?? [1=>0,2=>0,3=>0,4=>0,5=>0,6=>0];
            $herdPts = $this->computeHerdPoints($counts);
            $handN = (int)($handCounts[$pid] ?? 0);
            $handBonus = $this->computeHandBonus($handN);
            $total = $herdPts + $handBonus;
            $rows[] = [
                'player_id' => $pid,
                'player_name' => $name,
                'herd_counts' => $counts,
                'herd_points' => $herdPts,
                'hand_count' => $handN,
                'hand_bonus' => $handBonus,
                'total_points' => $total,
                'showcat_bonus_applied' => ((int)$counts[2] > 0 && (int)$counts[1] > 0),
            ];
            $scores[$pid] = $total;
        }

        // Provide a copy of card points for client context (with safe fallback)
        $cardPoints = $this->getCardPoints();

        return [
            'rows' => $rows,
            'scores' => $scores,
            'card_points' => $cardPoints,
        ];
    }

    /**
     * Return card point values, using global constant if available, otherwise a safe fallback.
     * @return array<int,int>
     */
    private function getCardPoints(): array
    {
        if (defined('CARD_POINTS')) {
            // @phpstan-ignore-next-line - CARD_POINTS is a global const in material.inc.php when available
            return CARD_POINTS;
        }
        return [
            1 => 2, // Kitten
            2 => 5, // Show Cat (base; bonus handled separately)
            3 => 1, // Alley Cat
            4 => 1, // Catnip
            5 => 0, // Animal Control
            6 => 0, // Laser Pointer
        ];
    }

    public function stEnterFinalPresentation(): void
    {
        // Make everyone multiactive; proceed to end when all acknowledged
        $this->gamestate->setAllPlayersMultiactive();
    }

    public function argFinalPresentation(): array
    {
        $payload = $this->globals->get('final_scoring');
        return is_array($payload) ? $payload : [];
    }

    public function actAcknowledgeFinal(): void
    {
        $this->checkAction('actAcknowledgeFinal');
        $pid = (int)$this->getCurrentPlayerId();
        $ack = $this->globals->get('final_ack');
        if (!is_array($ack)) { $ack = []; }
        $ack[$pid] = true;
        $this->globals->set('final_ack', $ack);
        // Deactivate this player; if last, transition to end
        $this->gamestate->setPlayerNonMultiactive($pid, 'toEnd');
        $this->gamestate->updateMultiactiveOrNextState('toEnd');
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
        $zone = ($zoneCode === 2) ? 'herd' : 'hand';
        $handCount = 0;
        $fdCount = 0;
        if ($zone === 'hand') {
            $handCount = count($this->getHandList($defender));
        } else {
            // Real herd count from store for gating
            $fdCount = $this->countFaceDownHerd($defender);
        }
        $entry = [ 'player_id' => $defender ];
        if ($zone === 'hand') { $entry['hand_count'] = $handCount; }
        if ($zone === 'herd') { $entry['fd_count'] = $fdCount; }

        return [
            'actor_id' => $actor,
            'actor_name' => $this->getPlayerNameById($actor),
            'defender_id' => $defender,
            'zone' => $zone,
            'fd_count' => $fdCount,
            'challengers' => [ $entry ],
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
        } else { // herd
            // Require selecting a face-down herd card id belonging to defender
            $store = $this->getHerds();
            $this->ensureHerdEntry($store, $defender);
            $ok = false;
            foreach ($store[$defender]['face_down'] as $c) { if ((int)$c['id'] === (int)$card_id) { $ok = true; break; } }
            if (!$ok) {
                throw new \BgaUserException(self::_("Select a face-down herd card to present."));
            }
            // Additional rule: cannot present the exact card that was originally targeted by Animal Control
            $selIdx = (int)$this->getGameStateValue(self::G_SELECTED_SLOT_INDEX);
            if ($selIdx > 0) {
                $targetSlot = $this->getHerdSlotByIndex($defender, $selIdx);
                if (!empty($targetSlot) && (int)$targetSlot['id'] === (int)$card_id) {
                    throw new \BgaUserException(self::_("You cannot present the targeted card for intercept."));
                }
            }
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
