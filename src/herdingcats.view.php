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
 * herdingcats.view.php
 *
 * This is your "view" file.
 *
 * The role of this file is to
 * _ collect relevant information
 * _ check that information is available and valid
 * _ pass that information to the appropriate template and show it to the user
 *
 * The global variable g_user and the method getCurrentPlayerId() are available.
 *
 * It will be called each time your game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 */

require_once(APP_BASE_PATH . "view/common/game.view.php");

class view_herdingcats_herdingcats extends game_view
{
    function getGameName()
    {
        // Used for translations and stuff. Please do not modify.
        return "herdingcats";
    }

    function build_page($viewArgs)
    {
        // Get players & current player information
        global $g_user;
        $current_player_id = $g_user->get_id();

        $template = self::getGameName() . "_" . self::getGameName();

        // Get current game status
        $players = $this->game->loadPlayersBasicInfos();
        $gamestate = $this->game->gamestate;

        // Get all data for display
        $all_datas = $this->game->getAllDatas();

        /*
         * Template Variables
         */
        
        // Main template data
        $this->tpl['YOUR_HAND_TITLE'] = self::_("Your Hand");

        /*
         * Player boards
         */
        $this->page->begin_block($template, "player_board");
        
        foreach ($players as $player_id => $player) {
            // Player color and text contrast
            $player_color = $player['player_color'];
            $name_color = $this->getContrastColor($player_color);
            
            // Get player statistics
            $hand_count = isset($all_datas['hand_counts'][$player_id]) ? 
                         $all_datas['hand_counts'][$player_id] : 0;
            // In new framework, loadPlayersBasicInfos may not include 'player_score'. Pull from getAllDatas if available.
            $player_score = 0;
            if (isset($all_datas['players']) && isset($all_datas['players'][$player_id]) && isset($all_datas['players'][$player_id]['score'])) {
                $player_score = (int)$all_datas['players'][$player_id]['score'];
            }

            // Set template variables for this player
            $this->page->insert_block("player_board", [
                'PLAYER_ID' => $player_id,
                'PLAYER_NAME' => $player['player_name'],
                'PLAYER_COLOR' => $player_color,
                'PLAYER_NAME_COLOR' => $name_color,
                'HAND_COUNT' => $hand_count,
                'PLAYER_SCORE' => $player_score
            ]);
        }

        // Pass all game data to client side through JavaScript
        $this->tpl['GAME_DATA'] = json_encode($all_datas);
        
        // No explicit show() call needed in the new framework

    }

    /**
     * Get contrasting text color (white or black) for background color
     * @param string $hexColor - hex color without #
     * @return string - "white" or "black"
     */
    function getContrastColor($hexColor) 
    {
        // Convert hex to RGB
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));
        
        // Calculate relative luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        
        // Return contrasting color
        return $luminance > 0.5 ? "black" : "white";
    }
}