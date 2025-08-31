<?php
/**
 * bgacats.action.php - Action handler
 */
class action_bgacats extends APP_GameAction
{
    public function __default()
    {
        if (self::isArg('notifwindow')) {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
        } else {
            $this->view = "bgacats_bgacats";
            self::trace("Complete reinitialization of board game");
        }
    }
    
    public function actDeclarePlay()
    {
        self::setAjaxMode();
        $card_id = self::getArg("card_id", AT_posint, true);
        $declared_type = self::getArg("declared_type", AT_posint, true);
        $target_player_id = self::getArg("target_player_id", AT_posint, false, 0);
        $this->game->actDeclarePlay($card_id, $declared_type, $target_player_id);
        self::ajaxResponse();
    }
    
    public function actChallenge()
    {
        self::setAjaxMode();
        $this->game->actChallenge();
        self::ajaxResponse();
    }
    
    public function actPassChallenge()
    {
        self::setAjaxMode();
        $this->game->actPassChallenge();
        self::ajaxResponse();
    }
    
    public function actPickBlindFromHand()
    {
        self::setAjaxMode();
        $target_player_id = self::getArg("target_player_id", AT_posint, true);
        $slot_index = self::getArg("slot_index", AT_posint, true);
        $this->game->actPickBlindFromHand($target_player_id, $slot_index);
        self::ajaxResponse();
    }
    
    public function actSelectTargetPlayer()
    {
        self::setAjaxMode();
        $target_player_id = self::getArg("target_player_id", AT_posint, true);
        $this->game->actSelectTargetPlayer($target_player_id);
        self::ajaxResponse();
    }
    
    public function actSelectHandSlot()
    {
        self::setAjaxMode();
        $target_player_id = self::getArg("target_player_id", AT_posint, true);
        $slot_index = self::getArg("slot_index", AT_posint, true);
        $this->game->actSelectHandSlot($target_player_id, $slot_index);
        self::ajaxResponse();
    }
    
    public function actSelectHerdCard()
    {
        self::setAjaxMode();
        $target_player_id = self::getArg("target_player_id", AT_posint, true);
        $card_id = self::getArg("card_id", AT_posint, true);
        $this->game->actSelectHerdCard($target_player_id, $card_id);
        self::ajaxResponse();
    }
    
    public function actSkipTarget()
    {
        self::setAjaxMode();
        $this->game->actSkipTarget();
        self::ajaxResponse();
    }
    
    public function actDeclineIntercept()
    {
        self::setAjaxMode();
        $this->game->actDeclineIntercept();
        self::ajaxResponse();
    }
    
    public function actDeclareIntercept()
    {
        self::setAjaxMode();
        $zone = self::getArg("zone", AT_posint, true);
        $this->game->actDeclareIntercept($zone);
        self::ajaxResponse();
    }
    
    public function actChallengeIntercept()
    {
        self::setAjaxMode();
        $this->game->actChallengeIntercept();
        self::ajaxResponse();
    }
    
    public function actPassIntercept()
    {
        self::setAjaxMode();
        $this->game->actPassIntercept();
        self::ajaxResponse();
    }
}