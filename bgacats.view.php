<?php
/**
 * bgacats.view.php
 */
require_once(APP_BASE_PATH.'view/common/game.view.php');
class view_bgacats_bgacats extends game_view
{
    function getGameName() {
        return "bgacats";
    }

    function build_page($viewArgs) {
        $this->page->begin_block("bgacats_bgacats", "playerboard");
        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $player_id => $info) {
            $this->page->insert_block("playerboard", array(
                "PLAYER_ID" => $player_id,
                "PLAYER_NAME" => $info['player_name'],
            ));
        }
    }
}