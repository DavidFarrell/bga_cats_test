<?php
/**
 * Herding Cats - main game class
 */
require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');

class bgacats extends Table
{
    /** @var Deck */
    protected $cards;

    function __construct() {
        parent::__construct();
        self::initGameStateLabels(array(
            GV_ATTACKER => GV_ATTACKER,
            GV_DEFENDER => GV_DEFENDER,
            GV_PLAYED_CARD_ID => GV_PLAYED_CARD_ID,
            GV_DECLARED_TYPE => GV_DECLARED_TYPE,
            GV_TARGET_PLAYER => GV_TARGET_PLAYER,
            GV_TARGET_ZONE => GV_TARGET_ZONE,
            GV_TARGET_SLOT => GV_TARGET_SLOT,
            GV_SELECTED_HERD_CARD => GV_SELECTED_HERD_CARD,
            GV_CHALLENGER_BITS => GV_CHALLENGER_BITS,
            GV_FIRST_CHAL_NO => GV_FIRST_CHAL_NO,
            GV_INTERCEPT_ZONE => GV_INTERCEPT_ZONE,
            GV_INTERCEPT_CHAL_BITS => GV_INTERCEPT_CHAL_BITS,
            GV_FIRST_INTERCEPT_CHAL_NO => GV_FIRST_INTERCEPT_CHAL_NO,
            GV_TRUTH_PENALTY_NEXT_NO => GV_TRUTH_PENALTY_NEXT_NO,
            GV_PHASE_MARKER => GV_PHASE_MARKER,
        ));
        $this->cards = self::getNew('module.common.deck');
        $this->cards->init('card');
        require_once('modules/HCRules.php');
    }

    protected function getGameName() {
        return 'bgacats';
    }

    // Utility getters
    protected function getPlayerIds() {
        return array_keys(self::loadPlayersBasicInfos());
    }

    protected function getOrderedPlayerIds($startPlayerId=null) {
        $players = self::getCollectionFromDb("SELECT player_id id, player_no no FROM player ORDER BY player_no ASC");
        $ids = array();
        foreach ($players as $p) $ids[] = intval($p['id']);
        if ($startPlayerId === null) return $ids;
        // rotate so that start is first
        while ($ids[0] != $startPlayerId) {
            $x = array_shift($ids);
            $ids[] = $x;
        }
        return $ids;
    }

    protected function nextPlayerId($pid) {
        $ids = $this->getOrderedPlayerIds($pid);
        array_shift($ids);
        return $ids[0];
    }

    // Setup
    function stGameSetup() {
        $players = self::loadPlayersBasicInfos();
        $sql = "UPDATE player SET player_score=0";
        self::DbQuery($sql);

        // Create cards - 9 per player
        $cards_to_create = array();
        foreach ($players as $pid => $p) {
            $types = array_merge(
                array_fill(0, 3, HC_TYPE_KITTEN),
                array(HC_TYPE_SHOWCAT),
                array_fill(0, 2, HC_TYPE_ALLEY),
                array(HC_TYPE_CATNIP),
                array(HC_TYPE_ANIMAL),
                array(HC_TYPE_LASER)
            );
            shuffle($types);
            $hand = array_slice($types, 0, 7);
            $removed = array_slice($types, 7, 2);
            $pos = 1;
            foreach ($hand as $t) {
                $cards_to_create[] = array('type'=>$t,'type_arg'=>0,'nbr'=>1,'location'=>HC_LOC_HAND,'location_arg'=>$pid,'pos'=>$pos);
                $pos++;
            }
            foreach ($removed as $t) {
                $cards_to_create[] = array('type'=>$t,'type_arg'=>0,'nbr'=>1,'location'=>HC_LOC_REMOVED,'location_arg'=>$pid);
            }
        }
        // Using Deck::createCards expects entries without 'pos', so we create and then set positions
        $create = array();
        foreach ($cards_to_create as $c) {
            $create[] = array('type'=>$c['type'],'type_arg'=>$c['type_arg'],'nbr'=>1,'location'=>$c['location'],'location_arg'=>$c['location_arg']);
        }
        $this->cards->createCards($create, 'card');
        // Now assign positions for hands
        foreach ($this->getPlayerIds() as $pid) {
            $handCards = $this->cards->getCardsInLocation(HC_LOC_HAND, $pid);
            $pos = 1;
            foreach ($handCards as $c) {
                HCRules::setCardPos($this->cards, $c['id'], $pos);
                $pos++;
            }
        }

        // First player
        $first = self::activeNextPlayer();
        self::setGameStateInitialValue(GV_ATTACKER, $first);
        self::setGameStateInitialValue(GV_DEFENDER, 0);
        self::setGameStateInitialValue(GV_PLAYED_CARD_ID, 0);
        self::setGameStateInitialValue(GV_DECLARED_TYPE, 0);
        self::setGameStateInitialValue(GV_TARGET_PLAYER, 0);
        self::setGameStateInitialValue(GV_TARGET_ZONE, 0);
        self::setGameStateInitialValue(GV_TARGET_SLOT, 0);
        self::setGameStateInitialValue(GV_SELECTED_HERD_CARD, 0);
        self::setGameStateInitialValue(GV_CHALLENGER_BITS, 0);
        self::setGameStateInitialValue(GV_FIRST_CHAL_NO, 0);
        self::setGameStateInitialValue(GV_INTERCEPT_ZONE, 0);
        self::setGameStateInitialValue(GV_INTERCEPT_CHAL_BITS, 0);
        self::setGameStateInitialValue(GV_FIRST_INTERCEPT_CHAL_NO, 0);
        self::setGameStateInitialValue(GV_TRUTH_PENALTY_NEXT_NO, 0);
        self::setGameStateInitialValue(GV_PHASE_MARKER, 0);

        self::setGameStateValue(GV_ATTACKER, $first);
        $this->gamestate->nextState(ST_PLAYER_DECLARE);
    }

