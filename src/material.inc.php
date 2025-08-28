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
 * material.inc.php
 *
 * HerdingCats game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *
 */

/*
 * Card Type Constants
 */
define('CARD_TYPE_KITTEN', 1);
define('CARD_TYPE_SHOWCAT', 2);  
define('CARD_TYPE_ALLEYCAT', 3);
define('CARD_TYPE_CATNIP', 4);
define('CARD_TYPE_ANIMALCONTROL', 5);
define('CARD_TYPE_LASERPOINTER', 6);

/*
 * Card Location Constants
 */
define('CARD_LOCATION_DECK', 'deck');
define('CARD_LOCATION_HAND', 'hand');
define('CARD_LOCATION_HERD_DOWN', 'herd_down');  // Face-down in herd
define('CARD_LOCATION_HERD_UP', 'herd_up');      // Face-up in herd (protected)
define('CARD_LOCATION_DISCARD', 'discard');
define('CARD_LOCATION_REMOVED', 'removed');
define('CARD_LOCATION_LIMBO', 'limbo');          // Temporary location during actions

/*
 * Target Zone Constants
 */
define('TARGET_ZONE_HAND', 'hand');
define('TARGET_ZONE_HERD', 'herd');

/*
 * Game Phase Constants
 */
define('PHASE_DECLARATION', 'declaration');
define('PHASE_CHALLENGE', 'challenge');
define('PHASE_TARGET_SELECT', 'target_select');
define('PHASE_INTERCEPT', 'intercept');
define('PHASE_RESOLVE', 'resolve');

/*
 * Card Definitions
 * Each card type with its properties and behavior
 */
$this->card_types = [
    CARD_TYPE_KITTEN => [
        'name' => clienttranslate('Kitten'),
        'value' => 2,
        'targets' => false,
        'target_zone' => null,
        'description' => clienttranslate('A cute kitten worth 2 points. No special effect.'),
        'sprite_position' => 0
    ],
    
    CARD_TYPE_SHOWCAT => [
        'name' => clienttranslate('Show Cat'),
        'value' => 5, // Base value, becomes 7 if player has at least one Kitten
        'targets' => false,
        'target_zone' => null,
        'description' => clienttranslate('Worth 5 points normally, 7 points if you have at least one Kitten in your herd at scoring.'),
        'sprite_position' => 1
    ],
    
    CARD_TYPE_ALLEYCAT => [
        'name' => clienttranslate('Alley Cat'),
        'value' => 1,
        'targets' => true,
        'target_zone' => TARGET_ZONE_HAND,
        'description' => clienttranslate('Force opponent to discard a card from their hand. Worth 1 point.'),
        'sprite_position' => 2
    ],
    
    CARD_TYPE_CATNIP => [
        'name' => clienttranslate('Catnip'),
        'value' => 1,
        'targets' => true,
        'target_zone' => TARGET_ZONE_HAND,
        'description' => clienttranslate('Steal a card from opponent\'s hand into your herd. Worth 1 point.'),
        'sprite_position' => 3
    ],
    
    CARD_TYPE_ANIMALCONTROL => [
        'name' => clienttranslate('Animal Control'),
        'value' => 0,
        'targets' => true,
        'target_zone' => TARGET_ZONE_HERD,
        'description' => clienttranslate('Remove a face-down card from opponent\'s herd. Worth 0 points.'),
        'sprite_position' => 4
    ],
    
    CARD_TYPE_LASERPOINTER => [
        'name' => clienttranslate('Laser Pointer'),
        'value' => 0,
        'targets' => false,
        'target_zone' => null,
        'description' => clienttranslate('Can be discarded to intercept attacks targeting you. Worth 0 points.'),
        'sprite_position' => 5
    ]
];

/*
 * Deck Specification
 * Each player gets identical 9-card deck
 */
$this->deck_composition = [
    CARD_TYPE_KITTEN => 3,        // 3 Kittens per player
    CARD_TYPE_SHOWCAT => 1,       // 1 Show Cat per player
    CARD_TYPE_ALLEYCAT => 2,      // 2 Alley Cats per player
    CARD_TYPE_CATNIP => 1,        // 1 Catnip per player
    CARD_TYPE_ANIMALCONTROL => 1, // 1 Animal Control per player
    CARD_TYPE_LASERPOINTER => 1   // 1 Laser Pointer per player
];

