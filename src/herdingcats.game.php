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
 * herdingcats.game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to define the rules of the game.
 */

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');
require_once('material.inc.php');

class HerdingCats extends Table
{
    function __construct()
    {
        // Your global variables labels:
        // Here, you can assign labels to global variables you are using for this game.
        // You can use any number of global variables with IDs between 10 and 99.
        // If your game has options (variants), you also have to associate here a label to
        // the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();

        self::initGameStateLabels([
            "current_declared_card" => 10,
            "current_declared_identity" => 11,
            "current_target_player" => 12,
            "current_action_id" => 13,
            "game_phase" => 14,
        ]);

        // Create deck component for managing cards
        $this->cards = self::getNew("module.common.deck");
        $this->cards->init("card");
    }

    protected function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "herdingcats";
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players with HTML color code
        // The default below is red/green/blue/orange/brown
        // The number of colors defined here must correspond to the maximum number of players allowed for the game
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        $sql = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES ";
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $values[] = "('" . $player_id . "','" . $color . "','" . $player['player_canal'] . "','" . addslashes($player['player_name']) . "','" . addslashes($player['player_avatar']) . "')";
        }
        $sql .= implode(',', $values);
        self::DbQuery($sql);
        self::reattributeColorsBasedOnPreferences($players, $gameinfos['player_colors']);
        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        // Init global values with their initial values
        self::setGameStateInitialValue('current_declared_card', 0);
        self::setGameStateInitialValue('current_declared_identity', 0);
        self::setGameStateInitialValue('current_target_player', 0);
        self::setGameStateInitialValue('current_action_id', 0);
        self::setGameStateInitialValue('game_phase', 0);

        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.json file)
        //self::initStat('table', 'table_teststat1', 0);    // Init a table statistics
        //self::initStat('player', 'player_teststat1', 0);  // Init a player statistics

        // Initialize pending_action table with empty row
        self::DbQuery("INSERT INTO pending_action (id) VALUES (1)");

        // Setup deck for each player - each player gets their OWN 9-card subset
        $all_cards = [];
        foreach ($players as $player_id => $player) {
            // Create 9-card deck per player using DECK_PER_PLAYER constant
            foreach (DECK_PER_PLAYER as $card_type => $count) {
                for ($i = 0; $i < $count; $i++) {
                    $all_cards[] = [
                        'type' => $card_type,
                        'type_arg' => $player_id, // Each card belongs to a specific player
                        'nbr' => 1
                    ];
                }
            }
        }
        
        // Create all cards in deck
        $this->cards->createCards($all_cards, CARD_LOCATION_DECK);
        
        // Now deal cards for each player separately
        foreach ($players as $player_id => $player) {
            // Get all cards that belong to this player
            $all_cards_in_deck = $this->cards->getCardsInLocation(CARD_LOCATION_DECK);
            $player_cards = [];
            
            foreach ($all_cards_in_deck as $card) {
                if ($card['type_arg'] == $player_id) {
                    $player_cards[] = $card;
                }
            }
            
            // Shuffle this player's cards
            shuffle($player_cards);
            
            // Deal 7 cards to hand for this player
            for ($i = 0; $i < STARTING_HAND_SIZE && $i < count($player_cards); $i++) {
                $this->cards->moveCard($player_cards[$i]['id'], CARD_LOCATION_HAND, $player_id);
            }
            
            // Remove 2 cards per player  
            for ($i = STARTING_HAND_SIZE; $i < STARTING_HAND_SIZE + CARDS_REMOVED_PER_PLAYER && $i < count($player_cards); $i++) {
                $this->cards->moveCard($player_cards[$i]['id'], CARD_LOCATION_REMOVED, $player_id);
            }
        }

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    /*
        getAllDatas: 
        
        Gather all information about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas()
    {
        $result = [];

        $current_player_id = self::getCurrentPlayerId();    // !! We must only return information visible by this player !!

        // Get information about players
        // Note: you can retrieve some extra field you added for "player" table in "dbmodel.sql" if you need it.
        $sql = "SELECT player_id id, player_score score FROM player ";
        $result['players'] = self::getCollectionFromDb($sql);

        // TODO: Gather all information about current game situation (visible by player $current_player_id).

        // EDGE CASE FIX: Make sure limbo cards are never visible in getAllDatas
        // Get current player's hand (only visible to them) - privacy fix
        $result['hand'] = $this->cards->getCardsInLocation(CARD_LOCATION_HAND, $current_player_id);
        
        // For all other players, only return hand count, not actual cards

        // Get all herds (face-up and face-down counts)
        $result['herds'] = [];
        foreach ($result['players'] as $player_id => $player_info) {
            $herd_down = $this->cards->getCardsInLocation(CARD_LOCATION_HERD_DOWN, $player_id);
            $herd_up = $this->cards->getCardsInLocation(CARD_LOCATION_HERD_UP, $player_id);
            
            $result['herds'][$player_id] = [
                'face_down_count' => count($herd_down),
                'face_up_cards' => $herd_up
            ];
        }

        // Get all discard piles
        $result['discards'] = [];
        foreach ($result['players'] as $player_id => $player_info) {
            $result['discards'][$player_id] = $this->cards->getCardsInLocation(CARD_LOCATION_DISCARD, $player_id);
        }

        // Get hand counts for all players
        $result['hand_counts'] = [];
        foreach ($result['players'] as $player_id => $player_info) {
            $result['hand_counts'][$player_id] = $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id);
        }

        // Get current game phase and pending actions if any
        $result['game_phase'] = self::getGameStateValue('game_phase');
        $current_action_id = self::getGameStateValue('current_action_id');
        if ($current_action_id > 0) {
            $result['current_action'] = $this->pullPending(); // Use proper pending action retrieval
        }

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression()
    {
        // TODO: compute and return the game progression
        
        // Calculate based on cards played
        $total_cards_in_hands = 0;
        $total_starting_cards = 0;
        
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $player_id => $player_info) {
            $cards_in_hand = $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id);
            $total_cards_in_hands += $cards_in_hand;
            $total_starting_cards += STARTING_HAND_SIZE;
        }
        
        // Progress based on how many cards have been played from hands
        $cards_played = $total_starting_cards - $total_cards_in_hands;
        $progression = ($cards_played * 100) / $total_starting_cards;
        
        return min(100, max(0, $progression));
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    /*
        In this space, you can put any utility methods useful for your game logic
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Stage 3: Pending Action Management
    ////////////

    /**
     * Store declaration/challenge/intercept data in pending_action table
     * @param array $data - Action data (actor_id, card_id, declared_type, target_player_id, etc.)
     * @return int - Action ID for tracking
     */
    function pushPending($data)
    {
        // Get the current game ID
        $game_id = intval(self::getGameId());
        
        // Prepare SQL values using correct database field names
        $actor_id = intval($data['actor_id']);
        $declared_identity = isset($data['declared_type']) ? "'" . addslashes($data['declared_type']) . "'" : 'NULL';
        $played_card_id = isset($data['card_id']) ? intval($data['card_id']) : 'NULL';
        $target_player_id = isset($data['target_player_id']) ? intval($data['target_player_id']) : 'NULL';
        $target_zone = isset($data['target_zone']) ? "'" . addslashes($data['target_zone']) . "'" : 'NULL';
        $phase = isset($data['phase']) ? "'" . addslashes($data['phase']) . "'" : "'declaration'";
        
        $sql = "INSERT INTO pending_action (game_id, actor_player_id, declared_identity, played_card_id, target_player_id, target_zone, phase) 
                VALUES ($game_id, $actor_id, $declared_identity, $played_card_id, $target_player_id, $target_zone, $phase)";
        self::DbQuery($sql);
        
        // Get the auto-generated action_id
        $action_id = self::DbGetLastId();
        
        // Store action ID in global state for tracking
        self::setGameStateValue('current_action_id', $action_id);
        
        return $action_id;
    }

    /**
     * Retrieve current pending action from database
     * @return array|null - Pending action data or null if none exists
     */
    function pullPending()
    {
        $current_action_id = self::getGameStateValue('current_action_id');
        if ($current_action_id == 0) {
            return null;
        }
        
        $sql = "SELECT * FROM pending_action WHERE action_id = $current_action_id";
        $result = self::getObjectFromDB($sql);
        
        if ($result && isset($result['challengers'])) {
            // Decode JSON arrays if they exist
            $result['challengers'] = !empty($result['challengers']) ? json_decode($result['challengers'], true) : [];
        }
        if ($result && isset($result['intercept_challengers'])) {
            $result['intercept_challengers'] = !empty($result['intercept_challengers']) ? json_decode($result['intercept_challengers'], true) : [];
        }
        
        return $result;
    }

    /**
     * Clear pending action after resolution
     */
    function clearPending()
    {
        $current_action_id = self::getGameStateValue('current_action_id');
        if ($current_action_id > 0) {
            $sql = "DELETE FROM pending_action WHERE action_id = $current_action_id";
            self::DbQuery($sql);
            
            // Reset global state
            self::setGameStateValue('current_action_id', 0);
            self::setGameStateValue('current_declared_card', 0);
            self::setGameStateValue('current_declared_identity', 0);
            self::setGameStateValue('current_target_player', 0);
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Stage 3: Card Utility Functions
    ////////////

    /**
     * Get card name from type constant
     * @param int $type - Card type constant
     * @return string - Translated card name
     */
    function getCardName($type)
    {
        switch ($type) {
            case CARD_TYPE_KITTEN:
                return clienttranslate('Kitten');
            case CARD_TYPE_SHOWCAT:
                return clienttranslate('Show Cat');
            case CARD_TYPE_ALLEYCAT:
                return clienttranslate('Alley Cat');
            case CARD_TYPE_CATNIP:
                return clienttranslate('Catnip');
            case CARD_TYPE_ANIMALCONTROL:
                return clienttranslate('Animal Control');
            case CARD_TYPE_LASERPOINTER:
                return clienttranslate('Laser Pointer');
            default:
                return clienttranslate('Unknown Card');
        }
    }

    /**
     * Check if card type requires target selection
     * @param int $type - Card type constant
     * @return bool - True if card requires target
     */
    function isTargetedType($type)
    {
        return in_array($type, [CARD_TYPE_ALLEYCAT, CARD_TYPE_CATNIP, CARD_TYPE_ANIMALCONTROL]);
    }

    /**
     * Get target zone for card type (hand/herd)
     * @param int $type - Card type constant
     * @return string|null - Target zone or null if not targeted
     */
    function getTargetZone($type)
    {
        switch ($type) {
            case CARD_TYPE_ALLEYCAT:
            case CARD_TYPE_CATNIP:
                return TARGET_ZONE_HAND;
            case CARD_TYPE_ANIMALCONTROL:
                return TARGET_ZONE_HERD;
            default:
                return null;
        }
    }

    /**
     * Add card to herd face-down with declared identity
     * @param int $card_id - Card to add to herd
     * @param int $player_id - Owner of the herd
     * @param int $declared_type - What the card was declared as
     */
    function addToHerdFaceDownAs($card_id, $player_id, $declared_type)
    {
        // Move card to herd_down location
        $this->cards->moveCard($card_id, CARD_LOCATION_HERD_DOWN, $player_id);
        
        // Store declared type using the correct database field name
        $sql = "UPDATE card SET card_declared_identity = '$declared_type' WHERE card_id = $card_id";
        self::DbQuery($sql);
    }

    /**
     * Get all herd cards for a player (both face-up and face-down)
     * @param int $player_id - Player ID
     * @return array - Array with 'face_down' and 'face_up' card arrays
     */
    function getPlayerHerdCards($player_id)
    {
        // Get face-down cards with declared identity
        $sql = "SELECT * FROM card WHERE card_location = '" . CARD_LOCATION_HERD_DOWN . "' AND card_location_arg = $player_id";
        $face_down = self::getCollectionFromDb($sql);
        
        $face_up = $this->cards->getCardsInLocation(CARD_LOCATION_HERD_UP, $player_id);
        
        return [
            'face_down' => $face_down,
            'face_up' => $face_up
        ];
    }

    /**
     * Get hand cards for a player (private data)
     * @param int $player_id - Player ID
     * @return array - Hand cards
     */
    function getPlayerHandCards($player_id)
    {
        return $this->cards->getCardsInLocation(CARD_LOCATION_HAND, $player_id);
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Stage 3: Notification Helpers
    ////////////

    /**
     * Notify all players about current hand counts
     */
    function notifyHandCounts()
    {
        $players = self::loadPlayersBasicInfos();
        $hand_counts = [];
        
        foreach ($players as $player_id => $player_info) {
            $hand_counts[$player_id] = $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id);
        }
        
        self::notifyAllPlayers('handCountUpdate', '', [
            'hand_counts' => $hand_counts
        ]);
    }

    /**
     * Notify about herd changes for a specific player
     * @param int $player_id - Player whose herd changed
     */
    function notifyHerdUpdate($player_id)
    {
        $herd_data = $this->getPlayerHerdCards($player_id);
        
        // Send full data to the herd owner (including declared identities)
        self::notifyPlayer($player_id, 'herdUpdate', '', [
            'player_id' => $player_id,
            'face_down_count' => count($herd_data['face_down']),
            'face_down_cards' => $herd_data['face_down'], // Owner can see declared identities
            'face_up_cards' => $herd_data['face_up']
        ]);
        
        // Send limited data to other players (no declared identities)
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $other_player_id => $player_info) {
            if ($other_player_id != $player_id) {
                self::notifyPlayer($other_player_id, 'herdUpdate', '', [
                    'player_id' => $player_id,
                    'face_down_count' => count($herd_data['face_down']),
                    'face_up_cards' => $herd_data['face_up']
                ]);
            }
        }
    }

    /**
     * Notify about discard pile changes for a specific player
     * @param int $player_id - Player whose discard changed
     */
    function notifyDiscardUpdate($player_id)
    {
        $discard_cards = $this->cards->getCardsInLocation(CARD_LOCATION_DISCARD, $player_id);
        
        self::notifyAllPlayers('discardUpdate', '', [
            'player_id' => $player_id,
            'discard_cards' => $discard_cards
        ]);
    }

    /**
     * Notify private card information to specific player
     * @param int $player_id - Player to notify
     * @param int $card_id - Card to reveal
     * @param string $context - Context of reveal (challenge, effect, etc.)
     */
    function notifyPrivateCardReveal($player_id, $card_id, $context = 'reveal')
    {
        $card = $this->cards->getCard($card_id);
        if ($card) {
            self::notifyPlayer($player_id, 'privateCardRevealed',
                clienttranslate('Card revealed: ${card_name}'),
                [
                    'card_id' => $card_id,
                    'card_type' => $card['type'],
                    'card_name' => $this->getCardName($card['type']),
                    'context' => $context
                ]
            );
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Stage 3: Validation Helpers
    ////////////

    /**
     * Check if a player can challenge an action
     * @param int $player_id - Player attempting to challenge
     * @param int $actor_id - Player who made the original action
     * @return bool - True if challenge is valid
     */
    function canPlayerChallenge($player_id, $actor_id)
    {
        // Cannot challenge yourself
        if ($player_id == $actor_id) {
            return false;
        }
        
        // SIMPLIFIED: Allow all other players to challenge for testing
        // Original: return $this->hasCardsInHand($player_id);
        return true;  // Allow all other players to challenge
    }

    /**
     * Validate hand target selection for card effects
     * @param int $target_player_id - Player being targeted
     * @param int $acting_card_type - Type of card being played
     * @return bool - True if target is valid
     */
    function validateHandTarget($target_player_id, $acting_card_type)
    {
        // Check if this card type can target hands
        $expected_zone = $this->getTargetZone($acting_card_type);
        if ($expected_zone !== TARGET_ZONE_HAND) {
            return false;
        }
        
        // Check if target player has cards in hand
        return $this->hasCardsInHand($target_player_id);
    }
    
    /**
     * Validate herd target selection for card effects
     * @param int $target_card_id - Specific card being targeted
     * @param int $acting_card_type - Type of card being played
     * @return bool - True if target is valid
     */
    function validateHerdTarget($target_card_id, $acting_card_type)
    {
        // Check if this card type can target herds
        $expected_zone = $this->getTargetZone($acting_card_type);
        if ($expected_zone !== TARGET_ZONE_HERD) {
            return false;
        }
        
        // Check if target card exists and is face-down in herd
        $card = $this->cards->getCard($target_card_id);
        return $card && $card['location'] === CARD_LOCATION_HERD_DOWN;
    }

    /**
     * Check if player has cards in hand
     * @param int $player_id - Player to check
     * @return bool - True if player has cards
     */
    function hasCardsInHand($player_id)
    {
        return $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id) > 0;
    }

    /**
     * Get player name by ID
     * @param int $player_id - Player ID
     * @return string - Player name
     */
    function getPlayerNameById($player_id)
    {
        $players = self::loadPlayersBasicInfos();
        return isset($players[$player_id]) ? $players[$player_id]['player_name'] : 'Unknown Player';
    }
    
    //////////////////////////////////////////////////////////////////////////////
    //////////// Stage 5: Card Effect Helper Functions
    ////////////
    
    /**
     * Apply Alley Cat effect - force target to discard a card from hand
     * @param int $target_player_id - Player to target
     * @param int $target_card_id - Specific card (null for random selection)
     */
    function applyAlleyCatEffect($target_player_id, $target_card_id = null)
    {
        $target_cards = $this->getPlayerHandCards($target_player_id);
        
        if (empty($target_cards)) {
            self::notifyAllPlayers('effectIneffective',
                clienttranslate('Alley Cat has no effect - ${target_name} has no cards to discard'),
                [
                    'target_id' => $target_player_id,
                    'target_name' => self::getPlayerNameById($target_player_id)
                ]
            );
            return;
        }
        
        // Check for ineffective-against-itself rule
        if ($this->checkIneffectiveAgainstItself(CARD_TYPE_ALLEYCAT, $target_cards)) {
            return; // Effect was ineffective, notification sent by check function
        }
        
        // Select card to discard (random if not specified)
        if ($target_card_id === null) {
            $target_cards_array = array_values($target_cards);
            $card_to_discard = $target_cards_array[array_rand($target_cards_array)];
        } else {
            $card_to_discard = $this->cards->getCard($target_card_id);
        }
        
        // Discard the selected card
        $this->cards->moveCard($card_to_discard['id'], CARD_LOCATION_DISCARD, $target_player_id);
        
        // Notify effect resolution
        self::notifyAllPlayers('alleyCatEffect',
            clienttranslate('Alley Cat forces ${target_name} to discard ${card_name}'),
            [
                'effect_type' => 'alleycat',
                'target_id' => $target_player_id,
                'target_name' => self::getPlayerNameById($target_player_id),
                'card_name' => $this->getCardName($card_to_discard['type']),
                'discarded_card_id' => $card_to_discard['id'],
                'card_type' => $card_to_discard['type']
            ]
        );
        
        // Private notification to reveal discarded card to all players
        self::notifyAllPlayers('cardMoved',
            '',
            [
                'card_id' => $card_to_discard['id'],
                'from_location' => 'hand',
                'to_location' => 'discard',
                'from_player_id' => $target_player_id,
                'to_player_id' => $target_player_id
            ]
        );
        
        $this->notifyHandCounts();
        $this->notifyDiscardUpdate($target_player_id);
    }
    
    /**
     * Apply Catnip effect - steal a card from target hand to actor's herd
     * @param int $target_player_id - Player to steal from
     * @param int $target_card_id - Specific card (null for random selection)
     * @param int $actor_id - Player stealing the card
     */
    function applyCatnipEffect($target_player_id, $target_card_id = null, $actor_id)
    {
        $target_cards = $this->getPlayerHandCards($target_player_id);
        
        if (empty($target_cards)) {
            self::notifyAllPlayers('effectIneffective',
                clienttranslate('Catnip has no effect - ${target_name} has no cards to steal'),
                [
                    'target_id' => $target_player_id,
                    'target_name' => self::getPlayerNameById($target_player_id)
                ]
            );
            return;
        }
        
        // Check for ineffective-against-itself rule
        if ($this->checkIneffectiveAgainstItself(CARD_TYPE_CATNIP, $target_cards)) {
            return; // Effect was ineffective, notification sent by check function
        }
        
        // Select card to steal (random if not specified)
        if ($target_card_id === null) {
            $target_cards_array = array_values($target_cards);
            $card_to_steal = $target_cards_array[array_rand($target_cards_array)];
        } else {
            $card_to_steal = $this->cards->getCard($target_card_id);
        }
        
        // Move card to actor's herd face-down
        $this->cards->moveCard($card_to_steal['id'], CARD_LOCATION_HERD_DOWN, $actor_id);
        
        // Set the card's declared identity to its actual type when stolen
        $sql = "UPDATE card SET card_declared_identity = '" . $card_to_steal['type'] . "' WHERE card_id = " . $card_to_steal['id'];
        self::DbQuery($sql);
        
        // Notify effect resolution
        self::notifyAllPlayers('catnipEffect',
            clienttranslate('Catnip steals ${card_name} from ${target_name} to ${actor_name}\'s herd'),
            [
                'effect_type' => 'catnip',
                'target_id' => $target_player_id,
                'target_name' => self::getPlayerNameById($target_player_id),
                'actor_id' => $actor_id,
                'actor_name' => self::getPlayerNameById($actor_id),
                'card_name' => $this->getCardName($card_to_steal['type']),
                'stolen_card_id' => $card_to_steal['id'],
                'card_type' => $card_to_steal['type']
            ]
        );
        
        // Private notification to reveal stolen card to all players
        self::notifyAllPlayers('cardMoved',
            '',
            [
                'card_id' => $card_to_steal['id'],
                'from_location' => 'hand',
                'to_location' => 'herd_down',
                'from_player_id' => $target_player_id,
                'to_player_id' => $actor_id
            ]
        );
        
        $this->notifyHandCounts();
        $this->notifyHerdUpdate($actor_id);
    }
    
    /**
     * Apply Animal Control effect - remove a face-down card from target herd
     * @param int $target_card_id - Specific card to remove
     */
    function applyAnimalControlEffect($target_card_id)
    {
        $target_card = $this->cards->getCard($target_card_id);
        
        if (!$target_card || $target_card['location'] != CARD_LOCATION_HERD_DOWN) {
            self::notifyAllPlayers('effectIneffective',
                clienttranslate('Animal Control has no effect - target card not found'),
                []
            );
            return;
        }
        
        $target_player_id = $target_card['location_arg'];
        
        // Check for ineffective-against-itself rule
        if ($target_card['type'] == CARD_TYPE_ANIMALCONTROL) {
            // Flip face-up and make it protected instead of removing
            $this->cards->moveCard($target_card_id, CARD_LOCATION_HERD_UP, $target_player_id);
            
            self::notifyAllPlayers('animalControlIneffective',
                clienttranslate('Animal Control is ineffective against itself - target Animal Control flips face-up and becomes protected'),
                [
                    'effect_type' => 'animalcontrol_ineffective',
                    'target_id' => $target_player_id,
                    'target_name' => self::getPlayerNameById($target_player_id),
                    'card_id' => $target_card_id,
                    'card_name' => $this->getCardName(CARD_TYPE_ANIMALCONTROL)
                ]
            );
            
            // Notify card movement from face-down to face-up
            self::notifyAllPlayers('cardMoved',
                '',
                [
                    'card_id' => $target_card_id,
                    'from_location' => 'herd_down',
                    'to_location' => 'herd_up',
                    'from_player_id' => $target_player_id,
                    'to_player_id' => $target_player_id
                ]
            );
            
            $this->notifyHerdUpdate($target_player_id);
            return;
        }
        
        // Remove the card from the game
        $this->cards->moveCard($target_card_id, CARD_LOCATION_REMOVED, $target_player_id);
        
        // Notify effect resolution
        self::notifyAllPlayers('animalControlEffect',
            clienttranslate('Animal Control removes ${card_name} from ${target_name}\'s herd'),
            [
                'effect_type' => 'animalcontrol',
                'target_id' => $target_player_id,
                'target_name' => self::getPlayerNameById($target_player_id),
                'card_name' => $this->getCardName($target_card['type']),
                'removed_card_id' => $target_card_id,
                'card_type' => $target_card['type']
            ]
        );
        
        // Private notification to reveal removed card to all players
        self::notifyAllPlayers('cardMoved',
            '',
            [
                'card_id' => $target_card_id,
                'from_location' => 'herd_down',
                'to_location' => 'removed',
                'from_player_id' => $target_player_id,
                'to_player_id' => $target_player_id
            ]
        );
        
        $this->notifyHerdUpdate($target_player_id);
    }
    
    /**
     * Check ineffective-against-itself rule for hand-targeting cards
     * @param int $acting_type - Type of card being played
     * @param array $target_cards - Cards in target's hand
     * @return bool - True if effect was ineffective
     */
    function checkIneffectiveAgainstItself($acting_type, $target_cards)
    {
        // Only applies to hand-targeting cards
        if (!in_array($acting_type, [CARD_TYPE_ALLEYCAT, CARD_TYPE_CATNIP])) {
            return false;
        }
        
        // Check if target hand contains the same type as acting card
        foreach ($target_cards as $card) {
            if ($card['type'] == $acting_type) {
                // Return the card to hand (it's already there)
                $effect_name = ($acting_type == CARD_TYPE_ALLEYCAT) ? 'Alley Cat' : 'Catnip';
                
                self::notifyAllPlayers('effectIneffectiveAgainstItself',
                    clienttranslate('${effect} is ineffective against itself - no effect'),
                    [
                        'effect' => $effect_name,
                        'target_id' => $card['location_arg'],
                        'target_name' => self::getPlayerNameById($card['location_arg'])
                    ]
                );
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate final score for a player
     * @param int $player_id - Player ID
     * @return int - Total score
     */
    function calculatePlayerScore($player_id)
    {
        $score = 0;
        $has_kitten = false;
        
        // Get all cards in player's herd (both face-up and face-down)
        $herd_cards = array_merge(
            $this->cards->getCardsInLocation(CARD_LOCATION_HERD_DOWN, $player_id),
            $this->cards->getCardsInLocation(CARD_LOCATION_HERD_UP, $player_id)
        );
        
        // Check if player has any Kitten for Show Cat bonus
        foreach ($herd_cards as $card) {
            if ($card['type'] == CARD_TYPE_KITTEN) {
                $has_kitten = true;
                break;
            }
        }
        
        // Calculate herd score
        foreach ($herd_cards as $card) {
            if ($card['type'] == CARD_TYPE_SHOWCAT) {
                // Show Cat: 5 normally, 7 if player has any Kitten
                $score += $has_kitten ? SHOWCAT_KITTEN_BONUS_VALUE : SHOWCAT_BASE_VALUE;
            } else {
                // All other cards use their base values
                $score += CARD_POINTS[$card['type']];
            }
        }
        
        // Add hand bonus: +1 per 2 cards in hand (rounded up)
        $cards_in_hand = $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id);
        $hand_bonus = ceil($cards_in_hand / 2);
        $score += $hand_bonus;
        
        return $score;
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Stage 3: Game State Helpers
    ////////////

    /**
     * Get players who can participate in challenge (excludes actor)
     * @return array - Array of player IDs who can challenge
     */
    function getActiveChallengeParticipants()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            return [];
        }
        
        $participants = [];
        $players = self::loadPlayersBasicInfos();
        
        foreach ($players as $player_id => $player_info) {
            // Use the correct field name from database schema
            if ($this->canPlayerChallenge($player_id, $pending['actor_player_id'])) {
                $participants[] = $player_id;
            }
        }
        
        return $participants;
    }

    /**
     * Set up challenge window - make all eligible players active except the actor
     * @param int $exclude_player_id - Player to exclude from challenge window (usually the actor)
     */
    function setMultipleActivePlayersForChallenge($exclude_player_id)
    {
        $players = self::loadPlayersBasicInfos();
        $active_players = [];
        
        foreach ($players as $player_id => $player_info) {
            if ($player_id != $exclude_player_id && $this->hasCardsInHand($player_id)) {
                $active_players[] = $player_id;
            }
        }
        
        if (!empty($active_players)) {
            $this->gamestate->setPlayersMultiactive($active_players, '', true);
        }
    }

    /**
     * Check if game should end (any player has 0 cards in hand)
     * @return bool - True if game should end
     */
    function checkGameEndCondition()
    {
        $players = self::loadPlayersBasicInfos();
        
        foreach ($players as $player_id => $player_info) {
            // Game ends when any player has exactly 0 cards in HAND specifically
            $cards_in_hand = $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id);
            if ($cards_in_hand == 0) {
                return true;
            }
        }
        
        return false;
    }



//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in herdingcats.action.php)
    */

    /*
    
    Example:

    function actPlayCard($card_id)
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction('playCard'); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card here
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ));
          
        // Next, go to the next state
        $this->gamestate->nextState( 'playCard' );
    }
    
    */

    // TODO: Stage 3-5 action implementations will go here
    function actDeclare($card_id, $declared_type, $target_player_id = null)
    {
        self::checkAction('declare');
        $player_id = self::getActivePlayerId();
        
        // Validate card is in player's hand
        $card = $this->cards->getCard($card_id);
        if (!$card || $card['location'] != CARD_LOCATION_HAND || $card['location_arg'] != $player_id) {
            throw new feException("Card not in your hand");
        }
        
        // Create a pending action for the challenge system
        $action_id = $this->pushPending([
            'actor_id' => $player_id,  // Changed from actor_player_id to actor_id
            'declared_type' => $declared_type,  // Changed from declared_identity to declared_type
            'card_id' => $card_id,  // Changed from played_card_id to card_id
            'target_player_id' => $target_player_id,
            'phase' => 'challenge'
        ]);
        
        // Store the action ID for later retrieval
        self::setGameStateValue('current_action_id', $action_id);
        
        // SIMPLIFIED: Just play the card to the herd without bluffing/challenges
        // Move card from hand to herd (face down for now)
        $this->cards->moveCard($card_id, CARD_LOCATION_HERD_DOWN, $player_id);
        
        // Notify all players that a card was played
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
        
        // Update herd display for the player
        self::notifyAllPlayers('herdUpdate',
            clienttranslate('Card added to herd'),
            [
                'player_id' => $player_id,
                'player_name' => self::getActivePlayerName(),
                'card' => ['id' => $card_id, 'type' => $card['type'], 'type_arg' => $card['type_arg']],
                'visible' => false // Keep cards face-down for now
            ]
        );
        
        // Notify hand count update
        $this->notifyHandCounts();
        
        // Go to challenge window state
        // Using 'declared' transition name which matches states.inc.php
        $this->gamestate->nextState('declared');
    }

    function actChallenge($actor_id)
    {
        self::checkAction('challenge');
        $player_id = self::getActivePlayerId();
        
        // Validate player can challenge
        if (!$this->canPlayerChallenge($player_id, $actor_id)) {
            throw new feException("Cannot challenge this action");
        }
        
        $pending = $this->pullPending();
        if (!$pending || $pending['actor_player_id'] != $actor_id) {
            throw new feException("Invalid challenge target");
        }
        
        // Add player to challengers list
        $challengers = isset($pending['challengers']) ? json_decode($pending['challengers'], true) : [];
        if (!in_array($player_id, $challengers)) {
            $challengers[] = $player_id;
            
            // Update pending action
            $sql = "UPDATE pending_action SET challengers = '" . addslashes(json_encode($challengers)) . "' WHERE action_id = " . $pending['action_id'];
            self::DbQuery($sql);
        }
        
        // Notify challenge declared
        self::notifyAllPlayers('challengeDeclared',
            clienttranslate('${player_name} challenges the declaration'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id),
                'actor_id' => $actor_id,
                'actor_name' => self::getPlayerNameById($actor_id)
            ]
        );
        
        // Remove player from multiactive
        $this->gamestate->setPlayerNonMultiactive($player_id, 'challenge');
    }

    function actPassChallenge()
    {
        self::checkAction('passChallenge');
        $player_id = self::getActivePlayerId();
        
        // Notify pass
        self::notifyAllPlayers('challengePassed',
            clienttranslate('${player_name} passes on challenging'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id)
            ]
        );
        
        // Remove player from multiactive
        $this->gamestate->setPlayerNonMultiactive($player_id, 'pass');
    }

    function actSelectBlindFromActor($card_index)
    {
        self::checkAction('selectBlindFromActor');
        $player_id = self::getActivePlayerId();
        
        $pending = $this->pullPending();
        if (!$pending) {
            throw new feException("No pending action");
        }
        
        $actor_id = $pending['actor_player_id'];
        $actor_cards = $this->getPlayerHandCards($actor_id);
        $actor_cards = array_values($actor_cards);
        
        if ($card_index < 0 || $card_index >= count($actor_cards)) {
            throw new feException("Invalid card index");
        }
        
        $selected_card = $actor_cards[$card_index];
        
        // Apply bluff penalty - discard both cards
        $limbo_card = $this->cards->getCard($pending['played_card_id']);
        
        $this->cards->moveCard($limbo_card['id'], CARD_LOCATION_DISCARD, $actor_id);
        $this->cards->moveCard($selected_card['id'], CARD_LOCATION_DISCARD, $actor_id);
        
        // Notify penalty
        self::notifyAllPlayers('bluffPenaltyApplied',
            clienttranslate('${player_name} selects a card from ${actor_name}. Both cards discarded for bluffing.'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id),
                'actor_id' => $actor_id,
                'actor_name' => self::getPlayerNameById($actor_id),
                'discarded_cards' => [$limbo_card['id'], $selected_card['id']]
            ]
        );
        
        $this->notifyHandCounts();
        $this->notifyDiscardUpdate($actor_id);
        
        // Clear pending and end turn
        $this->clearPending();
        $this->gamestate->nextState('penaltyApplied');
    }

    function actSelectBlindFromChallenger($challenger_id, $card_index)
    {
        self::checkAction('selectBlindFromChallenger');
        $player_id = self::getActivePlayerId();
        
        $pending = $this->pullPending();
        if (!$pending || $pending['actor_player_id'] != $player_id) {
            throw new feException("Invalid action");
        }
        
        $challenger_cards = $this->getPlayerHandCards($challenger_id);
        $challenger_cards = array_values($challenger_cards);
        
        if ($card_index < 0 || $card_index >= count($challenger_cards)) {
            throw new feException("Invalid card index");
        }
        
        $selected_card = $challenger_cards[$card_index];
        
        // Apply truth penalty - challenger discards
        $this->cards->moveCard($selected_card['id'], CARD_LOCATION_DISCARD, $challenger_id);

        self::notifyAllPlayers('cardRemoved', '', [
            'player_id' => $challenger_id,
            'card_id' => $selected_card['id'],
            'from_zone' => 'hand'
        ]);
        
        // Notify penalty
        self::notifyAllPlayers('truthPenaltyApplied',
            clienttranslate('${actor_name} selects a card from ${challenger_name}. ${challenger_name} discards for false challenge.'),
            [
                'player_id' => $player_id,
                'actor_name' => self::getPlayerNameById($player_id),
                'challenger_id' => $challenger_id,
                'challenger_name' => self::getPlayerNameById($challenger_id),
                'discarded_card' => $selected_card['id']
            ]
        );
        
        $this->notifyHandCounts();
        $this->notifyDiscardUpdate($challenger_id);
        
        // Check if more challengers to process
        $challengers = json_decode($pending['challengers'], true);
        $current_index = array_search($challenger_id, $challengers);
        
        if ($current_index !== false && $current_index < count($challengers) - 1) {
            // More challengers to process
            $this->gamestate->nextState('nextChallenger');
        } else {
            // All challengers processed, continue to next state
            if ($this->isTargetedType($pending['declared_identity'])) {
                $this->gamestate->nextState('targetSelection');
            } else {
                $this->gamestate->nextState('noTargeting');
            }
        }
    }

    function actSelectTargetSlot($slot_index, $zone)
    {
        self::checkAction('selectTarget');
        $player_id = self::getActivePlayerId();
        
        $pending = $this->pullPending();
        if (!$pending || $pending['actor_player_id'] != $player_id) {
            throw new feException("Invalid action");
        }
        
        // Validate target selection based on zone
        if ($zone == TARGET_ZONE_HAND) {
            // For hand targeting, slot_index is the target player ID
            $target_player_id = $slot_index;
            if (!$this->validateHandTarget($target_player_id, $pending['declared_identity'])) {
                throw new feException("Invalid hand target");
            }
            
            // Update pending action with specific target
            $sql = "UPDATE pending_action SET target_player_id = $target_player_id, target_zone = 'hand' WHERE action_id = " . $pending['action_id'];
            self::DbQuery($sql);
            
        } else if ($zone == TARGET_ZONE_HERD) {
            // For herd targeting, slot_index is the card ID
            $target_card_id = $slot_index;
            if (!$this->validateHerdTarget($target_card_id, $pending['declared_identity'])) {
                throw new feException("Invalid herd target");
            }
            
            // Get card owner
            $target_card = $this->cards->getCard($target_card_id);
            $target_player_id = $target_card['location_arg'];
            
            // Update pending action
            $sql = "UPDATE pending_action SET target_player_id = $target_player_id, target_zone = 'herd', selected_slot_index = $target_card_id WHERE action_id = " . $pending['action_id'];
            self::DbQuery($sql);
        } else {
            throw new feException("Invalid target zone");
        }
        
        // Notify target selection
        self::notifyAllPlayers('targetSelected',
            clienttranslate('${player_name} selects target'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id),
                'target_zone' => $zone,
                'target_player_id' => $target_player_id
            ]
        );
        
        // Transition to intercept declare state
        $this->gamestate->nextState('targetSelected');
    }

    function actDeclareIntercept($card_id, $zone)
    {
        self::checkAction('declareIntercept');
        $player_id = self::getActivePlayerId();
        
        // Validate Laser Pointer ownership
        $card = $this->cards->getCard($card_id);
        if (!$card || $card['location'] != CARD_LOCATION_HAND || $card['location_arg'] != $player_id) {
            throw new feException("Card not in your hand");
        }
        
        if ($card['type'] != CARD_TYPE_LASERPOINTER) {
            throw new feException("Only Laser Pointer can intercept");
        }
        
        $pending = $this->pullPending();
        if (!$pending) {
            throw new feException("No action to intercept");
        }
        
        // Store intercept declaration
        $sql = "UPDATE pending_action SET intercept_declared_by = $player_id, intercept_zone = '$zone' WHERE action_id = " . $pending['action_id'];
        self::DbQuery($sql);
        
        // Move intercept card to limbo
        $this->cards->moveCard($card_id, CARD_LOCATION_LIMBO, $player_id);
        
        // Notify intercept
        self::notifyAllPlayers('interceptDeclared',
            clienttranslate('${player_name} declares Laser Pointer intercept'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id),
                'intercept_zone' => $zone
            ]
        );
        
        $this->notifyHandCounts();
        
        // Transition to intercept challenge window
        $this->gamestate->nextState('interceptDeclared');
    }

    function actPassIntercept()
    {
        self::checkAction('passIntercept');
        $player_id = self::getActivePlayerId();
        
        // Notify pass
        self::notifyAllPlayers('interceptPassed',
            clienttranslate('${player_name} passes on intercept'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id)
            ]
        );
        
        // Remove player from multiactive
        $this->gamestate->setPlayerNonMultiactive($player_id, 'passIntercept');
    }

    function actChallengeIntercept()
    {
        self::checkAction('challengeIntercept');
        $player_id = self::getActivePlayerId();
        
        $pending = $this->pullPending();
        if (!$pending || !isset($pending['intercept_declared_by'])) {
            throw new feException("No intercept to challenge");
        }
        
        // Add player to intercept challengers list
        $intercept_challengers = isset($pending['intercept_challengers']) ? json_decode($pending['intercept_challengers'], true) : [];
        if (!in_array($player_id, $intercept_challengers)) {
            $intercept_challengers[] = $player_id;
            
            // Update pending action
            $sql = "UPDATE pending_action SET intercept_challengers = '" . addslashes(json_encode($intercept_challengers)) . "' WHERE action_id = " . $pending['action_id'];
            self::DbQuery($sql);
        }
        
        // Notify intercept challenge
        self::notifyAllPlayers('interceptChallengeDeclared',
            clienttranslate('${player_name} challenges the intercept'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id)
            ]
        );
        
        // Remove player from multiactive
        $this->gamestate->setPlayerNonMultiactive($player_id, 'challengeIntercept');
    }

    function actPassChallengeIntercept()
    {
        self::checkAction('passChallengeIntercept');
        $player_id = self::getActivePlayerId();
        
        // Notify pass
        self::notifyAllPlayers('interceptChallengePassed',
            clienttranslate('${player_name} passes on challenging intercept'),
            [
                'player_id' => $player_id,
                'player_name' => self::getPlayerNameById($player_id)
            ]
        );
        
        // Remove player from multiactive
        $this->gamestate->setPlayerNonMultiactive($player_id, 'passChallengeIntercept');
    }

    function actSkipTargeting()
    {
        // TODO: Implement targeting logic - for now just transition
        self::checkAction('actSkipTargeting');
        
        // Skip to next phase for non-targeting cards
        $this->gamestate->nextState('noTargeting');
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" in "states.inc.php".
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

    // TODO: Stage 3-5 argument functions will go here
    function argAwaitDeclaration()
    {
        // Return empty array if no active player (shouldn't happen)
        $player_id = self::getActivePlayerId();
        if (!$player_id) {
            return [];
        }
        
        // Get player's hand cards
        $hand_cards = $this->cards->getCardsInLocation('hand', $player_id);
        
        return [
            'hand_cards' => $hand_cards,
            'card_types' => [
                1 => clienttranslate('Kitten'),
                2 => clienttranslate('Show Cat'),
                3 => clienttranslate('Alley Cat'),
                4 => clienttranslate('Catnip'),
                5 => clienttranslate('Animal Control'),
                6 => clienttranslate('Laser Pointer')
            ],
            'players' => self::loadPlayersBasicInfos()
        ];
    }

    function argChallengeWindow()
    {
        // SIMPLIFIED: Just allow all other players to challenge
        $active_player = self::getActivePlayerId();
        $players = self::loadPlayersBasicInfos();
        $eligible = [];
        
        foreach ($players as $player_id => $player_info) {
            if ($player_id != $active_player) {
                $eligible[] = intval($player_id);
            }
        }
        
        return [
            'eligible' => $eligible,
            'eligible_challengers' => $eligible,
            'can_challenge' => $eligible,
            'actor_id' => $active_player,
            'declared_card' => 'Kitten',  // Simplified for testing
            'actor_name' => self::getActivePlayerName()
        ];
    }

    function argChallengerSelectBluffPenalty()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            return [];
        }
        
        $actor_id = $pending['actor_player_id'];
        $actor_cards = $this->getPlayerHandCards($actor_id);
        
        return [
            'pending_action' => $pending,
            'actor_id' => $actor_id,
            'actor_name' => self::getPlayerNameById($actor_id),
            'actor_hand_count' => count($actor_cards)
        ];
    }

    function argAttackerSelectTruthfulPenalty()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            return [];
        }
        
        $challengers = json_decode($pending['challengers'], true);
        $challenger_info = [];
        
        foreach ($challengers as $challenger_id) {
            $challenger_cards = $this->getPlayerHandCards($challenger_id);
            $challenger_info[] = [
                'player_id' => $challenger_id,
                'player_name' => self::getPlayerNameById($challenger_id),
                'hand_count' => count($challenger_cards)
            ];
        }
        
        return [
            'pending_action' => $pending,
            'challengers' => $challenger_info,
            'actor_name' => self::getPlayerNameById($pending['actor_player_id'])
        ];
    }

    function argSelectTarget()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            return [];
        }
        
        $declared_type = $pending['declared_identity'];
        $target_zone = $this->getTargetZone($declared_type);
        $valid_targets = [];
        
        if ($target_zone == TARGET_ZONE_HAND) {
            // Get players with cards in hand (excluding actor)
            $players = self::loadPlayersBasicInfos();
            foreach ($players as $player_id => $player_info) {
                if ($player_id != $pending['actor_player_id'] && $this->hasCardsInHand($player_id)) {
                    $valid_targets[] = [
                        'player_id' => $player_id,
                        'player_name' => $player_info['player_name'],
                        'hand_count' => $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id)
                    ];
                }
            }
        } else if ($target_zone == TARGET_ZONE_HERD) {
            // Get face-down herd cards from all players
            $players = self::loadPlayersBasicInfos();
            foreach ($players as $player_id => $player_info) {
                if ($player_id != $pending['actor_player_id']) {
                    $herd_cards = $this->cards->getCardsInLocation(CARD_LOCATION_HERD_DOWN, $player_id);
                    foreach ($herd_cards as $card) {
                        $valid_targets[] = [
                            'card_id' => $card['id'],
                            'player_id' => $player_id,
                            'player_name' => $player_info['player_name'],
                            'declared_identity' => $card['card_declared_identity']
                        ];
                    }
                }
            }
        }
        
        return [
            'pending_action' => $pending,
            'target_zone' => $target_zone,
            'valid_targets' => $valid_targets,
            'declared_card' => $this->getCardName($declared_type)
        ];
    }

    function argInterceptDeclare()
    {
        $pending = $this->pullPending();
        $player_id = self::getActivePlayerId();
        
        // Check if player has Laser Pointer
        $hand_cards = $this->getPlayerHandCards($player_id);
        $has_laser_pointer = false;
        foreach ($hand_cards as $card) {
            if ($card['type'] == CARD_TYPE_LASERPOINTER) {
                $has_laser_pointer = true;
                break;
            }
        }
        
        return [
            'pending_action' => $pending,
            'has_laser_pointer' => $has_laser_pointer,
            'target_zone' => isset($pending['target_zone']) ? $pending['target_zone'] : null,
            'target_player_name' => isset($pending['target_player_id']) ? self::getPlayerNameById($pending['target_player_id']) : ''
        ];
    }

    function argInterceptChallengeWindow()
    {
        $pending = $this->pullPending();
        
        // Get players who can challenge intercept (excluding interceptor and original actor)
        $participants = [];
        $players = self::loadPlayersBasicInfos();
        
        foreach ($players as $player_id => $player_info) {
            if ($player_id != $pending['intercept_declared_by'] && 
                $player_id != $pending['actor_player_id'] && 
                $this->hasCardsInHand($player_id)) {
                $participants[] = $player_id;
            }
        }
        
        return [
            'pending_action' => $pending,
            'can_challenge_intercept' => $participants,
            'interceptor_name' => self::getPlayerNameById($pending['intercept_declared_by'])
        ];
    }

    function argInterceptChallengerSelectPenalty()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            return [];
        }
        
        $interceptor_id = $pending['intercept_declared_by'];
        $interceptor_cards = $this->getPlayerHandCards($interceptor_id);
        
        return [
            'pending_action' => $pending,
            'interceptor_id' => $interceptor_id,
            'interceptor_name' => self::getPlayerNameById($interceptor_id),
            'interceptor_hand_count' => count($interceptor_cards)
        ];
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" in "states.inc.php".
        The action method of state X is called everytime the current game state is set to X.
    */
    
    /*
    
    Example for game state "MyGameState":

    function stMyGameState()
    {
        // Do some stuff ...
        
        // (very often) go to another gamestate
        $this->gamestate->nextState( 'myTransition' );
    }    
    */

    // TODO: Stage 3-5 state action implementations will go here
    function stResolveChallenge()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            throw new feException("No pending action to resolve");
        }
        
        // Get the actual card that was declared
        $declared_card = $this->cards->getCard($pending['played_card_id']);
        $declared_type = $pending['declared_identity'];
        $actual_type = $declared_card['type'];
        
        // Check if declaration was truthful
        $is_bluff = ($actual_type != $declared_type);
        
        if ($is_bluff) {
            // Bluff detected - challengers win
            self::notifyAllPlayers('challengeResolved',
                clienttranslate('Challenge succeeded! ${actor_name} was bluffing.'),
                [
                    'challenge_result' => 'succeeded',
                    'actor_id' => $pending['actor_player_id'],
                    'actor_name' => self::getPlayerNameById($pending['actor_player_id']),
                    'actual_card' => $this->getCardName($actual_type),
                    'declared_card' => $this->getCardName($declared_type),
                    'is_bluff' => true
                ]
            );
            
            // Private notification to reveal actual card to all players
            self::notifyAllPlayers('cardRevealed',
                clienttranslate('The actual card was ${card_name}'),
                [
                    'card_id' => $pending['played_card_id'],
                    'card_type' => $actual_type,
                    'card_name' => $this->getCardName($actual_type),
                    'revealed_to' => 'all'
                ]
            );
            
            // Set active player to first challenger for penalty selection
            $challengers = json_decode($pending['challengers'], true);
            $this->gamestate->changeActivePlayer($challengers[0]);
            $this->gamestate->nextState('challengeSucceeded');
        } else {
            // Truth - actor wins
            self::notifyAllPlayers('challengeResolved',
                clienttranslate('Challenge failed! ${actor_name} was telling the truth.'),
                [
                    'challenge_result' => 'failed',
                    'actor_id' => $pending['actor_player_id'],
                    'actor_name' => self::getPlayerNameById($pending['actor_player_id']),
                    'revealed_card' => $this->getCardName($actual_type),
                    'is_bluff' => false
                ]
            );
            
            // Private notification to reveal actual card to all players
            self::notifyAllPlayers('cardRevealed',
                clienttranslate('The actual card was ${card_name}'),
                [
                    'card_id' => $pending['played_card_id'],
                    'card_type' => $actual_type,
                    'card_name' => $this->getCardName($actual_type),
                    'revealed_to' => 'all'
                ]
            );
            
            // Set active player back to actor for penalty selection
            $this->gamestate->changeActivePlayer($pending['actor_player_id']);
            $this->gamestate->nextState('challengeFailed');
        }
    }

    function stResolveInterceptChallenge()
    {
        $pending = $this->pullPending();
        if (!$pending || !isset($pending['intercept_declared_by'])) {
            throw new feException("No intercept to resolve");
        }
        
        // Get the intercept card from limbo
        $intercept_cards = $this->cards->getCardsInLocation(CARD_LOCATION_LIMBO, $pending['intercept_declared_by']);
        if (empty($intercept_cards)) {
            throw new feException("Intercept card not found in limbo");
        }
        $intercept_card = array_values($intercept_cards)[0];
        $is_laser_pointer = ($intercept_card['type'] == CARD_TYPE_LASERPOINTER);
        
        if (!$is_laser_pointer) {
            // Intercept was a bluff - challengers win
            self::notifyAllPlayers('interceptChallengeSucceeded',
                clienttranslate('Intercept challenge succeeded! ${interceptor_name} was bluffing about Laser Pointer.'),
                [
                    'interceptor_id' => $pending['intercept_declared_by'],
                    'interceptor_name' => self::getPlayerNameById($pending['intercept_declared_by']),
                    'actual_card' => $this->getCardName($intercept_card['type'])
                ]
            );
            
            // Discard the fake laser pointer
            $this->cards->moveCard($intercept_card['id'], CARD_LOCATION_DISCARD, $pending['intercept_declared_by']);
            $this->notifyHandCounts();
            $this->notifyDiscardUpdate($pending['intercept_declared_by']);
            
            // Set active player to first intercept challenger for penalty selection
            $intercept_challengers = json_decode($pending['intercept_challengers'], true);
            $this->gamestate->changeActivePlayer($intercept_challengers[0]);
            $this->gamestate->nextState('interceptChallengeSucceeded');
        } else {
            // Truth - interceptor wins
            self::notifyAllPlayers('interceptChallengeFailed',
                clienttranslate('Intercept challenge failed! ${interceptor_name} really has Laser Pointer.'),
                [
                    'interceptor_id' => $pending['intercept_declared_by'],
                    'interceptor_name' => self::getPlayerNameById($pending['intercept_declared_by'])
                ]
            );
            
            // Set active player to interceptor for penalty selection
            $this->gamestate->changeActivePlayer($pending['intercept_declared_by']);
            $this->gamestate->nextState('interceptChallengeFailed');
        }
    }

    function stRevealAndResolve()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            throw new feException("No pending action to resolve");
        }
        
        // Get the actual card and declared types
        $card = $this->cards->getCard($pending['played_card_id']);
        $declared_type = $pending['declared_identity'];
        $actual_type = $card['type'];
        $actor_id = $pending['actor_player_id'];
        
        // Check if effect was intercepted by Laser Pointer
        $effect_cancelled = false;
        if (isset($pending['intercept_declared_by']) && $pending['intercept_declared_by'] > 0) {
            // Find laser pointer in limbo from interceptor
            $intercept_cards = $this->cards->getCardsInLocation(CARD_LOCATION_LIMBO, $pending['intercept_declared_by']);
            if (!empty($intercept_cards)) {
                $laser_card = array_values($intercept_cards)[0];
                if ($laser_card['type'] == CARD_TYPE_LASERPOINTER) {
                    // Successful intercept - cancel effect and discard laser pointer
                    $effect_cancelled = true;
                    $this->cards->moveCard($laser_card['id'], CARD_LOCATION_DISCARD, $pending['intercept_declared_by']);
                    
                    self::notifyAllPlayers('effectIntercepted',
                        clienttranslate('${interceptor_name} successfully intercepts with Laser Pointer! Effect cancelled.'),
                        [
                            'interceptor_id' => $pending['intercept_declared_by'],
                            'interceptor_name' => self::getPlayerNameById($pending['intercept_declared_by']),
                            'actor_id' => $actor_id,
                            'actor_name' => self::getPlayerNameById($actor_id)
                        ]
                    );
                    
                    $this->notifyHandCounts();
                    $this->notifyDiscardUpdate($pending['intercept_declared_by']);
                }
            }
        }
        
        // Apply card effects (if not intercepted)
        if (!$effect_cancelled && $this->isTargetedType($declared_type)) {
            $target_player_id = $pending['target_player_id'];
            $target_zone = $pending['target_zone'];
            
            // Apply specific card effects based on declared type
            switch ($declared_type) {
                case CARD_TYPE_ALLEYCAT:
                    if ($target_zone == TARGET_ZONE_HAND) {
                        $this->applyAlleyCatEffect($target_player_id, null);
                    }
                    break;
                    
                case CARD_TYPE_CATNIP:
                    if ($target_zone == TARGET_ZONE_HAND) {
                        $this->applyCatnipEffect($target_player_id, null, $actor_id);
                    }
                    break;
                    
                case CARD_TYPE_ANIMALCONTROL:
                    if ($target_zone == TARGET_ZONE_HERD && isset($pending['selected_slot_index'])) {
                        $this->applyAnimalControlEffect($pending['selected_slot_index']);
                    }
                    break;
            }
        }
        
        $this->gamestate->nextState('effectResolved');
    }

    function stAddPlayedCardToHerd()
    {
        $pending = $this->pullPending();
        if (!$pending) {
            throw new feException("No pending action");
        }
        
        // Move card from limbo to herd with declared identity
        $card = $this->cards->getCard($pending['played_card_id']);
        $this->addToHerdFaceDownAs($card['id'], $pending['actor_player_id'], $pending['declared_identity']);
        
        // Notify herd update
        // Keep log simple to avoid substitution issues during development
        self::notifyAllPlayers('cardAddedToHerd',
            clienttranslate('Card added to herd'),
            [
                'player_id' => $pending['actor_player_id'],
                'player_name' => self::getPlayerNameById($pending['actor_player_id'])
            ]
        );
        
        $this->notifyHerdUpdate($pending['actor_player_id']);
        
        // Clear pending action
        $this->clearPending();
        
        $this->gamestate->nextState('cardAdded');
    }

    function stEndTurn()
    {
        $current_player_id = self::getActivePlayerId();
        
        // Notify turn ended
        self::notifyAllPlayers('turnEnded',
            clienttranslate('${player_name}\'s turn has ended'),
            [
                'player_id' => $current_player_id,
                'player_name' => self::getPlayerNameById($current_player_id)
            ]
        );
        
        // Update hand counts for all players
        $this->notifyHandCounts();
        
        // Check if game should end or continue to next player
        if ($this->checkGameEndCondition()) {
            // Calculate and store final scores
            $this->stGameEnd();
            $this->gamestate->nextState('gameEnd');
        } else {
            $this->activeNextPlayer();
            $this->gamestate->nextState('nextPlayer');
        }
    }
    
    function stGameEnd()
    {
        $players = self::loadPlayersBasicInfos();
        $final_scores = [];
        $detailed_scores = [];
        
        foreach ($players as $player_id => $player_info) {
            $score = $this->calculatePlayerScore($player_id);
            $final_scores[$player_id] = $score;
            
            // Get detailed scoring breakdown
            $herd_cards = array_merge(
                $this->cards->getCardsInLocation(CARD_LOCATION_HERD_DOWN, $player_id),
                $this->cards->getCardsInLocation(CARD_LOCATION_HERD_UP, $player_id)
            );
            $cards_in_hand = $this->cards->countCardsInLocation(CARD_LOCATION_HAND, $player_id);
            $hand_bonus = ceil($cards_in_hand / 2);
            
            $detailed_scores[$player_id] = [
                'total_score' => $score,
                'herd_cards_count' => count($herd_cards),
                'hand_cards_count' => $cards_in_hand,
                'hand_bonus' => $hand_bonus,
                'player_name' => $player_info['player_name']
            ];
            
            // Store score in player table for BGA
            $sql = "UPDATE player SET player_score = $score WHERE player_id = $player_id";
            self::DbQuery($sql);
        }
        
        // Send game end notification with final scores
        self::notifyAllPlayers('gameEnded',
            clienttranslate('Game has ended! Final scores calculated'),
            [
                'scores' => $final_scores,
                'detailed_scores' => $detailed_scores,
                'winner_id' => array_keys($final_scores, max($final_scores))[0]
            ]
        );
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player)
    {
        $statename = $state['name'];
        
        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                case "awaitDeclaration":
                    // TODO: Auto-declare a random valid card
                    throw new feException("Zombie handling for awaitDeclaration not implemented yet - Stage 3");
                    break;
                    
                case "challengerSelectBluffPenalty":
                    // TODO: Auto-select a random card from bluffer's hand
                    throw new feException("Zombie handling for challengerSelectBluffPenalty not implemented yet - Stage 3");
                    break;
                    
                case "attackerSelectTruthfulPenalty":
                    // TODO: Auto-select penalty cards from challengers
                    throw new feException("Zombie handling for attackerSelectTruthfulPenalty not implemented yet - Stage 3");
                    break;
                    
                case "selectTarget":
                    // TODO: Auto-select a random valid target
                    throw new feException("Zombie handling for selectTarget not implemented yet - Stage 4");
                    break;
                    
                case "interceptDeclare":
                    // TODO: Auto-pass intercept
                    throw new feException("Zombie handling for interceptDeclare not implemented yet - Stage 4");
                    break;
                    
                case "interceptChallengerSelectPenalty":
                    // TODO: Auto-select penalty card
                    throw new feException("Zombie handling for interceptChallengerSelectPenalty not implemented yet - Stage 4");
                    break;
                    
                default:
                    $this->gamestate->nextState("zombiePass");
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non-blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            
            return;
        }

        throw new feException("Zombie mode not supported at this game state: " . $statename);
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb($from_version)
    {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//
    }    
}
