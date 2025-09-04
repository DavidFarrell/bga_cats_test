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
 * herdingcats.action.php
 *
 * HerdingCats main action entry point
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * bgaPerformAction('myAction', { 
 *    'parameter1': myParameter1,
 *    'parameter2': myParameter2,
 *    ...
 * });
 */
  
class action_herdingcats extends APP_GameAction
{ 
    // Constructor: please do not modify
    public function __default()
    {
        if( self::isArg( 'notifwindow') )
        {
            $this->view = "common_notifwindow";
            $this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
        }
        else
        {
            $this->view = "herdingcats_herdingcats";
            self::trace( "Complete reinitialization." );
        }
    } 
    
    // TODO: defines your action entry points there

    /*
    
    Example:
  	
    public function myAction()
    {
        self::setAjaxMode();     

        // Retrieve arguments
        // Note: these arguments correspond to what has been sent through the javascript "bgaPerformAction" call
        $arg1 = self::getArg( "myArgument1", AT_posint, true );
        $arg2 = self::getArg( "myArgument2", AT_posint, true );

        // Then, call the appropriate method in your game logic, like:
        $this->game->myAction( $arg1, $arg2 );

        self::ajaxResponse( );
    }
    
    */

    //////////////////////////////////////////////////////////////////////////////
    //////////// Declaration Phase Actions
    //////////// 