    // Return game data to set up the UI
    function getAllDatas() {
        $result = array();
        $current_player_id = self::getCurrentPlayerId();
        $players = self::loadPlayersBasicInfos();
        $result['players'] = $players;

        // Hands - only current player sees their own
        $result['hand'] = array();
        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $current_player_id);
        // order by location_arg
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        $result['hand'] = $hand;

        // Add public hand counts so the client can preview the correct number of facedown cards per opponent
        $result['herds'] = array(); $result['herd_up']=array(); $result['discards']=array(); $result['hand_counts']=array();
        foreach ($players as $pid=>$p) {
            $result['herds'][$pid] = array_values($this->cards->getCardsInLocation(HC_LOC_HERD, $pid));
            $result['herd_up'][$pid] = array_values($this->cards->getCardsInLocation(HC_LOC_HERD_UP, $pid));
            $result['discards'][$pid] = array_values($this->cards->getCardsInLocation(HC_LOC_DISCARD, $pid));
            $result['hand_counts'][$pid] = count($this->cards->getCardsInLocation(HC_LOC_HAND, $pid));
        }

        // Played card and declaration context (partial)
        $result['ctx'] = array(
            'attacker' => self::getGameStateValue(GV_ATTACKER),
            'declaredType' => self::getGameStateValue(GV_DECLARED_TYPE),
            'targetPlayer' => self::getGameStateValue(GV_TARGET_PLAYER),
            'targetZone' => self::getGameStateValue(GV_TARGET_ZONE),
            'targetSlot' => self::getGameStateValue(GV_TARGET_SLOT),
            'selectedHerdCard' => self::getGameStateValue(GV_SELECTED_HERD_CARD),
        );

