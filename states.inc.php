<?php
/**
 * States
 *
 * Note the extra transition 'declaredTarget' out of 'awaitDeclaration'.
 * Backend should call $this->gamestate->nextState('declaredTarget') when
 * the declared card requires a target (e.g. Alley Cat, Animal Control).
 */

$machinestates = array(

  // 1xx: game management
  1 => array(
    'name' => 'gameSetup',
    'type' => 'manager',
    'description' => clienttranslate('Game setup'),
    'action' => 'stGameSetup',
    'transitions' => array( '' => 10 )
  ),

  // 10: active player declares a card to play
  10 => array(
    'name' => 'awaitDeclaration',
    'type' => 'activeplayer',
    'description' => clienttranslate('${actplayer} must declare a card and play it'),
    'descriptionmyturn' => clienttranslate('${you} must declare a card identity and play it'),
    'args' => 'argAwaitDeclaration',
    'possibleactions' => array('actDeclare'),
    // NB: we keep the existing 'declared' -> challengeWindow for non-targeting cards
    // and add 'declaredTarget' -> selectTarget to target first when needed.
    'transitions' => array(
      'declared'       => 20, // old flow (e.g. Kitten, Show Cat, Catnip)
      'declaredTarget' => 40  // new flow for Alley Cat / Animal Control
    ),
  ),

  // 20: challenge window for the initial declaration
  20 => array(
    'name' => 'challengeWindow',
    'type' => 'multipleactiveplayer',
    'description' => clienttranslate('Waiting for possible challenges'),
    'descriptionmyturn' => clienttranslate('You may challenge the declaration'),
    'action' => 'stEnterChallengeWindow',
    'args' => 'argChallengeWindow',
    'possibleactions' => array('actChallenge','actPassChallenge'),
    'transitions' => array(
      'challenged'   => 30,
      'unchallenged' => 40, // go to select target if not done yet
    ),
  ),

  // 30: resolve challenge
  30 => array(
    'name' => 'resolveChallenge',
    'type' => 'game',
    'action' => 'stResolveChallenge',
    'transitions' => array(
      'bluffCaught'      => 31,
      'challengeFailed'  => 32,
      'goToTarget'       => 40
    ),
  ),

  // 31/32: penalties after challenge resolution
  31 => array(
    'name' => 'challengerSelectBluffPenalty',
    'type' => 'activeplayer',
    'description' => clienttranslate('A challenger may discard one card from the actor\'s hand'),
    'descriptionmyturn' => clienttranslate('Select one card from the actor\'s hand to discard'),
    'args' => 'argChallengerSelectBluffPenalty',
    'possibleactions' => array('actSelectBlindFromActor'),
    'transitions' => array('penaltyApplied' => 95, 'zombie' => 95),
  ),

  32 => array(
    'name' => 'attackerSelectTruthfulPenalty',
    'type' => 'activeplayer',
    'description' => clienttranslate('${actor_name} may discard a card from ${challenger_name}\'s hand'),
    'descriptionmyturn' => clienttranslate('You may discard one card from ${challenger_name}\'s hand'),
    'args' => 'argAttackerSelectTruthfulPenalty',
    'possibleactions' => array('actSelectBlindFromChallenger'),
    'transitions' => array('nextPlayer' => 95, 'penaltyApplied' => 40, 'zombie' => 40),
  ),

  // 40: select target (player or zone)
  40 => array(
    'name' => 'selectTarget',
    'type' => 'activeplayer',
    'description' => clienttranslate('${actplayer} must select a target'),
    'descriptionmyturn' => clienttranslate('${you} must select a target'),
    'action' => 'stEnterSelectTarget',
    'args'   => 'argSelectTarget',
    'possibleactions' => array('actSelectTargetSlot','actSkipTargeting'),
    'transitions' => array(
      'targetSelected' => 50,
      'noTargeting'    => 80,
      'zombie'         => 80
    ),
  ),

  // 50..90: unchanged from your current file (intercept, reveal, add to herd, end turn)
  50 => array(
    'name' => 'interceptDeclare',
    'type' => 'activeplayer',
    'description' => clienttranslate('${target_player} may intercept with Laser Pointer'),
    'args' => 'argInterceptDeclare',
    'possibleactions' => array('actDeclareIntercept','actPassIntercept'),
    'transitions' => array('interceptDeclared' => 60, 'noIntercept' => 80, 'zombie' => 80),
  ),

  60 => array(
    'name' => 'interceptChallengeWindow',
    'type' => 'multipleactiveplayer',
    'description' => clienttranslate('Players may challenge ${defender}\'s Laser Pointer intercept'),
    'args' => 'argInterceptChallengeWindow',
    'possibleactions' => array('actChallengeIntercept','actPassChallengeIntercept'),
    'transitions' => array('interceptChallenged' => 70,'interceptUnchallenged' => 80),
  ),

  70 => array(
    'name' => 'resolveInterceptChallenge',
    'type' => 'game',
    'action' => 'stResolveInterceptChallenge',
    'transitions' => array(
      'interceptBluffCaught'   => 75,
      'interceptChallengeFailed'=> 80,
      'interceptGoToResolve'   => 80
    ),
  ),

  75 => array(
    'name' => 'interceptChallengerSelectPenalty',
    'type' => 'activeplayer',
    'description' => clienttranslate('${intercept_challenger} selects penalty card from ${bluffer}'),
    'args' => 'argInterceptChallengerSelectPenalty',
    'possibleactions' => array('actSelectBlindFromActor'),
    'transitions' => array('interceptPenaltyApplied' => 80, 'zombie' => 80),
  ),

  80 => array(
    'name' => 'revealAndResolve',
    'type' => 'game',
    'action' => 'stRevealAndResolve',
    'transitions' => array('effectResolved' => 90),
  ),

  90 => array(
    'name' => 'addPlayedCardToHerd',
    'type' => 'game',
    'action' => 'stAddPlayedCardToHerd',
    'transitions' => array('cardAdded' => 95),
  ),

  95 => array(
    'name' => 'endTurn',
    'type' => 'game',
    'action' => 'stEndTurn',
    'updateGameProgression' => true,
    'transitions' => array('gameEnd' => 99, 'nextPlayer' => 10, 'zombie' => 10),
  ),

  99 => array(
    'name' => 'endScore',
    'type' => 'game',
    'action' => 'stEndScore',
    'transitions' => array( '' => 99 ),
  )
);