// BGA-compatible DECK_PER_PLAYER constant array
define('DECK_PER_PLAYER', [
    CARD_TYPE_KITTEN => 3,
    CARD_TYPE_SHOWCAT => 1,
    CARD_TYPE_ALLEYCAT => 2,
    CARD_TYPE_CATNIP => 1,
    CARD_TYPE_ANIMALCONTROL => 1,
    CARD_TYPE_LASERPOINTER => 1
]);

/*
 * Card Values for Scoring (CARD_POINTS array for BGA compliance)
 */
$this->card_values = [
    CARD_TYPE_KITTEN => 2,
    CARD_TYPE_SHOWCAT => 5,     // Base value (becomes 7 with kittens)
    CARD_TYPE_ALLEYCAT => 1,
    CARD_TYPE_CATNIP => 1,
    CARD_TYPE_ANIMALCONTROL => 0,
    CARD_TYPE_LASERPOINTER => 0
];

// BGA-compatible CARD_POINTS array
define('CARD_POINTS', [
    CARD_TYPE_KITTEN => 2,
    CARD_TYPE_SHOWCAT => 5,     // Show Cat scoring: 5 normally, 7 if player has Kitten
    CARD_TYPE_ALLEYCAT => 1,
    CARD_TYPE_CATNIP => 1,
    CARD_TYPE_ANIMALCONTROL => 0,
    CARD_TYPE_LASERPOINTER => 0
]);

/*
 * Cards that have targeting effects
 */
$this->targeting_cards = [
    CARD_TYPE_ALLEYCAT,
    CARD_TYPE_CATNIP,
    CARD_TYPE_ANIMALCONTROL
];

/*
 * Cards that target hand vs herd
 */
$this->hand_targeting_cards = [
    CARD_TYPE_ALLEYCAT,
    CARD_TYPE_CATNIP
];

$this->herd_targeting_cards = [
    CARD_TYPE_ANIMALCONTROL
];

/*
 * Non-targeting cards
 */
$this->non_targeting_cards = [
    CARD_TYPE_KITTEN,
    CARD_TYPE_SHOWCAT,
    CARD_TYPE_LASERPOINTER
];

/*
 * Targeting Rules Array - defines what each card can target
 */
$this->targeting_rules = [
    CARD_TYPE_ALLEYCAT => [
        'requires_target' => true,
        'target_zone' => TARGET_ZONE_HAND,
        'target_type' => 'opponent_card',
        'effect' => 'discard'
    ],
    CARD_TYPE_CATNIP => [
        'requires_target' => true,
        'target_zone' => TARGET_ZONE_HAND,
        'target_type' => 'opponent_card',
        'effect' => 'steal_to_herd'
    ],
    CARD_TYPE_ANIMALCONTROL => [
        'requires_target' => true,
        'target_zone' => TARGET_ZONE_HERD,
        'target_type' => 'opponent_face_down_card',
        'effect' => 'remove'
    ],
    CARD_TYPE_KITTEN => [
        'requires_target' => false
    ],
    CARD_TYPE_SHOWCAT => [
        'requires_target' => false,
        'special_scoring' => 'kitten_bonus'  // 7 points if player has Kitten
    ],
    CARD_TYPE_LASERPOINTER => [
        'requires_target' => false,
        'special_ability' => 'intercept'
    ]
];

/*
 * Hand bonus calculation
 * For each player still with cards in hand at game end:
 * Add 1 point per 2 cards in hand (rounded up)
 */
$this->hand_bonus_table = [
    0 => 0,   // 0 cards = +0 points
    1 => 1,   // 1 card = +1 point  
    2 => 1,   // 2 cards = +1 point
    3 => 2,   // 3 cards = +2 points
    4 => 2,   // 4 cards = +2 points
    5 => 3,   // 5 cards = +3 points
    6 => 3,   // 6 cards = +3 points
    7 => 4    // 7 cards = +4 points (max starting hand)
];

/*
 * Initial setup constants
 */
define('CARDS_PER_PLAYER', 9);
define('STARTING_HAND_SIZE', 7);
define('CARDS_REMOVED_PER_PLAYER', 2);

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

?>