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
 * states.inc.php
 *
 * HerdingCats game states description
 *
 */

use Bga\GameFramework\GameStateBuilder;
use Bga\GameFramework\StateType;

/*
   Game state machine for Herding Cats bluffing card game.
   
   Flow:
   1. Player declares a card identity and targets (if applicable)
   2. Challenge window - other players may challenge
   3. If challenged, resolve truth/bluff
   4. If targeting, player selects specific target
   5. Defender may intercept with Laser Pointer
   6. Intercept may be challenged
   7. Resolve effect and add card to herd
   8. Check end condition, next player or game end
*/

//    !! It is not a good idea to modify this file when a game is running !!

$machinestates = [
    // Game setup
    1 => GameStateBuilder::gameSetup(10)->build(),

    // ========== MAIN TURN FLOW ========== 
    
    10 => GameStateBuilder::create()
        ->name('awaitDeclaration')
        ->description(clienttranslate('${actplayer} must declare a card and play it'))
        ->descriptionmyturn(clienttranslate('${you} must declare a card identity and play it'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argAwaitDeclaration')
         ->possibleactions([
             'actDeclare'
         ])
         ->transitions([
             // Branch: targeted cards go to target selection first; others go to challenge
             'declaredToTarget' => 40,
             'declaredToChallenge' => 20,
         ])
        ->build(),

    // ========== CHALLENGE SYSTEM ========== 

    20 => GameStateBuilder::create()
        ->name('challengeWindow')
        ->description(clienttranslate('Waiting for possible challenges'))
        ->descriptionmyturn(clienttranslate('You may challenge the declaration'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->args('argChallengeWindow')
        ->action('stEnterChallengeWindow')
        ->possibleactions([
            'actChallenge',
            'actPassChallenge'
        ])
        ->transitions([
            'challenged' => 30,
            'unchallenged' => 30,
        ])
        ->build(),

    30 => GameStateBuilder::create()
        ->name('resolveChallenge')
        ->description('Resolving challenge')
        ->type(StateType::GAME)
        ->action('stResolveChallenge')
        ->transitions([
            'bluffCaught' => 31,       // Player was bluffing
            'challengeFailed' => 32,   // Player was truthful 
            'goToTarget' => 40,        // Select target now
            'goToIntercept' => 50,     // Target already chosen; proceed to intercept
            'goToResolve' => 80,       // No targeting; resolve
        ])
        ->build(),

    31 => GameStateBuilder::create()
        ->name('challengerSelectBluffPenalty')
        ->description(clienttranslate('A challenger may discard one card from the actor\'s hand'))
        ->descriptionmyturn(clienttranslate('Select one card from the actor\'s hand to discard'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argChallengerSelectBluffPenalty')
        ->possibleactions([
            'actSelectBlindFromActor'
        ])
        ->transitions([
            'penaltyApplied' => 95,  // End turn
            'zombie' => 95,  // Handle zombie players
        ])
        ->build(),

    32 => GameStateBuilder::create()
        ->name('attackerSelectTruthfulPenalty')
        ->description(clienttranslate('${actor_name} may discard a card from ${challenger_name}\'s hand'))
        ->descriptionmyturn(clienttranslate('You may discard one card from ${challenger_name}\'s hand'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argAttackerSelectTruthfulPenalty')
        ->possibleactions([
            'actSelectBlindFromChallenger'
        ])
        ->transitions([
            'nextPlayer' => 95, // Assuming 95 is the state for next player/end of turn
            'penaltyApplied' => 50,  // Default path (e.g., after failed challenge)
            'toResolve' => 80,       // Used when this state resolves the main effect directly
            'zombie' => 80,  // Handle zombie players
        ])
        ->build(),

    // ========== TARGET SELECTION ========== 

    40 => GameStateBuilder::create()
        ->name('selectTarget')
        ->description(clienttranslate('${actplayer} must select a target'))
        ->descriptionmyturn(clienttranslate('${you} must select a target')) 
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argSelectTarget')
        ->action('stEnterSelectTarget')
        ->possibleactions([
            'actSelectTargetSlot',
            'actSkipTargeting'  // For non-targeting cards
        ])
        ->transitions([
            'targetSelected' => 20, // After target, go to challenge window
            'noTargeting' => 20,    // If no targeting, go to challenge window
            'zombie' => 80,         // Handle zombie players
        ])
        ->build(),

    // ========== INTERCEPTION SYSTEM ========== 

    50 => GameStateBuilder::create()
        ->name('interceptDeclare')
        ->description(clienttranslate('${target_player} may intercept with Laser Pointer'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argInterceptDeclare')
        ->possibleactions([
            'actDeclareIntercept',
            'actPassIntercept'
        ])
        ->transitions([
            'interceptDeclared' => 60,
            'noIntercept' => 80,
            'noInterceptPenalty' => 52,
            'zombie' => 80,  // Handle zombie players
        ])
        ->build(),

    // Prepare attacker-controlled penalty selection (e.g., Alley Cat) after defender passes intercept
    52 => GameStateBuilder::create()
        ->name('prepareAttackerPenalty')
        ->description('Preparing attacker penalty selection')
        ->type(StateType::GAME)
        ->action('stPrepareAttackerPenalty')
        ->transitions([
            'toPenalty' => 32,
        ])
        ->build(),

    60 => GameStateBuilder::create()
        ->name('interceptChallengeWindow')
        ->description(clienttranslate('Players may challenge ${defender}\'s Laser Pointer intercept'))
        ->type(StateType::MULTIPLE_ACTIVE_PLAYER)
        ->args('argInterceptChallengeWindow')
        ->possibleactions([
            'actChallengeIntercept',
            'actPassChallengeIntercept'
        ])
        ->transitions([
            'interceptChallenged' => 70,
            'interceptUnchallenged' => 80,
        ])
        ->build(),

    70 => GameStateBuilder::create()
        ->name('resolveInterceptChallenge')
        ->description('Resolving intercept challenge')
        ->type(StateType::GAME)
        ->action('stResolveInterceptChallenge')
        ->transitions([
            'interceptBluffCaught' => 75,     // Defender was bluffing about Laser Pointer
            'interceptChallengeFailed' => 80, // Defender really had Laser Pointer
            'interceptGoToResolve' => 80,     // Minimal path
        ])
        ->build(),

    75 => GameStateBuilder::create()
        ->name('interceptChallengerSelectPenalty')
        ->description(clienttranslate('${intercept_challenger} selects penalty card from ${bluffer}'))
        ->type(StateType::ACTIVE_PLAYER)
        ->args('argInterceptChallengerSelectPenalty')
        ->possibleactions([
            'actSelectBlindFromActor'  // Reuse existing action for intercept penalty
        ])
        ->transitions([
            'interceptPenaltyApplied' => 80,
            'zombie' => 80,  // Handle zombie players
        ])
        ->build(),

    // ========== EFFECT RESOLUTION ========== 

    80 => GameStateBuilder::create()
        ->name('revealAndResolve')
        ->description('Revealing card and resolving effect')
        ->type(StateType::GAME)
        ->action('stRevealAndResolve')
        ->transitions([
            'effectResolved' => 90,
        ])
        ->build(),

    90 => GameStateBuilder::create()
        ->name('addPlayedCardToHerd')
        ->description('Adding played card to herd')
        ->type(StateType::GAME)
        ->action('stAddPlayedCardToHerd')
        ->transitions([
            'cardAdded' => 95,
        ])
        ->build(),

    // ========== TURN END ========== 

    95 => GameStateBuilder::create()
        ->name('endTurn')
        ->description('Checking end conditions')
        ->type(StateType::GAME)
        ->action('stEndTurn')
        ->updateGameProgression(true)
        ->transitions([
            'gameEnd' => 99,
            'nextPlayer' => 10,
            'zombie' => 10,  // Handle zombie players
        ])
        ->build(),

    // ========== GAME END ========== 

    99 => GameStateBuilder::endScore()->build(),
];
