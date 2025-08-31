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
    private static array $CARD_TYPES;
    private const G_PENDING_CARD = 'g_pending_card';
    private const G_PENDING_DECL = 'g_pending_decl';
    private const G_ACTOR = 'g_actor';
    private const G_TARGET_PLAYER = 'g_target_player';
    private const G_TARGET_ZONE = 'g_target_zone'; // 1 = hand, 2 = herd
    private const G_CHALLENGER = 'g_challenger';
    private const G_PENALTY_TO_RESOLVE = 'g_penalty_to_resolve';

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
            self::G_ACTOR => 12,
            self::G_TARGET_PLAYER => 13,
            self::G_TARGET_ZONE => 14,
            self::G_CHALLENGER => 15,
            self::G_PENALTY_TO_RESOLVE => 16,
        ]);

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

        // Provide a dummy hand for the current player so UI can select a card
        $result['hand'] = $this->getDummyHandFor($current_player_id);

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

    private function getDummyHandFor(int $playerId): array
    {
        // Provide 7 dummy cards with ids and types for UI selection
        $hand = [];
        $id = 100;
        for ($i = 0; $i < 7; $i++) {
            $cardId = $id + $i;
            $type = ($i % 6) + 1; // 1..6 rotate
            $hand[(string)$cardId] = [ 'id' => $cardId, 'type' => $type ];
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
        return [
            'target_player_id' => $actor,
            'hand_count' => 7,
        ];
    }

    public function argAttackerSelectTruthfulPenalty(): array
    {
        // On a failed challenge (truthful declaration), the attacker selects a blind penalty
        // from the challenger(s). Use the stored challenger id, not the targeting value.
        $actor      = (int)$this->getGameStateValue(self::G_ACTOR);
        $challenger = (int)$this->getGameStateValue(self::G_CHALLENGER);

        // Defensive guard: if challenger is unset, return minimal args to avoid 0-id lookups
        if ($challenger === 0) {
            return [
                'actor_id'   => $actor,
                'actor_name' => $this->getPlayerNameById($actor),
                'challengers' => [],
            ];
        }

        $args = [
            // legacy fields some clients expect
            'target_player_id' => $challenger,
            'hand_count'       => 7,

            // primary fields
            'actor_id'        => $actor,
            'actor_name'      => $this->getPlayerNameById($actor),
            'challenger_id'   => $challenger,
            'challenger_name' => $this->getPlayerNameById($challenger),
        ];

        // Provide a challengers array for UIs that iterate
        $args['challengers'] = [[
            'player_id'  => $challenger,
            'hand_count' => 7,
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

        // Store pending declaration info for downstream states
        $this->setGameStateValue(self::G_PENDING_CARD, $card_id);
        $this->setGameStateValue(self::G_PENDING_DECL, $decl);
        $this->setGameStateValue(self::G_ACTOR, $player_id);
        $this->setGameStateValue(self::G_TARGET_PLAYER, 0);
        $this->setGameStateValue(self::G_TARGET_ZONE, 0);
        $this->setGameStateValue(self::G_CHALLENGER, 0);
        $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 0);

        // Branch: targeted cards select opponent first; others go straight to challenge window
        if ($this->requiresTarget($decl)) {
            $this->gamestate->nextState('declaredToTarget');
        } else {
            $this->gamestate->nextState('declaredToChallenge');
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
        // Minimal resolution for prototype: use stored pending + target to drive next state
        $actor     = (int)$this->getGameStateValue(self::G_ACTOR);
        $declared  = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $challenger = (int)$this->getGameStateValue(self::G_CHALLENGER); // set in actChallenge

        if ($challenger === 0) {
            // Nobody challenged: route depending on whether target is required/selected
            if ($this->requiresTarget($declared)) {
                $currentTarget = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
                if ($currentTarget !== 0) {
                    // Defender should decide to intercept; make them active
                    $this->gamestate->changeActivePlayer($currentTarget);
                    $this->gamestate->nextState('goToIntercept');
                } else {
                    $this->gamestate->nextState('goToTarget');
                }
            } else {
                $this->gamestate->nextState('goToResolve');
            }
            return;
        }

        // Force a loss for the challenger in this prototype so we can reach penalty UI
        // i.e., the declaration is considered truthful
        $this->notify->all('challengeResult', '', [
            'player_name'    => $this->getPlayerNameById($actor),
            'declared_type'  => $declared,
            'was_bluffing'   => false,
        ]);

        // Active player (actor) may discard one card from opponent hand
        $this->gamestate->changeActivePlayer($actor);
        $this->gamestate->nextState('challengeFailed');
    }

    public function stResolveInterceptChallenge(): void
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
            $this->notify->all('discardPublic', clienttranslate('${player_name} discards a Laser Pointer to intercept'), [
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
                    $this->notify->all('discardPublic', clienttranslate('${victim} discards a blind card due to intercept'), [
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
            $this->notify->all('discardPublic', clienttranslate('${player_name} discards the falsely presented card'), [
                'player_id' => $defender,
                'player_name' => $this->getPlayerNameById($defender),
                'card' => $card
            ]);

            $hand = array_values($this->cards->getCardsInLocation('hand', $defender));
            if (count($hand) > 0) {
                $pick = $hand[bga_rand(0, count($hand)-1)];
                $this->cards->moveCard($pick['id'], 'discard', $defender);
                $this->notify->all('discardPublic', clienttranslate('${player_name} also discards a blind card due to a wrong intercept claim'), [
                    'player_id' => $defender,
                    'player_name' => $this->getPlayerNameById($defender),
                    'card' => $pick
                ]);
            }
            // Original attack resumes
            $this->gamestate->nextState('toRevealAndResolve');
        }
    }

    public function stRevealAndResolve(): void
    {
        $actor = (int)$this->getActivePlayerId();
        $decl  = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        $targetPlayer = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        $targetZone   = (int)$this->getGameStateValue(self::G_TARGET_ZONE); // 1=hand,2=herd

        switch ($decl) {
            case 3: // Alley Cat
                $this->notify->all('alleyCatEffect', clienttranslate('${player_name} forces a discard from ${target_name}\'s hand'), [
                    'player_id'   => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'target_id'   => $targetPlayer,
                    'target_name' => $this->getPlayerNameById($targetPlayer),
                ]);
                break;
            case 4: // Catnip
                $this->notify->all('catnipEffect', clienttranslate('${player_name} steals a card from ${target_name}\'s hand to herd'), [
                    'player_id'   => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'target_id'   => $targetPlayer,
                    'target_name' => $this->getPlayerNameById($targetPlayer),
                ]);
                break;
            case 5: // Animal Control
                $this->notify->all('animalControlEffect', clienttranslate('${player_name} removes a card from ${target_name}\'s herd'), [
                    'player_id'   => $actor,
                    'player_name' => $this->getPlayerNameById($actor),
                    'target_id'   => $targetPlayer,
                    'target_name' => $this->getPlayerNameById($targetPlayer),
                ]);
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

        $this->notify->all('herdUpdate', clienttranslate('Card added to herd'), [
            'player_id' => $actor,
            'player_name' => $this->getPlayerNameById($actor),
            'card' => [ 'id' => $card, 'type' => $decl ],
            'visible' => false,
        ]);

        $this->gamestate->nextState('cardAdded');
    }

    public function actSelectBlindFromChallenger(int $player_id, int $card_index): void
    {
        $this->checkAction('actSelectBlindFromChallenger');
        $actor = (int)$this->getActivePlayerId();
        // Derive a dummy card id/type for the target's hand (ids 100..)
        $card_id = 100 + max(0, $card_index);
        $card_type = (($card_id - 100) % 6) + 1;

        // Notify removal from target's hand and add to discard
        $this->notify->all('cardRemoved', '', [
            'player_id' => $player_id,
            'card_id'   => $card_id,
            'from_zone' => 'hand',
        ]);
        $this->notify->all('discardUpdate', '', [
            'player_id' => $player_id,
            'card' => [ 'id' => $card_id, 'type' => $card_type ],
        ]);
        // Hand counts (persisted)
        $counts = $this->adjustHandCount($player_id, -1);
        $this->notify->all('handCountUpdate', '', [ 'hand_counts' => $counts ]);

        // Log
        $this->notify->all('truthPenaltyApplied', clienttranslate('${player_name} selects a penalty card from ${target_name}'), [
            'player_id'   => $actor,
            'player_name' => $this->getPlayerNameById($actor),
            'target_id'   => $player_id,
            'target_name' => $this->getPlayerNameById($player_id),
            'card_index'  => $card_index,
        ]);
        // Route depending on whether the declared card requires targeting
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        if ((int)$this->getGameStateValue(self::G_PENALTY_TO_RESOLVE) === 1) {
            $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 0);
            $this->gamestate->nextState('toResolve');
            return;
        }

        if (!$this->requiresTarget($decl)) {
            // Non-targeting cards (e.g., Kitten): proceed to resolve/add to herd
            $this->gamestate->nextState('toResolve');
        } else {
            // Targeted cards: continue to intercept/resolve flow
            $this->gamestate->nextState('penaltyApplied');
        }
    }

    public function actSelectBlindFromActor(int $card_index): void
    {
        $this->checkAction('actSelectBlindFromActor');
        $actor = (int)$this->getActivePlayerId();
        $fromPlayer = (int)$this->getGameStateValue(self::G_ACTOR);

        // Derive dummy id/type for actor's hand
        $card_id = 100 + max(0, $card_index);
        $card_type = (($card_id - 100) % 6) + 1;

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

        $this->notify->all('bluffPenaltyApplied', clienttranslate('${player_name} selects a penalty card from ${target_name}'), [
            'player_id'   => $actor,
            'player_name' => $this->getPlayerNameById($actor),
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

    public function actSelectTargetSlot(int $slot_id, string $zone): void
    {
        $this->checkAction('actSelectTargetSlot');
        $active = (int)$this->getActivePlayerId();
        $this->setGameStateValue(self::G_TARGET_PLAYER, $slot_id);
        $this->setGameStateValue(self::G_TARGET_ZONE, $this->zoneCodeFromString($zone));

        // Notify minimal selection (optional)
        $this->notify->all('targetSelected', clienttranslate('${player_name} selected a target'), [
            'player_id' => $active,
            'player_name' => $this->getPlayerNameById($active),
            'target_player_id' => $slot_id,
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
        $this->activeNextPlayer();
        $this->gamestate->nextState('nextPlayer');
    }

    // removed duplicate actSkipTargeting()

    public function argInterceptDeclare(): array
    {
        $target = (int)$this->getGameStateValue(self::G_TARGET_PLAYER);
        // Avoid calling getPlayerNameById with 0 when no target is set
        $name = $target > 0 ? $this->getPlayerNameById($target) : '';
        return [
            'target_player' => $name,
        ];
    }

    public function argInterceptChallengeWindow(): array
    {
        return [
            'eligible' => [],
        ];
    }

    public function argInterceptChallengerSelectPenalty(): array
    {
        return [];
    }

    // =============
    // Intercept actions (minimal)
    // =============
    public function actPassIntercept(): void
    {
        $this->checkAction('actPassIntercept');
        // If Alley Cat was declared, attacker chooses a blind card from target's hand
        $decl = (int)$this->getGameStateValue(self::G_PENDING_DECL);
        if ($decl === 3) { // Alley Cat
            $this->setGameStateValue(self::G_PENALTY_TO_RESOLVE, 1);
            // Switch active player in a dedicated GAME state (safe in engine)
            $this->gamestate->nextState('noInterceptPenalty');
            return;
        }
        $this->gamestate->nextState('noIntercept');
    }

    public function stPrepareAttackerPenalty(): void
    {
        $actor = (int)$this->getGameStateValue(self::G_ACTOR);
        $this->gamestate->changeActivePlayer($actor);
        $this->gamestate->nextState('toPenalty');
    }

    public function actDeclareIntercept(): void
    {
        $this->checkAction('actDeclareIntercept');
        // For now, just proceed to intercept challenge window without card verification
        $this->gamestate->nextState('interceptDeclared');
    }

    public function actChallengeIntercept(): void
    {
        $this->checkAction('actChallengeIntercept');
        // Minimal: treat as no challengers and continue
        $this->gamestate->nextState('interceptUnchallenged');
    }

    public function actPassChallengeIntercept(): void
    {
        $this->checkAction('actPassChallengeIntercept');
        $this->gamestate->nextState('interceptUnchallenged');
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
