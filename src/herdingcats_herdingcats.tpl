{OVERALL_GAME_HEADER}

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
-- in the "main game zone" of the screen.
--
-- You can use in this template:
--   _ variables, with the format {MY_VARIABLE_ELEMENT}
--   _ HTML block, with the BEGIN/END format
--
-- See your "view" PHP file to check how to set variables and control blocks
-->

<div id="hc_game_area">
    <!-- Current Player Hand Area -->
    <div id="hc_current_hand_area" class="hc_hand_area">
        <h3 id="hc_hand_title">{YOUR_HAND_TITLE}</h3>
        <div id="hc_current_hand" class="hc_hand">
            <!-- Current player's hand cards will be dynamically added here -->
        </div>
    </div>

    <!-- Game Control Panel -->
    <div id="hc_control_panel">
        <div id="hc_current_action" class="hc_action_area">
            <div id="hc_action_prompts">
                <!-- Dynamic prompts and action buttons will appear here -->
            </div>
        </div>
    </div>

    <!-- Players Board Area -->
    <div id="hc_players_area">
        <!-- BEGIN player_board -->
        <div id="hc_player_board_{PLAYER_ID}" class="hc_player_board" style="border-color: #{PLAYER_COLOR};">
            
            <!-- Player Info Header -->
            <div class="hc_player_info">
                <div class="hc_player_name_panel" style="background-color: #{PLAYER_COLOR};">
                    <span class="hc_player_name" style="color: {PLAYER_NAME_COLOR};">{PLAYER_NAME}</span>
                </div>
                <div class="hc_player_stats">
                    <span class="hc_hand_count_label">Hand: </span>
                    <span id="hc_hand_count_{PLAYER_ID}" class="hc_hand_count">{HAND_COUNT}</span>
                    <span class="hc_score_label">Score: </span>
                    <span id="hc_score_{PLAYER_ID}" class="hc_score">{PLAYER_SCORE}</span>
                </div>
            </div>

            <!-- Player Herd Area -->
            <div class="hc_herd_area">
                <div class="hc_herd_title">Herd:</div>
                <div id="hc_herd_{PLAYER_ID}" class="hc_herd">
                    <div id="hc_herd_face_down_{PLAYER_ID}" class="hc_herd_face_down">
                        <!-- Face-down herd cards will be added here -->
                    </div>
                    <div id="hc_herd_face_up_{PLAYER_ID}" class="hc_herd_face_up">
                        <!-- Face-up herd cards will be added here -->
                    </div>
                </div>
            </div>

            <!-- Player Discard Pile -->
            <div class="hc_discard_area">
                <div class="hc_discard_title">Discard:</div>
                <div id="hc_discard_{PLAYER_ID}" class="hc_discard_pile">
                    <!-- Discarded cards will be added here -->
                </div>
            </div>

        </div>
        <!-- END player_board -->
    </div>

    <!-- Target Selection Overlay (hidden by default) -->
    <div id="hc_target_overlay" class="hc_overlay" style="display: none;">
        <div class="hc_target_selection">
            <h3 id="hc_target_title">Select Target</h3>
            <div id="hc_target_options">
                <!-- Target selection buttons will be added here dynamically -->
            </div>
            <button id="hc_cancel_target" class="hc_button hc_cancel_button">Cancel</button>
        </div>
    </div>

    <!-- Card Declaration Overlay (hidden by default) -->
    <div id="hc_declare_overlay" class="hc_overlay" style="display: none;">
        <div class="hc_declaration_panel">
            <h3>Declare Card Type</h3>
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
var jstpl_hand_card = '<div class="hc_card hc_hand_card" id="hc_card_${card_id}" data-card-id="${card_id}" data-card-type="${card_type}"></div>';

var jstpl_herd_card_face_down = '<div class="hc_card hc_herd_card hc_face_down" id="hc_card_${card_id}" data-card-id="${card_id}" data-declared-type="${declared_type}"></div>';

var jstpl_herd_card_face_up = '<div class="hc_card hc_herd_card hc_face_up" id="hc_card_${card_id}" data-card-id="${card_id}" data-card-type="${card_type}"></div>';

var jstpl_discard_card = '<div class="hc_card hc_discard_card hc_face_up" id="hc_card_${card_id}" data-card-id="${card_id}" data-card-type="${card_type}"></div>';

var jstpl_target_button = '<button class="hc_target_btn" data-target-id="${target_id}" data-target-zone="${target_zone}">${target_name}</button>';

var jstpl_challenge_prompt = '<div class="hc_challenge_prompt">Do you want to challenge ${player_name}\'s declaration of ${card_name}?</div>';
</script>

{OVERALL_GAME_FOOTER}