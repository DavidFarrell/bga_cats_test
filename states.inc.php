<?php
/**
 * states.inc.php
 */
$machinestates = array(

    ST_GAME_SETUP => array(
        'name' => 'gameSetup',
        'description' => '',
        'type' => 'manager',
        'action' => 'stGameSetup',
        'transitions' => array( '' => ST_PLAYER_DECLARE )
    ),

    ST_PLAYER_DECLARE => array(
        'name' => 'playerDeclare',
        'description' => clienttranslate('${actplayer} must play a card and declare'),
        'descriptionmyturn' => clienttranslate('${you} must play a card and declare'),
        'type' => 'activeplayer',
        'args' => 'argPlayerDeclare',
        'possibleactions' => array('actDeclarePlay'),
        'transitions' => array(
            'toSelectTarget' => ST_SELECT_TARGET,
            'goChallenge' => ST_CHALLENGE_WINDOW
        )
    ),

    ST_CHALLENGE_WINDOW => array(
        'name' => 'challengeWindow',
        'description' => clienttranslate('Challenge window - other players may challenge or pass'),
        'type' => 'multipleactiveplayer',
        'args' => 'argChallengeWindow',
        'possibleactions' => array('actChallenge', 'actPassChallenge'),
        'transitions' => array( 'resolve' => ST_RESOLVE_CHALLENGE )
    ),

    ST_RESOLVE_CHALLENGE => array(
        'name' => 'resolveChallenge',
        'type' => 'game',
        'action' => 'stResolveChallenge',
        'transitions' => array(
            'bluffPenalty' => ST_BLUFF_PENALTY_PICK,
            'truthPenalty' => ST_TRUTH_PENALTY_PICK,
            'noChallenge' => ST_SELECT_TARGET,
            'truthNoPenalty' => ST_SELECT_TARGET,
            'endTurn' => ST_END_TURN
        )
    ),

    ST_BLUFF_PENALTY_PICK => array(
        'name' => 'bluffPenaltyPick',
        'description' => clienttranslate('${actplayer} - pick a blind card from attacker to discard'),
        'descriptionmyturn' => clienttranslate('Pick a blind card from attacker to discard'),
        'type' => 'activeplayer',
        'args' => 'argBluffPenaltyPick',
        'possibleactions' => array('actPickBlindFromHand'),
        'transitions' => array( 
            'done' => ST_END_TURN,
            // New transition used when the penalty comes from a failed intercept: resume the original effect
            'toEffect' => ST_RESOLVE_EFFECT
        )
    ),

    ST_TRUTH_PENALTY_PICK => array(
        'name' => 'truthPenaltyPick',
        'description' => clienttranslate('${actplayer} - pick a blind card from a challenger to discard'),
        'descriptionmyturn' => clienttranslate('Pick a blind card from the next challenger'),
        'type' => 'activeplayer',
        'args' => 'argTruthPenaltyPick',
        'possibleactions' => array('actPickBlindFromHand'),
        'transitions' => array( 
            'next' => ST_TRUTH_PENALTY_PICK,
            // Normal path (after truthful main claim): proceed to target selection
            'done' => ST_SELECT_TARGET,
            // New path (after truthful intercept): go back to resolveIntercept to apply success
            'doneIntercept' => ST_RESOLVE_INTERCEPT
        )
    ),

    ST_SELECT_TARGET => array(
        'name' => 'selectTarget',
        'description' => clienttranslate('${actplayer} must select the target / slot'),
        'descriptionmyturn' => clienttranslate('${you} must select the target / slot'),
        'type' => 'activeplayer',
        'args' => 'argSelectTarget',
        'possibleactions' => array('actSelectTargetPlayer', 'actSelectHandSlot', 'actSelectHerdCard'),
        'transitions' => array(
            'goChallenge' => ST_CHALLENGE_WINDOW,
            'toIntercept' => ST_INTERCEPT_DECISION
        )
    ),

    ST_INTERCEPT_DECISION => array(
        'name' => 'interceptDecision',
        'description' => clienttranslate('${actplayer} - you may declare a Laser Pointer intercept'),
        'descriptionmyturn' => clienttranslate('Declare Laser Pointer intercept or continue'),
        'type' => 'activeplayer',
        'args' => 'argInterceptDecision',
        'possibleactions' => array('actDeclareIntercept','actDeclineIntercept'),
        'transitions' => array( 'toInterceptChallenge' => ST_INTERCEPT_CHALLENGE, 'toResolve' => ST_RESOLVE_EFFECT )
    ),

    ST_INTERCEPT_CHALLENGE => array(
        'name' => 'interceptChallenge',
        'description' => clienttranslate('Intercept - others may challenge or pass'),
        'type' => 'multipleactiveplayer',
        'args' => 'argInterceptChallengeWindow',
        'possibleactions' => array('actChallengeIntercept','actPassIntercept'),
        'transitions' => array( 'resolve' => ST_RESOLVE_INTERCEPT )
    ),

    ST_RESOLVE_INTERCEPT => array(
        'name' => 'resolveIntercept',
        'type' => 'game',
        'action' => 'stResolveIntercept',
        'transitions' => array(
            'success' => ST_END_TURN,
            'liePenalty' => ST_BLUFF_PENALTY_PICK,  // reuse picker flow: first intercept challenger picks from defender
            'failProceed' => ST_RESOLVE_EFFECT
        )
    ),

    ST_RESOLVE_EFFECT => array(
        'name' => 'resolveEffect',
        'type' => 'game',
        'action' => 'stResolveEffect',
        'transitions' => array( 'endTurn' => ST_END_TURN )
    ),

    ST_END_TURN => array(
        'name' => 'endTurn',
        'type' => 'game',
        'action' => 'stEndTurn',
        'transitions' => array( 'next' => ST_PLAYER_DECLARE, 'scoring' => ST_SCORING )
    ),

    ST_SCORING => array(
        'name' => 'scoring',
        'type' => 'game',
        'action' => 'stComputeScores',
        'transitions' => array( 'endGame' => 99 )
    ),
);