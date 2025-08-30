<?php
/**
 * material.inc.php - constants and helpers
 */

// Card type constants (printed identities)
define('HC_TYPE_KITTEN', 1);
define('HC_TYPE_SHOWCAT', 2);
define('HC_TYPE_ALLEY', 3);
define('HC_TYPE_CATNIP', 4);
define('HC_TYPE_ANIMAL', 5);
define('HC_TYPE_LASER', 6);

// Herd zone names
define('HC_LOC_HAND', 'hand');         // location_arg = player_id, card_location_arg = position (1..N)
define('HC_LOC_HERD', 'herd');         // face-down
define('HC_LOC_HERD_UP', 'herdup');    // face-up, protected
define('HC_LOC_DISCARD', 'discard');   // location_arg = player_id
define('HC_LOC_REMOVED', 'removed');   // location_arg = player_id
define('HC_LOC_PLAYED', 'played');     // temporary, location_arg = attacker_id

// Targets
define('HC_TGT_NONE', 0);
define('HC_TGT_HAND', 1);
define('HC_TGT_HERD', 2);

// Game state value keys
define('GV_ATTACKER', 1);
define('GV_DEFENDER', 2);
define('GV_PLAYED_CARD_ID', 3);
define('GV_DECLARED_TYPE', 4);
define('GV_TARGET_PLAYER', 5);
define('GV_TARGET_ZONE', 6);
define('GV_TARGET_SLOT', 7);
define('GV_SELECTED_HERD_CARD', 8);
define('GV_CHALLENGER_BITS', 9);
define('GV_FIRST_CHAL_NO', 10);
define('GV_INTERCEPT_ZONE', 11);
define('GV_INTERCEPT_CHAL_BITS', 12);
define('GV_FIRST_INTERCEPT_CHAL_NO', 13);
define('GV_TRUTH_PENALTY_NEXT_NO', 14);
define('GV_PHASE_MARKER', 15);

// State constants
define('ST_GAME_SETUP', 1);
define('ST_PLAYER_DECLARE', 10);
define('ST_CHALLENGE_WINDOW', 11);
define('ST_RESOLVE_CHALLENGE', 12);
define('ST_SELECT_TARGET', 13);
define('ST_INTERCEPT_DECISION', 14);
define('ST_INTERCEPT_CHALLENGE', 15);
define('ST_RESOLVE_INTERCEPT', 16);
define('ST_RESOLVE_EFFECT', 17);
define('ST_BLUFF_PENALTY_PICK', 18);
define('ST_TRUTH_PENALTY_PICK', 19);
define('ST_END_TURN', 98);
define('ST_SCORING', 99);