    /**
     * Player declares a card identity and plays it
     * 
     * @param int $card_id - The card being played from hand
     * @param string $declared_type - What card type the player claims it is
     * @param int|null $target_player_id - Target player for targeted effects (null for non-targeting cards)
     */
    public function actDeclare()
    {
        self::setAjaxMode();
        
        // Retrieve arguments from JavaScript call
        $card_id = self::getArg("card_id", AT_posint, true);
        $declared_type = self::getArg("declared_type", AT_alphanum, true);
        $target_player_id = self::getArg("target_player_id", AT_posint, false);
        
        // Check action is valid
        $this->game->checkAction('actDeclare');
        
        // Call the game logic method
        $this->game->actDeclare($card_id, $declared_type, $target_player_id);
        
        self::ajaxResponse();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Challenge Phase Actions
    //////////// 

    /**
     * Player challenges the declaration made by the active player (new stack)
     */
    public function actChallenge()
    {
        self::setAjaxMode();
        // Check action is valid; new-stack server derives actor/challenger
        $this->game->checkAction('actChallenge');
        $this->game->actChallenge();

        self::ajaxResponse();
    }

    /**
     * Player passes on challenging the declaration
     */
    public function actPassChallenge()
    {
        self::setAjaxMode();
        
        // Check action is valid
        $this->game->checkAction('actPassChallenge');
        
        $this->game->actPassChallenge();
        
        self::ajaxResponse();
    }

    /**
     * After a successful challenge (bluff caught), challenger selects a card from actor's hand to discard
     * 
     * @param int $card_index - Index of card in actor's hand (0-based, for blind selection)
     */
    public function actSelectBlindFromActor()
    {
        self::setAjaxMode();
        
        // Accept both 1-based card_index and legacy slot_index
        $card_index = self::getArg("card_index", AT_posint, false);
        if ($card_index === null) {
            $card_index = self::getArg("slot_index", AT_posint, true);
        }
        
        // Check action is valid
        $this->game->checkAction('actSelectBlindFromActor');
        
        $this->game->actSelectBlindFromActor($card_index);
        
        self::ajaxResponse();
    }

    /**
     * After a failed challenge (actor was truthful), actor selects penalty cards from challengers
     * 
     * @param int $player_id - The challenger to penalize
     * @param int $card_index - Index of card in challenger's hand (0-based, for blind selection)
     */
    public function actSelectBlindFromChallenger()
    {
        self::setAjaxMode();
        
        $player_id = self::getArg("player_id", AT_posint, true);
        // Accept both 1-based card_index and legacy slot_index
        $card_index = self::getArg("card_index", AT_posint, false);
        if ($card_index === null) {
            $card_index = self::getArg("slot_index", AT_posint, true);
        }
        
        // Check action is valid
        $this->game->checkAction('actSelectBlindFromChallenger');
        
        $this->game->actSelectBlindFromChallenger($player_id, $card_index);
        
        self::ajaxResponse();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Target Selection Phase Actions
    //////////// 

    /**
     * Player selects the specific target for their targeting effect.
     * For legacy compatibility, accepts either `player_id` (preferred) or `slot_index` (older clients).
     * The server validates timing and treats the numeric value strictly as a player id during selectTarget.
     *
     * @param int $player_id - Target player id (preferred)
     * @param int $slot_index - Legacy numeric param (treated as player id in selectTarget)
     * @param string $zone - Target zone ("hand" or "herd")
     */
    public function actSelectTargetSlot()
    {
        self::setAjaxMode();

        // Accept both player_id and legacy slot_index; prefer player_id when provided
        $player_id = self::getArg("player_id", AT_posint, false);
        $slot_index = self::getArg("slot_index", AT_posint, false);
        $zone = self::getArg("zone", AT_alphanum, true);

        $id = $player_id ? $player_id : $slot_index;
        if (!$id) {
            throw new BgaUserException("Missing target identifier");
        }

        // Check action is valid
        $this->game->checkAction('actSelectTargetSlot');

        $this->game->actSelectTargetSlot($id, $zone);

        self::ajaxResponse();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Intercept Phase Actions
    //////////// 

    /**
     * Defender declares an intercept with Laser Pointer
     * 
     * @param int $card_id - The Laser Pointer card being used to intercept
     * @param string $zone - Zone the Laser Pointer is from ("hand" or "herd")
     */
    public function actDeclareIntercept()
    {
        self::setAjaxMode();
        
        $card_id = self::getArg("card_id", AT_posint, true);
        $zone = self::getArg("zone", AT_alphanum, true);
        
        // Check action is valid
        $this->game->checkAction('actDeclareIntercept');
        
        $this->game->actDeclareIntercept($card_id, $zone);
        
        self::ajaxResponse();
    }

    /**
     * Defender passes on intercepting
     */
    public function actPassIntercept()
    {
        self::setAjaxMode();
        
        // Check action is valid
        $this->game->checkAction('actPassIntercept');
        
        $this->game->actPassIntercept();
        
        self::ajaxResponse();
    }

    /**
     * Player challenges the intercept declaration
     */
    public function actChallengeIntercept()
    {
        self::setAjaxMode();
        
        // Check action is valid
        $this->game->checkAction('actChallengeIntercept');
        
        $this->game->actChallengeIntercept();
        
        self::ajaxResponse();
    }

    /**
     * Player passes on challenging the intercept
     */
    public function actPassChallengeIntercept()
    {
        self::setAjaxMode();
        
        // Check action is valid
        $this->game->checkAction('actPassChallengeIntercept');
        
        $this->game->actPassChallengeIntercept();
        
        self::ajaxResponse();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Final Presentation (Ack)
    //////////// 

    /**
     * Player acknowledges the final scoring modal so the game can proceed to end screen.
     */
    public function actAcknowledgeFinal()
    {
        self::setAjaxMode();
        $this->game->checkAction('actAcknowledgeFinal');
        $this->game->actAcknowledgeFinal();
        self::ajaxResponse();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Debug Actions (if needed)
    //////////// 

    /**
     * Skip targeting for non-targeting cards
     */
    public function actSkipTargeting()
    {
        self::setAjaxMode();
        
        // Check action is valid
        $this->game->checkAction('actSkipTargeting');
        
        $this->game->actSkipTargeting();
        
        self::ajaxResponse();
    }

    /**
     * Debug action - jump to a specific game state for testing
     * Only available in studio mode
     */
    public function actDebugGoToState()
    {
        self::setAjaxMode();
        
        $state = self::getArg("state", AT_posint, false, 3);
        
        $this->game->debug_goToState($state);
        
        self::ajaxResponse();
    }

    /**
     * Client log relay for debugging (Studio only)
     */
    public function actClientLog()
    {
        self::setAjaxMode();
        $level = self::getArg('level', AT_alphanum, false, 'log');
        // Accept a simple alphanumeric debug tag from client
        $msg = self::getArg('msg', AT_alphanum, true);
        error_log('[HC CLIENT '.$level.'] '.$msg);
        self::ajaxResponse();
    }

    /*
    
    TODO: Add more debug actions as needed for testing:
    
    public function actDebugSetCardInHand()
    {
        self::setAjaxMode();
        
        $card_type = self::getArg("card_type", AT_posint, true);
        $player_id = self::getArg("player_id", AT_posint, true);
        
        $this->game->debug_setCardInHand($card_type, $player_id);
        
        self::ajaxResponse();
    }
    
    */
}