        return $result;
    }

    function getGameProgression() {
        // Rough %: proportion of cards played into herds vs total possible
        $players = self::loadPlayersBasicInfos();
        $total = 0; $played = 0;
        foreach ($players as $pid=>$p) {
            $total += 7;
            $played += count($this->cards->getCardsInLocation(HC_LOC_HERD, $pid));
            $played += count($this->cards->getCardsInLocation(HC_LOC_HERD_UP, $pid));
            $played += count($this->cards->getCardsInLocation(HC_LOC_DISCARD, $pid));
        }
        if ($total == 0) return 0;
        return min(100, intval($played*100/$total));
    }

    ////////////// Player actions //////////////

    function actDeclarePlay($card_id, $declared_type, $target_player_id=0) {
        self::checkAction('actDeclarePlay');
        $player_id = self::getActivePlayerId();
        $card_id = intval($card_id);
        $declared_type = intval($declared_type);
        $target_player_id = intval($target_player_id);

        $card = $this->cards->getCard($card_id);
        if ($card['location'] != HC_LOC_HAND || intval($card['location_arg']) != $player_id) {
            throw new BgaUserException(self::_("You must select a card from your hand."));
        }
        $tgtZone = HCRules::getTargetZoneForDeclared($declared_type);

        // Move the card to played immediately
        $this->cards->moveCard($card_id, HC_LOC_PLAYED, $player_id);

        self::setGameStateValue(GV_ATTACKER, $player_id);
        self::setGameStateValue(GV_PLAYED_CARD_ID, $card_id);
        self::setGameStateValue(GV_DECLARED_TYPE, $declared_type);
        self::setGameStateValue(GV_TARGET_ZONE, $tgtZone);
        self::setGameStateValue(GV_TARGET_SLOT, 0);
        self::setGameStateValue(GV_SELECTED_HERD_CARD, 0);
        self::setGameStateValue(GV_CHALLENGER_BITS, 0);
        self::setGameStateValue(GV_FIRST_CHAL_NO, 0);

        // Branching logic: decide whether to go straight to challenge or select target first
        if ($tgtZone == HC_TGT_NONE) {
            // Non-targeting cards (Kitten, Show Cat, Catnip) - go straight to challenge window
            self::setGameStateValue(GV_TARGET_PLAYER, 0);
            self::notifyAllPlayers('declarePlay', clienttranslate('${player_name} plays a card face-down and declares ${decl}'), array(
                'player_id' => $player_id,
                'player_name' => $this->getPlayerNameById($player_id),
                'decl' => HCRules::declaredToText($declared_type),
                'declared_type' => $declared_type
            ));
            $this->gamestate->nextState('declared');  // Go to challengeWindow state
        } else {
            // Targeting cards (Alley Cat, Animal Control) - go to target selection first
            self::setGameStateValue(GV_TARGET_PLAYER, 0);  // Will be set in selectTarget state
            self::notifyAllPlayers('declarePlay', clienttranslate('${player_name} plays a card face-down and declares ${decl} (target to be chosen)'), array(
                'player_id' => $player_id,
                'player_name' => $this->getPlayerNameById($player_id),
                'decl' => HCRules::declaredToText($declared_type),
                'declared_type' => $declared_type
            ));
            $this->gamestate->nextState('declaredTarget');  // Go to selectTarget state
        }
    }

    function argPlayerDeclare() {
        $pid = self::getActivePlayerId();
        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $pid);
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        return array( 'hand'=>$hand );
    }

    function argChallengeWindow() {
        return array(
            'attacker'=> self::getGameStateValue(GV_ATTACKER),
            'declared'=> self::getGameStateValue(GV_DECLARED_TYPE),
            'targetPlayer'=> self::getGameStateValue(GV_TARGET_PLAYER),
            'targetZone'=> self::getGameStateValue(GV_TARGET_ZONE),
        );
    }

    function actChallenge() {
        self::checkAction('actChallenge');
        $pid = self::getCurrentPlayerId();
        if ($pid == self::getGameStateValue(GV_ATTACKER)) throw new BgaUserException(self::_("Attacker cannot challenge."));
        $bits = self::getGameStateValue(GV_CHALLENGER_BITS);
        $no = intval(self::getUniqueValueFromDB("SELECT player_no FROM player WHERE player_id=$pid"));
        if (($bits & (1 << ($no-1))) == 0) {
            $bits |= (1 << ($no-1));
            self::setGameStateValue(GV_CHALLENGER_BITS, $bits);
            if (self::getGameStateValue(GV_FIRST_CHAL_NO) == 0) self::setGameStateValue(GV_FIRST_CHAL_NO, $no);
            self::notifyAllPlayers('challengeMade', clienttranslate('${player_name} challenges!'), array(
                'player_id'=>$pid, 'player_name'=>$this->getPlayerNameById($pid)
            ));
        }
        $this->gamestate->setPlayerNonMultiactive($pid, 'resolve');
        $this->checkAllChallengeResponses();
    }

    function actPassChallenge() {
        self::checkAction('actPassChallenge');
        $pid = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'resolve');
        $this->checkAllChallengeResponses();
    }

    protected function checkAllChallengeResponses() {
        if (!$this->gamestate->isMultiActivePlayerActive()) {
            $this->gamestate->nextState('resolve');
        }
    }

    function stResolveChallenge() {
        $attacker = self::getGameStateValue(GV_ATTACKER);
        $card_id = self::getGameStateValue(GV_PLAYED_CARD_ID);
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $bits = self::getGameStateValue(GV_CHALLENGER_BITS);

        if ($bits == 0) {
            // No challenge
            if (self::getGameStateValue(GV_TARGET_ZONE) == HC_TGT_NONE) {
                $this->gamestate->nextState('truthNoPenalty');
            } else {
                $this->gamestate->nextState('noChallenge');
            }
            return;
        }

        $card = $this->cards->getCard($card_id);
        $printed = intval($card['type']);
        $truth = ($printed == $decl);

        if (!$truth) {
            // Bluff caught
            // Reveal to all
            self::notifyAllPlayers('revealPlayed', clienttranslate('Bluff! The played card was ${printed}'), array(
                'player_id'=>$attacker,
                'printed'=>$this->typeToText($printed),
                'printed_type'=>$printed,
            ));
            // Discard played card
            $this->cards->moveCard($card_id, HC_LOC_DISCARD, $attacker);
            // First challenger selects blind penalty from attacker hand
            $firstNo = self::getGameStateValue(GV_FIRST_CHAL_NO);
            $firstChallenger = intval(self::getUniqueValueFromDB("SELECT player_id FROM player WHERE player_no=$firstNo"));
            self::setGameStateValue(GV_DEFENDER, 0); // not used here
            self::setGameStateValue(GV_TRUTH_PENALTY_NEXT_NO, 0);
            // Set active to first challenger
            $this->gamestate->changeActivePlayer($firstChallenger);
            $this->gamestate->nextState('bluffPenalty');
        } else {
            // Truthful: attacker picks blind discards from each challenger in turn (active player is attacker)
            self::notifyAllPlayers('truthful', clienttranslate('Truthful play stands'), array());
            $this->gamestate->changeActivePlayer($attacker);
            // choose next challenger (lowest player_no present in bits)
            $nextNo = $this->firstNoFromBits($bits);
            self::setGameStateValue(GV_TRUTH_PENALTY_NEXT_NO, $nextNo);
            $this->gamestate->nextState('truthPenalty');
        }
    }

    function argBluffPenaltyPick() {
        // If this was a failed intercept, the target is the defender; otherwise the attacker.
        $phase = self::getGameStateValue(GV_PHASE_MARKER);
        if ($phase == 2) {
            $targetPid = self::getGameStateValue(GV_TARGET_PLAYER); // defender
        } else {
            $targetPid = self::getGameStateValue(GV_ATTACKER);
        }
        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $targetPid);
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        return array('targetPlayer'=>$targetPid, 'handSize'=>count($hand));
    }

    function argTruthPenaltyPick() {
        $bits = self::getGameStateValue(GV_CHALLENGER_BITS);
        $nextNo = self::getGameStateValue(GV_TRUTH_PENALTY_NEXT_NO);
        $challenger = intval(self::getUniqueValueFromDB("SELECT player_id FROM player WHERE player_no=$nextNo"));
        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $challenger);
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        return array('targetPlayer'=>$challenger, 'handSize'=>count($hand));
    }

    function actPickBlindFromHand($target_player_id, $slot_index) {
        self::checkAction('actPickBlindFromHand');
        $picker = self::getActivePlayerId();
        $target_player_id = intval($target_player_id);
        $slot_index = intval($slot_index);

        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target_player_id);
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        $n = count($hand);
        if ($n == 0) throw new BgaUserException(self::_("No cards to pick."));
        if ($slot_index < 1 || $slot_index > $n) throw new BgaUserException(self::_("Invalid slot index."));
        $card = $hand[$slot_index-1];
        // Reveal then discard
        self::notifyAllPlayers('revealHandCard', clienttranslate('${player_name} reveals ${card} from ${target}'s hand'), array(
            'player_id'=>$picker,
            'player_name'=>self::getActivePlayerName(),
            'target'=>$this->getPlayerNameById($target_player_id),
            'card'=>$this->typeToText($card['type']),
            'card_type'=>$card['type'],
            'card_id'=>$card['id'],
            'target_player_id'=>$target_player_id,
            'slot'=>$slot_index,
        ));
        $this->cards->moveCard($card['id'], HC_LOC_DISCARD, $target_player_id);

        // Re-pack positions
        $newhand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target_player_id);
        usort($newhand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        $pos=1; foreach($newhand as $c){ HCRules::setCardPos($this->cards, $c['id'], $pos); $pos++; }

        $stId = $this->gamestate->state_id();
        if ($stId == ST_BLUFF_PENALTY_PICK) {
            // If this was a failed intercept penalty, continue to resolve effect
            if (self::getGameStateValue(GV_PHASE_MARKER) == 2) {
                self::setGameStateValue(GV_PHASE_MARKER, 0);
                $this->gamestate->nextState('toEffect');
            } else {
                $this->gamestate->nextState('done');
            }
        } else if ($stId == ST_TRUTH_PENALTY_PICK) {
            $bits = self::getGameStateValue(GV_CHALLENGER_BITS);
            $nextNo = self::getGameStateValue(GV_TRUTH_PENALTY_NEXT_NO);
            $bits &= ~(1 << ($nextNo-1));
            self::setGameStateValue(GV_CHALLENGER_BITS, $bits);
            if ($bits == 0) {
                // If we came from a truthful intercept, go back to resolve it now
                if (self::getGameStateValue(GV_PHASE_MARKER) == 1) {
                    self::setGameStateValue(GV_PHASE_MARKER, 0);
                    $this->gamestate->nextState('doneIntercept');
                } else {
                    $this->gamestate->nextState('done');
                }
            } else {
                $nextNo = $this->firstNoFromBits($bits);
                self::setGameStateValue(GV_TRUTH_PENALTY_NEXT_NO, $nextNo);
                $this->gamestate->nextState('next');
            }
        } else {
            $this->gamestate->nextState('done');
        }
    }

    function argSelectTarget() {
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $zone = HCRules::getTargetZoneForDeclared($decl);
        $tpid = self::getGameStateValue(GV_TARGET_PLAYER);
        $res = array('zone'=>$zone, 'targetPlayer'=>$tpid);

        if ($tpid == 0) {
            // Provide opponents for the picker
            $players = self::loadPlayersBasicInfos();
            $pid = self::getActivePlayerId();
            $others = array();
            foreach ($players as $opid=>$p) {
                if (intval($opid) == intval($pid)) continue;
                $others[$opid] = array(
                    'name' => $p['player_name'],
                    'handSize' => count($this->cards->getCardsInLocation(HC_LOC_HAND, $opid)),
                    'herdCount' => count($this->cards->getCardsInLocation(HC_LOC_HERD, $opid)),
                );
            }
            $res['opponents'] = $others;
            return $res;
        }

        if ($zone == HC_TGT_HAND) {
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $tpid);
            usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
            $res['handSize'] = count($hand);
        } else if ($zone == HC_TGT_HERD) {
            $herd = $this->cards->getCardsInLocation(HC_LOC_HERD, $tpid);
            $res['herdCards'] = array_values(array_map(function($c){ return $c['id']; }, $herd));
        }
        return $res;
    }

    function actSelectTargetPlayer($target_player_id) {
        self::checkAction('actSelectTargetPlayer');
        $attacker = self::getActivePlayerId();
        $target_player_id = intval($target_player_id);
        if ($target_player_id == 0 || $target_player_id == $attacker) throw new BgaUserException(self::_("Select an opponent."));
        if (self::getGameStateValue(GV_DECLARED_TYPE) == 0) throw new BgaUserException(self::_("No declaration in progress."));

        self::setGameStateValue(GV_TARGET_PLAYER, $target_player_id);
        self::notifyAllPlayers('targetChosen', clienttranslate('${player_name} targets ${target}'), array(
            'player_id'=>$attacker,
            'player_name'=>$this->getPlayerNameById($attacker),
            'target'=>$this->getPlayerNameById($target_player_id),
            'target_player_id'=>$target_player_id
        ));

        // Open the challenge window
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->setPlayerNonMultiactive($attacker, 'resolve');
        $this->gamestate->nextState('goChallenge');
    }

    function actSelectHandSlot($target_player_id, $slot_index) {
        self::checkAction('actSelectHandSlot');
        $slot_index = intval($slot_index);
        $target_player_id = intval($target_player_id);
        if ($target_player_id != self::getGameStateValue(GV_TARGET_PLAYER)) throw new BgaUserException(self::_("Wrong target."));
        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target_player_id);
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        if ($slot_index < 1 || $slot_index > count($hand)) throw new BgaUserException(self::_("Invalid slot."));
        self::setGameStateValue(GV_TARGET_SLOT, $slot_index);
        $this->gamestate->changeActivePlayer($target_player_id);
        $this->gamestate->nextState('toIntercept');
    }

    function actSelectHerdCard($target_player_id, $card_id) {
        self::checkAction('actSelectHerdCard');
        $target_player_id = intval($target_player_id);
        $card_id = intval($card_id);
        if ($target_player_id != self::getGameStateValue(GV_TARGET_PLAYER)) throw new BgaUserException(self::_("Wrong target."));
        $card = $this->cards->getCard($card_id);
        if ($card['location'] != HC_LOC_HERD || intval($card['location_arg']) != $target_player_id) throw new BgaUserException(self::_("Select a face-down herd card."));
        self::setGameStateValue(GV_SELECTED_HERD_CARD, $card_id);
        $this->gamestate->changeActivePlayer($target_player_id);
        $this->gamestate->nextState('toIntercept');
    }

    function actSkipTarget() {
        self::checkAction('actSkipTarget');
        $this->gamestate->nextState('toResolve');
    }

    function argInterceptDecision() {
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $zone = self::getGameStateValue(GV_TARGET_ZONE);
        return array('allowedZone'=>$zone, 'declared'=>$decl);
    }

    function actDeclineIntercept() {
        self::checkAction('actDeclineIntercept');
        $this->gamestate->nextState('toResolve');
    }

    function actDeclareIntercept($zone) {
        self::checkAction('actDeclareIntercept');
        $def = self::getActivePlayerId();
        $allowed = self::getGameStateValue(GV_TARGET_ZONE);
        if ($zone != $allowed) throw new BgaUserException(self::_("Intercept must come from the targeted zone."));
        self::setGameStateValue(GV_INTERCEPT_ZONE, $zone);
        self::setGameStateValue(GV_INTERCEPT_CHAL_BITS, 0);
        self::setGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO, 0);
        // Notify declaration (no reveal)
        self::notifyAllPlayers('interceptDeclared', clienttranslate('${player_name} declares a Laser Pointer intercept'), array(
            'player_id'=>$def, 'player_name'=>self::getActivePlayerName(), 'zone'=>$zone
        ));
        // Start multi-active challenge window for all except defender
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->setPlayerNonMultiactive($def, 'resolve');
        $this->gamestate->nextState('toInterceptChallenge');
    }

    function argInterceptChallengeWindow() {
        return array('defender'=> self::getGameStateValue(GV_TARGET_PLAYER));
    }

    function actChallengeIntercept() {
        self::checkAction('actChallengeIntercept');
        $pid = self::getCurrentPlayerId();
        if ($pid == self::getGameStateValue(GV_TARGET_PLAYER)) throw new BgaUserException(self::_("Defender cannot challenge their own intercept."));
        $bits = self::getGameStateValue(GV_INTERCEPT_CHAL_BITS);
        $no = intval(self::getUniqueValueFromDB("SELECT player_no FROM player WHERE player_id=$pid"));
        if (($bits & (1 << ($no-1))) == 0) {
            $bits |= (1 << ($no-1));
            self::setGameStateValue(GV_INTERCEPT_CHAL_BITS, $bits);
            if (self::getGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO) == 0) self::setGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO, $no);
            self::notifyAllPlayers('challengeMade', clienttranslate('${player_name} challenges the intercept!'), array(
                'player_id'=>$pid, 'player_name'=>$this->getPlayerNameById($pid)
            ));
        }
        $this->gamestate->setPlayerNonMultiactive($pid, 'resolve');
        $this->checkAllInterceptChallengeResponses();
    }

    function actPassIntercept() {
        self::checkAction('actPassIntercept');
        $pid = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'resolve');
        $this->checkAllInterceptChallengeResponses();
    }

    protected function checkAllInterceptChallengeResponses() {
        if (!$this->gamestate->isMultiActivePlayerActive()) {
            $this->gamestate->nextState('resolve');
        }
    }

    function stResolveIntercept() {
        // Continuation hook after truthful intercept penalties
        if (self::getGameStateValue(GV_PHASE_MARKER) == 1) {
            self::setGameStateValue(GV_PHASE_MARKER, 0);
            $this->applyInterceptSuccess(true);
            $this->gamestate->nextState('success');
            return;
        }

        $def = self::getGameStateValue(GV_TARGET_PLAYER);
        $zone = self::getGameStateValue(GV_INTERCEPT_ZONE);
        $bits = self::getGameStateValue(GV_INTERCEPT_CHAL_BITS);

        if ($bits == 0) {
            $this->applyInterceptSuccess(false);
            $this->gamestate->nextState('success');
            return;
        }

        $has = false;
        if ($zone == HC_TGT_HAND) {
            foreach ($this->cards->getCardsInLocation(HC_LOC_HAND, $def) as $c)
                if (intval($c['type']) == HC_TYPE_LASER) { $has = true; break; }
        } else {
            foreach ($this->cards->getCardsInLocation(HC_LOC_HERD, $def) as $c)
                if (intval($c['type']) == HC_TYPE_LASER) { $has = true; break; }
        }

        if ($has) {
            self::notifyAllPlayers('truthful', clienttranslate('Intercept is truthful'), array());
            $this->gamestate->changeActivePlayer($def);
            $nextNo = $this->firstNoFromBits($bits);
            self::setGameStateValue(GV_CHALLENGER_BITS, $bits);
            self::setGameStateValue(GV_TRUTH_PENALTY_NEXT_NO, $nextNo);
            self::setGameStateValue(GV_PHASE_MARKER, 1); // continue to intercept success after penalties
            $this->gamestate->nextState('truthPenalty');
        } else {
            $firstNo = self::getGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO);
            $firstCh = intval(self::getUniqueValueFromDB("SELECT player_id FROM player WHERE player_no=$firstNo"));
            self::notifyAllPlayers('bluff', clienttranslate('Intercept was a lie'), array());
            $this->gamestate->changeActivePlayer($firstCh);
            self::setGameStateValue(GV_PHASE_MARKER, 2); // failed intercept penalty, then resume effect
            $this->gamestate->nextState('liePenalty');
        }
    }

    // After truth penalty picks, if phase marker == 1 we continue to intercept success, else normal continue
    function stResolveChallenge_afterTruthPenaltyHook() {
        // Not a real BGA hook, we inline this behaviour in transition from ST_TRUTH_PENALTY_PICK
    }

    function stResolveEffect() {
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $attacker = self::getGameStateValue(GV_ATTACKER);
        $tgtZone = self::getGameStateValue(GV_TARGET_ZONE);
        $target = self::getGameStateValue(GV_TARGET_PLAYER);

        if ($tgtZone == HC_TGT_NONE) {
            // Non-targeting - just place the played card to herd as declared identity
            $this->placePlayedToHerdAsDeclared($attacker, $decl);
            $this->gamestate->nextState('endTurn');
            return;
        }

        if ($tgtZone == HC_TGT_HAND) {
            $slot = self::getGameStateValue(GV_TARGET_SLOT);
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target);
            usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
            if ($slot < 1 || $slot > count($hand)) {
                // Edge case: target changed size due to penalties - clamp
                $slot = min(max(1,$slot), max(1,count($hand)));
            }
            $card = $hand[$slot-1];
            // Reveal
            self::notifyAllPlayers('revealHandCard', clienttranslate('Revealed from ${player}: ${card}'), array(
                'player_id'=>$target,
                'player_name'=>$this->getPlayerNameById($target),
                'target'=>$this->getPlayerNameById($target),
                'card'=>$this->typeToText($card['type']),
                'card_type'=>$card['type'],
                'card_id'=>$card['id'],
                'target_player_id'=>$target,
                'slot'=>$slot,
            ));
            // Ineffective vs itself?
            if ( ($decl == HC_TYPE_ALLEY && intval($card['type']) == HC_TYPE_ALLEY)
              || ($decl == HC_TYPE_CATNIP && intval($card['type']) == HC_TYPE_CATNIP) ) {
                // Return to hand unchanged
                self::notifyAllPlayers('ineffective', clienttranslate('Ineffective - card returns to hand'), array());
                // leave as is
            } else {
                if ($decl == HC_TYPE_ALLEY) {
                    // Discard revealed card
                    $this->cards->moveCard($card['id'], HC_LOC_DISCARD, $target);
                } else if ($decl == HC_TYPE_CATNIP) {
                    // Move revealed card into attacker herd face-down, identity preserved
                    $this->cards->moveCard($card['id'], HC_LOC_HERD, $attacker);
                    // type_arg = declared type for herd identity; for stolen card we set it equal to its own printed type
                    $this->setDeclaredType($card['id'], intval($card['type']));
                }
            }
            // Normalise target hand order
            $newhand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target);
            usort($newhand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
            $pos=1; foreach($newhand as $c){ HCRules::setCardPos($this->cards, $c['id'], $pos); $pos++; }

            // Attacker's played card goes to herd as declared
            $this->placePlayedToHerdAsDeclared($attacker, $decl);
            $this->gamestate->nextState('endTurn');
            return;
        }

        if ($tgtZone == HC_TGT_HERD) {
            $card_id = self::getGameStateValue(GV_SELECTED_HERD_CARD);
            $card = $this->cards->getCard($card_id);
            // Reveal
            self::notifyAllPlayers('revealHerdCard', clienttranslate('Revealed from ${player} herd: ${card}'), array(
                'player_id'=>$target,
                'player_name'=>$this->getPlayerNameById($target),
                'card'=>$this->typeToText($card['type']),
                'card_type'=>$card['type'],
                'card_id'=>$card['id'],
                'target_player_id'=>$target,
            ));
            if ($decl == HC_TYPE_ANIMAL && intval($card['type']) == HC_TYPE_ANIMAL) {
                // Ineffective - flip to face-up protected
                $this->cards->moveCard($card_id, HC_LOC_HERD_UP, $target);
            } else {
                // Discard target herd card
                $this->cards->moveCard($card_id, HC_LOC_DISCARD, $target);
            }
            // Attacker's played card goes to herd as declared
            $this->placePlayedToHerdAsDeclared($attacker, $decl);
            $this->gamestate->nextState('endTurn');
            return;
        }
    }

    protected function applyInterceptSuccess($afterTruthPenalties) {
        $def = self::getGameStateValue(GV_TARGET_PLAYER);
        $zone = self::getGameStateValue(GV_INTERCEPT_ZONE);
        // Discard (or convert) one Laser from the zone
        $laserCardId = 0;
        if ($zone == HC_TGT_HAND) {
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $def);
            foreach ($hand as $c) if (intval($c['type']) == HC_TYPE_LASER) { $laserCardId = $c['id']; break; }
            if ($laserCardId == 0) return; // should not happen after truth check
            if (HCRules::$BUFF_LASER_TO_HERD) {
                $this->cards->moveCard($laserCardId, HC_LOC_HERD, $def);
                $this->setDeclaredType($laserCardId, HC_TYPE_LASER);
            } else {
                $this->cards->moveCard($laserCardId, HC_LOC_DISCARD, $def);
            }
        } else {
            $herd = $this->cards->getCardsInLocation(HC_LOC_HERD, $def);
            foreach ($herd as $c) if (intval($c['type']) == HC_TYPE_LASER) { $laserCardId = $c['id']; break; }
            if ($laserCardId == 0) return;
            if (HCRules::$BUFF_LASER_TO_HERD) {
                // Already in herd - leave it (intercept consumes it but stays as herd card)
                // No move required; consider it "consumed" without change.
            } else {
                $this->cards->moveCard($laserCardId, HC_LOC_DISCARD, $def);
            }
        }
        // Cancel original attack effect; the attacker's card is still played to herd
        $attacker = self::getGameStateValue(GV_ATTACKER);
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $this->placePlayedToHerdAsDeclared($attacker, $decl);
    }

    function stEndTurn() {
        // Clear transient values
        self::setGameStateValue(GV_DEFENDER, 0);
        self::setGameStateValue(GV_PLAYED_CARD_ID, 0);
        self::setGameStateValue(GV_DECLARED_TYPE, 0);
        self::setGameStateValue(GV_TARGET_PLAYER, 0);
        self::setGameStateValue(GV_TARGET_ZONE, 0);
        self::setGameStateValue(GV_TARGET_SLOT, 0);
        self::setGameStateValue(GV_SELECTED_HERD_CARD, 0);
        self::setGameStateValue(GV_CHALLENGER_BITS, 0);
        self::setGameStateValue(GV_FIRST_CHAL_NO, 0);
        self::setGameStateValue(GV_INTERCEPT_ZONE, 0);
        self::setGameStateValue(GV_INTERCEPT_CHAL_BITS, 0);
        self::setGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO, 0);
        self::setGameStateValue(GV_TRUTH_PENALTY_NEXT_NO, 0);
        self::setGameStateValue(GV_PHASE_MARKER, 0);

        // End trigger: if any player has 0 cards in hand, compute scores
        foreach ($this->getPlayerIds() as $pid) {
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $pid);
            if (count($hand) == 0) {
                $this->gamestate->nextState('scoring');
                return;
            }
        }

        // Next player
        $next = self::activeNextPlayer();
        self::setGameStateValue(GV_ATTACKER, $next);
        $this->gamestate->nextState('next');
    }

    function stComputeScores() {
        $players = self::loadPlayersBasicInfos();
        foreach ($players as $pid=>$p) {
            $herdDown = $this->cards->getCardsInLocation(HC_LOC_HERD, $pid);
            $herdUp = $this->cards->getCardsInLocation(HC_LOC_HERD_UP, $pid);
            $all = array_merge($herdDown, $herdUp);
            $hasKitten = false;
            $base = 0;
            foreach ($all as $c) {
                $declared = intval($c['type_arg']) > 0 ? intval($c['type_arg']) : intval($c['type']); // default to printed
                if ($declared == HC_TYPE_KITTEN) $hasKitten = true;
            }
            foreach ($all as $c) {
                $declared = intval($c['type_arg']) > 0 ? intval($c['type_arg']) : intval($c['type']);
                if ($declared == HC_TYPE_SHOWCAT) {
                    $base += HCRules::faceValueForShowCat($hasKitten);
                } else {
                    $base += HCRules::$CARD_VALUES[$declared];
                }
            }
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $pid);
            $bonus = intdiv(count($hand)+1, 2); // ceil(n/2)
            $score = $base + $bonus;
            self::DbQuery("UPDATE player SET player_score=$score WHERE player_id=$pid");
            self::notifyAllPlayers('scorePlayer', clienttranslate('${player_name} scores ${score} (herd ${base} + hand bonus ${bonus})'), array(
                'player_id'=>$pid,
                'player_name'=>$this->getPlayerNameById($pid),
                'score'=>$score, 'base'=>$base, 'bonus'=>$bonus
            ));
        }
        $this->gamestate->nextState('endGame');
    }

    ////////////// Helpers //////////////

    protected function setDeclaredType($card_id, $declared) {
        self::DbQuery("UPDATE card SET card_type_arg=$declared WHERE card_id=$card_id");
    }

    protected function placePlayedToHerdAsDeclared($pid, $declared) {
        $card_id = self::getGameStateValue(GV_PLAYED_CARD_ID);
        if ($card_id == 0) return;
        $this->cards->moveCard($card_id, HC_LOC_HERD, $pid);
        $this->setDeclaredType($card_id, $declared);
        self::notifyAllPlayers('addToHerd', clienttranslate('${player_name} adds a card to herd as ${decl}'), array(
            'player_id'=>$pid, 'player_name'=>$this->getPlayerNameById($pid),
            'decl'=>$this->typeToText($declared),
            'declared_type'=>$declared,
            'card_id'=>$card_id
        ));
    }

    protected function firstNoFromBits($bits) {
        for ($i=1;$i<=6;$i++) { if ($bits & (1<<($i-1))) return $i; }
        return 0;
    }

    protected function typeToText($t) {
        return HCRules::getCardName($t);
    }

    protected function getPlayerNameById($pid) {
        return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id=$pid");
    }

    // Zombie behaviour
    function zombieTurn($state, $active_player) {
        $statename = $state['name'];
        if ($state['type'] == 'activeplayer') {
            $this->gamestate->nextState('zombiePass');
            return;
        }
        if ($state['type'] == 'multipleactiveplayer') {
            $this->gamestate->setPlayerNonMultiactive($active_player, 'resolve');
            return;
        }
    }

    function actDecline() { /* noop */ }
}