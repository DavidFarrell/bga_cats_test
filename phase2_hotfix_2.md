<?xml version="1.0" ?>
<codebase>
<file path="src/herdingcats.game.php"><?php
/**
 * Herding Cats - main game class (hotfix + target UI support)
 */
require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');

class herdingcats extends Table
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
        require_once('modules/HerdingCatsRules.php');
    }

    protected function getGameName() { return 'herdingcats'; }

    protected function getPlayerIds() { return array_keys(self::loadPlayersBasicInfos()); }

    protected function getOrderedPlayerIds($startPlayerId=null) {
        $players = self::getCollectionFromDb("SELECT player_id id, player_no no FROM player ORDER BY player_no ASC");
        $ids = array(); foreach ($players as $p) $ids[] = intval($p['id']);
        if ($startPlayerId === null) return $ids;
        while ($ids[0] != $startPlayerId) { $x = array_shift($ids); $ids[] = $x; }
        return $ids;
    }

    protected function nextPlayerId($pid) {
        $ids = $this->getOrderedPlayerIds($pid); array_shift($ids); return $ids[0];
    }

    // ---------- Setup ----------
    function stGameSetup() {
        $players = self::loadPlayersBasicInfos();
        self::DbQuery("UPDATE player SET player_score=0");
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
            foreach ($hand as $t) $cards_to_create[] = array('type'=>$t,'type_arg'=>0,'nbr'=>1,'location'=>HC_LOC_HAND,'location_arg'=>$pid);
            foreach ($removed as $t) $cards_to_create[] = array('type'=>$t,'type_arg'=>0,'nbr'=>1,'location'=>HC_LOC_REMOVED,'location_arg'=>$pid);
        }
        $this->cards->createCards($cards_to_create, 'card');
        foreach ($this->getPlayerIds() as $pid) {
            $handCards = $this->cards->getCardsInLocation(HC_LOC_HAND, $pid);
            usort($handCards, function($a,$b){ return $a['id'] <=> $b['id']; });
            $pos=1; foreach ($handCards as $c){ self::DbQuery("UPDATE card SET card_location_arg=$pos WHERE card_id=".$c['id']); $pos++; }
        }

        $first = self::activeNextPlayer();
        foreach ([GV_DEFENDER,GV_PLAYED_CARD_ID,GV_DECLARED_TYPE,GV_TARGET_PLAYER,GV_TARGET_ZONE,GV_TARGET_SLOT,
                  GV_SELECTED_HERD_CARD,GV_CHALLENGER_BITS,GV_FIRST_CHAL_NO,GV_INTERCEPT_ZONE,GV_INTERCEPT_CHAL_BITS,
                  GV_FIRST_INTERCEPT_CHAL_NO,GV_TRUTH_PENALTY_NEXT_NO,GV_PHASE_MARKER] as $k) self::setGameStateInitialValue($k,0);
        self::setGameStateValue(GV_ATTACKER, $first);
        $this->gamestate->nextState(ST_PLAYER_DECLARE);
    }

    // ---------- UI data ----------
    function getAllDatas() {
        $result = array();
        $current_player_id = self::getCurrentPlayerId();
        $players = self::loadPlayersBasicInfos();
        $result['players'] = $players;

        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $current_player_id);
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
        $players = self::loadPlayersBasicInfos();
        $total=0; $played=0;
        foreach ($players as $pid=>$p) {
            $total += 7;
            $played += count($this->cards->getCardsInLocation(HC_LOC_HERD,$pid));
            $played += count($this->cards->getCardsInLocation(HC_LOC_HERD_UP,$pid));
            $played += count($this->cards->getCardsInLocation(HC_LOC_DISCARD,$pid));
        }
        return $total? min(100, intval($played*100/$total)) : 0;
    }

    // ---------- Actions ----------
    function argPlayerDeclare() {
        $pid = self::getActivePlayerId();
        $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $pid);
        usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        return array( 'hand'=>$hand );
    }

    function actDeclarePlay($card_id, $declared_type, $target_player_id=0) {
        self::checkAction('actDeclarePlay');
        $player_id = self::getActivePlayerId();
        $card_id = intval($card_id); $declared_type = intval($declared_type); $target_player_id = intval($target_player_id);

        $card = $this->cards->getCard($card_id);
        if ($card['location'] != HC_LOC_HAND || intval($card['location_arg']) != $player_id) {
            throw new BgaUserException(self::_("You must select a card from your hand."));
        }
        $tgtZone = HCRules::getTargetZoneForDeclared($declared_type);
        if ($tgtZone == HC_TGT_NONE) {
            $target_player_id = 0;
        } else {
            if ($target_player_id == 0 || $target_player_id == $player_id) {
                throw new BgaUserException(self::_("Choose an opponent to target before declaring."));
            }
        }

        $this->cards->moveCard($card_id, HC_LOC_PLAYED, $player_id);

        self::setGameStateValue(GV_ATTACKER, $player_id);
        self::setGameStateValue(GV_PLAYED_CARD_ID, $card_id);
        self::setGameStateValue(GV_DECLARED_TYPE, $declared_type);
        self::setGameStateValue(GV_TARGET_PLAYER, $target_player_id);
        self::setGameStateValue(GV_TARGET_ZONE, $tgtZone);
        self::setGameStateValue(GV_TARGET_SLOT, 0);
        self::setGameStateValue(GV_SELECTED_HERD_CARD, 0);
        self::setGameStateValue(GV_CHALLENGER_BITS, 0);
        self::setGameStateValue(GV_FIRST_CHAL_NO, 0);

        self::notifyAllPlayers('declarePlay', clienttranslate('${player_name} plays a card face-down and declares ${decl}${target_suffix}'), array(
            'player_id' => $player_id,
            'player_name' => $this->getPlayerNameById($player_id),
            'decl' => HCRules::declaredToText($declared_type),
            'declared_type' => $declared_type,
            'target_player_id' => $target_player_id,
            'target_zone' => $tgtZone,
            'target_suffix' => $tgtZone==HC_TGT_NONE ? '' : ' (' . self::_('targeting ') . $this->getPlayerNameById($target_player_id) . ')',
        ));

        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->setPlayerNonMultiactive($player_id, 'resolve');
        $this->gamestate->nextState('goChallenge');
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
        if (!$this->gamestate->isMultiActivePlayerActive()) $this->gamestate->nextState('resolve');
    }

    function actPassChallenge() {
        self::checkAction('actPassChallenge');
        $pid = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'resolve');
        if (!$this->gamestate->isMultiActivePlayerActive()) $this->gamestate->nextState('resolve');
    }

    function stResolveChallenge() {
        $attacker = self::getGameStateValue(GV_ATTACKER);
        $card_id = self::getGameStateValue(GV_PLAYED_CARD_ID);
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $bits = self::getGameStateValue(GV_CHALLENGER_BITS);

        if ($bits == 0) {
            if (self::getGameStateValue(GV_TARGET_ZONE) == HC_TGT_NONE) $this->gamestate->nextState('truthNoPenalty');
            else $this->gamestate->nextState('noChallenge');
            return;
        }

        $card = $this->cards->getCard($card_id);
        $printed = intval($card['type']);
        $truth = ($printed == $decl);

        if (!$truth) {
            self::notifyAllPlayers('revealPlayed', clienttranslate('Bluff! The played card was ${printed}'), array(
                'player_id'=>$attacker,
                'printed'=>$this->typeToText($printed),
                'printed_type'=>$printed,
            ));
            $this->cards->moveCard($card_id, HC_LOC_DISCARD, $attacker);
            $firstNo = self::getGameStateValue(GV_FIRST_CHAL_NO);
            $firstChallenger = intval(self::getUniqueValueFromDB("SELECT player_id FROM player WHERE player_no=$firstNo"));
            $this->gamestate->changeActivePlayer($firstChallenger);
            $this->gamestate->nextState('bluffPenalty');
        } else {
            self::notifyAllPlayers('truthful', clienttranslate('Truthful play stands'), array());
            $this->gamestate->changeActivePlayer($attacker);
            $nextNo = $this->firstNoFromBits($bits);
            self::setGameStateValue(GV_TRUTH_PENALTY_NEXT_NO, $nextNo);
            $this->gamestate->nextState('truthPenalty');
        }
    }

    function argBluffPenaltyPick() {
        $phase = self::getGameStateValue(GV_PHASE_MARKER);
        $targetPid = ($phase == 2) ? self::getGameStateValue(GV_TARGET_PLAYER) : self::getGameStateValue(GV_ATTACKER);
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
        self::notifyAllPlayers('revealHandCard', clienttranslate('${player_name} reveals ${card} from ${target}\'s hand'), array(
            'player_id'=>$picker,
            'player_name'=>$this->getPlayerNameById($picker),
            'target'=>$this->getPlayerNameById($target_player_id),
            'card'=>$this->typeToText($card['type']),
            'card_type'=>$card['type'],
            'card_id'=>$card['id'],
            'target_player_id'=>$target_player_id,
            'slot'=>$slot_index,
        ));
        $this->cards->moveCard($card['id'], HC_LOC_DISCARD, $target_player_id);

        $newhand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target_player_id);
        usort($newhand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
        $pos=1; foreach($newhand as $c){ self::DbQuery("UPDATE card SET card_location_arg=$pos WHERE card_id=".$c['id']); $pos++; }

        $stId = $this->gamestate->state_id();
        if ($stId == ST_BLUFF_PENALTY_PICK) {
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

    function actSelectHandSlot($target_player_id, $slot_index) {
        self::checkAction('actSelectHandSlot');
        $slot_index = intval($slot_index); $target_player_id = intval($target_player_id);
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
        $target_player_id = intval($target_player_id); $card_id = intval($card_id);
        if ($target_player_id != self::getGameStateValue(GV_TARGET_PLAYER)) throw new BgaUserException(self::_("Wrong target."));
        $card = $this->cards->getCard($card_id);
        if ($card['location'] != HC_LOC_HERD || intval($card['location_arg']) != $target_player_id) throw new BgaUserException(self::_("Select a face-down herd card."));
        self::setGameStateValue(GV_SELECTED_HERD_CARD, $card_id);
        $this->gamestate->changeActivePlayer($target_player_id);
        $this->gamestate->nextState('toIntercept');
    }

    function argInterceptDecision() {
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $zone = self::getGameStateValue(GV_TARGET_ZONE);
        return array('allowedZone'=>$zone, 'declared'=>$decl);
    }

    function actDeclineIntercept() { self::checkAction('actDeclineIntercept'); $this->gamestate->nextState('toResolve'); }

    function actDeclareIntercept($zone) {
        self::checkAction('actDeclareIntercept');
        $def = self::getActivePlayerId();
        $allowed = self::getGameStateValue(GV_TARGET_ZONE);
        if (intval($zone) != $allowed) throw new BgaUserException(self::_("Intercept must come from the targeted zone."));
        self::setGameStateValue(GV_INTERCEPT_ZONE, intval($zone));
        self::setGameStateValue(GV_INTERCEPT_CHAL_BITS, 0);
        self::setGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO, 0);
        self::notifyAllPlayers('interceptDeclared', clienttranslate('${player_name} declares a Laser Pointer intercept'), array(
            'player_id'=>$def, 'player_name'=>$this->getPlayerNameById($def), 'zone'=>intval($zone)
        ));
        $this->gamestate->setAllPlayersMultiactive();
        $this->gamestate->setPlayerNonMultiactive($def, 'resolve');
        $this->gamestate->nextState('toInterceptChallenge');
    }

    function argInterceptChallengeWindow() { return array('defender'=> self::getGameStateValue(GV_TARGET_PLAYER)); }

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
        if (!$this->gamestate->isMultiActivePlayerActive()) $this->gamestate->nextState('resolve');
    }

    function actPassIntercept() {
        self::checkAction('actPassIntercept');
        $pid = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive($pid, 'resolve');
        if (!$this->gamestate->isMultiActivePlayerActive()) $this->gamestate->nextState('resolve');
    }

    function stResolveIntercept() {
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
            self::setGameStateValue(GV_PHASE_MARKER, 1);
            $this->gamestate->nextState('truthPenalty');
        } else {
            $firstNo = self::getGameStateValue(GV_FIRST_INTERCEPT_CHAL_NO);
            $firstCh = intval(self::getUniqueValueFromDB("SELECT player_id FROM player WHERE player_no=$firstNo"));
            self::notifyAllPlayers('bluff', clienttranslate('Intercept was a lie'), array());
            $this->gamestate->changeActivePlayer($firstCh);
            self::setGameStateValue(GV_PHASE_MARKER, 2);
            $this->gamestate->nextState('liePenalty');
        }
    }

    function stResolveEffect() {
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $attacker = self::getGameStateValue(GV_ATTACKER);
        $tgtZone = self::getGameStateValue(GV_TARGET_ZONE);
        $target = self::getGameStateValue(GV_TARGET_PLAYER);

        if ($tgtZone == HC_TGT_NONE) {
            $this->placePlayedToHerdAsDeclared($attacker, $decl);
            $this->gamestate->nextState('endTurn');
            return;
        }

        if ($tgtZone == HC_TGT_HAND) {
            $slot = self::getGameStateValue(GV_TARGET_SLOT);
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target);
            usort($hand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
            if ($slot < 1 || $slot > count($hand)) throw new BgaUserException(self::_("Invalid slot."));
            $card = $hand[$slot-1];
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
            if ( ($decl == HC_TYPE_ALLEY && intval($card['type']) == HC_TYPE_ALLEY)
              || ($decl == HC_TYPE_CATNIP && intval($card['type']) == HC_TYPE_CATNIP) ) {
                self::notifyAllPlayers('ineffective', clienttranslate('Ineffective - card returns to hand'), array());
            } else {
                if ($decl == HC_TYPE_ALLEY) {
                    $this->cards->moveCard($card['id'], HC_LOC_DISCARD, $target);
                } else if ($decl == HC_TYPE_CATNIP) {
                    $this->cards->moveCard($card['id'], HC_LOC_HERD, $attacker);
                    $this->setDeclaredType($card['id'], intval($card['type']));
                }
            }
            $newhand = $this->cards->getCardsInLocation(HC_LOC_HAND, $target);
            usort($newhand, function($a,$b){ return $a['location_arg'] <=> $b['location_arg']; });
            $pos=1; foreach($newhand as $c){ self::DbQuery("UPDATE card SET card_location_arg=$pos WHERE card_id=".$c['id']); $pos++; }

            $this->placePlayedToHerdAsDeclared($attacker, $decl);
            $this->gamestate->nextState('endTurn');
            return;
        }

        if ($tgtZone == HC_TGT_HERD) {
            $card_id = self::getGameStateValue(GV_SELECTED_HERD_CARD);
            $card = $this->cards->getCard($card_id);
            self::notifyAllPlayers('revealHerdCard', clienttranslate('Revealed from ${player} herd: ${card}'), array(
                'player_id'=>$target,
                'player_name'=>$this->getPlayerNameById($target),
                'card'=>$this->typeToText($card['type']),
                'card_type'=>$card['type'],
                'card_id'=>$card['id'],
                'target_player_id'=>$target,
            ));
            if ($decl == HC_TYPE_ANIMAL && intval($card['type']) == HC_TYPE_ANIMAL) {
                $this->cards->moveCard($card_id, HC_LOC_HERD_UP, $target);
            } else {
                $this->cards->moveCard($card_id, HC_LOC_DISCARD, $target);
            }
            $this->placePlayedToHerdAsDeclared($attacker, $decl);
            $this->gamestate->nextState('endTurn');
            return;
        }
    }

    protected function applyInterceptSuccess($afterTruthPenalties) {
        $def = self::getGameStateValue(GV_TARGET_PLAYER);
        $zone = self::getGameStateValue(GV_INTERCEPT_ZONE);
        $laserCardId = 0;
        if ($zone == HC_TGT_HAND) {
            $hand = $this->cards->getCardsInLocation(HC_LOC_HAND, $def);
            foreach ($hand as $c) if (intval($c['type']) == HC_TYPE_LASER) { $laserCardId = $c['id']; break; }
            if ($laserCardId == 0) return;
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
            if (!HCRules::$BUFF_LASER_TO_HERD) {
                $this->cards->moveCard($laserCardId, HC_LOC_DISCARD, $def);
            }
        }
        $attacker = self::getGameStateValue(GV_ATTACKER);
        $decl = self::getGameStateValue(GV_DECLARED_TYPE);
        $this->placePlayedToHerdAsDeclared($attacker, $decl);
    }

    function stEndTurn() {
        foreach ([GV_DEFENDER,GV_PLAYED_CARD_ID,GV_DECLARED_TYPE,GV_TARGET_PLAYER,GV_TARGET_ZONE,GV_TARGET_SLOT,GV_SELECTED_HERD_CARD,
                  GV_CHALLENGER_BITS,GV_FIRST_CHAL_NO,GV_INTERCEPT_ZONE,GV_INTERCEPT_CHAL_BITS,GV_FIRST_INTERCEPT_CHAL_NO,
                  GV_TRUTH_PENALTY_NEXT_NO,GV_PHASE_MARKER] as $k) self::setGameStateValue($k,0);

        foreach ($this->getPlayerIds() as $pid) {
            if (count($this->cards->getCardsInLocation(HC_LOC_HAND, $pid)) == 0) { $this->gamestate->nextState('scoring'); return; }
        }
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
            $hasKitten = false; foreach ($all as $c) { $declared = intval($c['type_arg'])>0?intval($c['type_arg']):intval($c['type']); if ($declared==HC_TYPE_KITTEN) $hasKitten=true; }
            $base=0;
            foreach ($all as $c) {
                $declared = intval($c['type_arg'])>0?intval($c['type_arg']):intval($c['type']);
                if ($declared==HC_TYPE_SHOWCAT) $base += HCRules::faceValueForShowCat($hasKitten);
                else $base += HCRules::$CARD_VALUES[$declared];
            }
            $handn = count($this->cards->getCardsInLocation(HC_LOC_HAND, $pid));
            $bonus = intdiv($handn+1,2);
            $score = $base + $bonus;
            self::DbQuery("UPDATE player SET player_score=$score WHERE player_id=$pid");
            self::notifyAllPlayers('scorePlayer', clienttranslate('${player_name} scores ${score} (herd ${base} + hand bonus ${bonus})'), array(
                'player_id'=>$pid, 'player_name'=>$this->getPlayerNameById($pid), 'score'=>$score, 'base'=>$base, 'bonus'=>$bonus
            ));
        }
        $this->gamestate->nextState('endGame');
    }

    protected function setDeclaredType($card_id, $declared) { self::DbQuery("UPDATE card SET card_type_arg=$declared WHERE card_id=$card_id"); }

    protected function placePlayedToHerdAsDeclared($pid, $declared) {
        $card_id = self::getGameStateValue(GV_PLAYED_CARD_ID);
        if ($card_id == 0) return;
        $this->cards->moveCard($card_id, HC_LOC_HERD, $pid);
        $this->setDeclaredType($card_id, $declared);
        self::notifyAllPlayers('addToHerd', clienttranslate('${player_name} adds a card to herd as ${decl}'), array(
            'player_id'=>$pid, 'player_name'=>$this->getPlayerNameById($pid),
            'decl'=>$this->typeToText($declared), 'declared_type'=>$declared, 'card_id'=>$card_id
        ));
    }

    protected function firstNoFromBits($bits) { for ($i=1;$i<=6;$i++){ if ($bits & (1<<($i-1))) return $i; } return 0; }

    protected function typeToText($t) { require_once('modules/HerdingCatsRules.php'); return HCRules::getCardName($t); }

    protected function getPlayerNameById($pid) { return self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id=$pid"); }

    function zombieTurn($state, $active_player) {
        if ($state['type'] == 'activeplayer') { $this->gamestate->nextState('zombiePass'); return; }
        if ($state['type'] == 'multipleactiveplayer') { $this->gamestate->setPlayerNonMultiactive($active_player, 'resolve'); return; }
    }
}
?></file>

<file path="src/herdingcats.js">define([ "dojo","dojo/_base/declare", "ebg/core/gamegui" ], function (dojo, declare) {
    return declare("bgagame.herdingcats", ebg.core.gamegui, {
        constructor: function(){
            this.hand = [];
            this.gamedatas = null;
            this._pending = {};
        },

        setup: function( gamedatas ) {
            this.gamedatas = gamedatas;
            this.player_id = this.player_id || this.getCurrentPlayerId();
            this._renderHand(gamedatas.hand);
            for (var pid in gamedatas.players) {
                this._renderZone('herd_'+pid, gamedatas.herds[pid], false);
                this._renderZone('herdup_'+pid, gamedatas.herd_up[pid], true);
                this._renderZone('discard_'+pid, gamedatas.discards[pid], true, true);
            }
            this._setupNotifications();
        },

        onEnteringState: function(stateName, args) {
            if (stateName == 'playerDeclare' && this.isCurrentPlayerActive()) this._showDeclareUI();
            if (stateName == 'challengeWindow') this._showChallengeUI(args.args);
            if (stateName == 'selectTarget' && this.isCurrentPlayerActive()) this._showTargetUI(args.args);
            if (stateName == 'interceptDecision' && this.isCurrentPlayerActive()) this._showInterceptUI(args.args);
            if (stateName == 'bluffPenaltyPick' && this.isCurrentPlayerActive()) this._showBlindPickUI(args.args);
            if (stateName == 'truthPenaltyPick' && this.isCurrentPlayerActive()) this._showBlindPickUI(args.args);
        },

        onLeavingState: function(stateName) { this._clearUI(); },

        onUpdateActionButtons: function(stateName, args) {},

        _renderHand: function(cards) {
            var node = $('hand-area'); dojo.empty(node);
            cards.sort(function(a,b){ return a.location_arg - b.location_arg; });
            this.hand = cards;
            for (var i=0;i<cards.length;i++) {
                var c = cards[i];
                var div = dojo.create('div', { id:'hand_'+c.id, 'class':'card facedown', innerHTML:'Hand' }, node);
                dojo.addClass(div, 'clickable');
                (function(self, cid){
                    dojo.connect(div, 'onclick', function(){ self._onClickHandCard(cid); });
                })(this, c.id);
            }
        },

        _renderZone: function(zoneId, cards, faceup, discard){
            var node = $(zoneId); if (!node) return;
            dojo.empty(node);
            for (var i=0;i<cards.length;i++) {
                var c = cards[i];
                var div = dojo.create('div', { id:zoneId+'_card_'+c.id, 'class':'card '+(faceup?'faceup':'facedown')+(discard?' discard':'') }, node);
                div.innerHTML = faceup ? this._typeToText(c.type_arg>0?c.type_arg:c.type) : '';
            }
        },

        _typeToText: function(type){
            var map = {1:'Kitten',2:'Show Cat',3:'Alley Cat',4:'Catnip',5:'Animal Control',6:'Laser Pointer'};
            return map[type] || '?';
        },

        _showDeclareUI: function(){
            var panel = $('decl-area'); dojo.empty(panel);
            dojo.create('div', { innerHTML: _('Choose a hand card, pick a declaration and (if needed) a target player.') }, panel);
            var decls = [
                {t:1,n:_('Kitten')},{t:2,n:_('Show Cat')},{t:3,n:_('Alley Cat')},
                {t:4,n:_('Catnip')},{t:5,n:_('Animal Control')},{t:6,n:_('Laser Pointer')}
            ];
            var self=this;
            decls.forEach(function(d){
                var btn = dojo.create('button', { 'class':'bga-btn', innerHTML:d.n }, panel);
                dojo.connect(btn, 'onclick', function(){ self._pending.decl = d.t; self.showMessage(_('Click a hand card to play and declare ')+d.n, 'info'); });
            });
        },

        _onClickHandCard: function(card_id){
            if (!this._pending.decl) { this.showMessage(_('Choose a declaration first'), 'error'); return; }
            var decl = this._pending.decl;
            var tgtZone = (decl==3||decl==4)?1: (decl==5?2:0);
            this._pending.card_id = card_id;
            this._pending.tgtZone = tgtZone;
            if (tgtZone==0) {
                this.ajaxcall('/herdingcats/herdingcats/actDeclarePlay.html', {
                    card_id: card_id, declared_type: decl, target_player_id: 0
                }, this, function(){}, function(){});
            } else {
                this._renderTargetSelection(card_id, decl, tgtZone);
            }
        },

        // New explicit target picker in the yellow area
        _renderTargetSelection: function(card_id, decl, zone){
            var panel = $('target-area'); dojo.empty(panel);
            dojo.create('div', { innerHTML: _('Select a player to target:') }, panel);
            var self=this;
            for (var pid in this.gamedatas.players) {
                if (parseInt(pid) == parseInt(this.player_id)) continue;
                var wrap = dojo.create('div', {'class':'target-row'}, panel);
                dojo.create('span', {'class':'target-name', innerHTML: this.gamedatas.players[pid].player_name }, wrap);
                var btn = dojo.create('button', {'class':'bga-btn', innerHTML:_('Target')}, wrap);
                (function(targetPid){
                    dojo.connect(btn, 'onclick', function(){
                        self.ajaxcall('/herdingcats/herdingcats/actDeclarePlay.html', {
                            card_id: card_id, declared_type: decl, target_player_id: targetPid
                        }, self, function(){}, function(){});
                    });
                })(pid);
                // Non-interactive preview of the targeted zone
                if (zone==1) {
                    var count = (self.gamedatas.hand_counts && self.gamedatas.hand_counts[pid]) || 0;
                    var preview = dojo.create('div', {'class':'target-preview'}, wrap);
                    for (var i=0;i<count;i++) dojo.create('div', {'class':'card facedown', innerHTML:''}, preview);
                } else if (zone==2) {
                    var herd = self.gamedatas.herds[pid] || [];
                    var preview = dojo.create('div', {'class':'target-preview'}, wrap);
                    herd.forEach(function(c){
                        dojo.create('div', {'class':'card facedown', innerHTML:''}, preview);
                    });
                }
            }
        },

        _showChallengeUI: function(args){
            var panel = $('challenge-area'); dojo.empty(panel);
            var msg = _('Challenge the claim or pass.');
            if (args && args.targetPlayer && args.targetZone) {
                msg += ' '+_('Target: ')+ args.targetPlayer;
            }
            dojo.create('div',{innerHTML:msg}, panel);
            var self=this;
            this.addActionButton('btnChallenge', _('Challenge'), function(){
                self.ajaxcall('/herdingcats/herdingcats/actChallenge.html', {}, self, function(){}, function(){});
            });
            this.addActionButton('btnPass', _('Pass'), function(){
                self.ajaxcall('/herdingcats/herdingcats/actPassChallenge.html', {}, self, function(){}, function(){});
            });
        },

        _showTargetUI: function(args){
            var panel = $('target-area'); dojo.empty(panel);
            if (args.zone == 1) {
                dojo.create('div',{innerHTML:_('Select a slot in the target hand.')}, panel);
                for (var i=1;i<=args.handSize;i++) {
                    (function(idx){
                        var btn = dojo.create('button', {'class':'bga-btn', innerHTML:_('Slot ')+idx}, panel);
                        dojo.connect(btn, 'onclick', function(){ 
                            this.ajaxcall('/herdingcats/herdingcats/actSelectHandSlot.html', { target_player_id: args.targetPlayer, slot_index: idx }, this, function(){}, function(){});
                        }.bind(this));
                    }).call(this,i);
                }
            } else if (args.zone == 2) {
                dojo.create('div',{innerHTML:_('Select a face-down herd card.')}, panel);
                var ids = args.herdCards || [];
                var self=this;
                ids.forEach(function(cid){
                    var el = $('herd_'+args.targetPlayer+'_card_'+cid);
                    if (el) {
                        dojo.addClass(el, 'clickable');
                        dojo.connect(el, 'onclick', function(){
                            self.ajaxcall('/herdingcats/herdingcats/actSelectHerdCard.html', { target_player_id: args.targetPlayer, card_id: cid }, self, function(){}, function(){});
                        });
                    }
                });
            }
        },

        _showInterceptUI: function(args){
            var panel = $('intercept-area'); dojo.empty(panel);
            dojo.create('div',{innerHTML:_('Declare Laser Pointer intercept?')}, panel);
            var self=this;
            this.addActionButton('btnNoIntercept', _('No'), function(){
                self.ajaxcall('/herdingcats/herdingcats/actDeclineIntercept.html', {}, self, function(){}, function(){});
            });
            this.addActionButton('btnYesIntercept', _('Yes'), function(){
                self.ajaxcall('/herdingcats/herdingcats/actDeclareIntercept.html', { zone: args.allowedZone }, self, function(){}, function(){});
            });
        },

        _showBlindPickUI: function(args){
            var panel = $('target-area'); dojo.empty(panel);
            dojo.create('div',{innerHTML:_('Pick a blind slot from the target hand.')}, panel);
            var n = args.handSize || 0;
            for (var i=1;i<=n;i++) {
                (function(idx){
                    var btn = dojo.create('button', {'class':'bga-btn', innerHTML:_('Slot ')+idx}, panel);
                    dojo.connect(btn, 'onclick', function(){ 
                        this.ajaxcall('/herdingcats/herdingcats/actPickBlindFromHand.html', { target_player_id: args.targetPlayer, slot_index: idx }, this, function(){}, function(){});
                    }.bind(this));
                }).call(this,i);
            }
        },

        _clearUI: function(){
            ['decl-area','challenge-area','target-area','intercept-area'].forEach(function(id){ var n=$(id); if (n) dojo.empty(n); });
            for (var pid in this.gamedatas.players) {
                var board=$('playerboard_'+pid); if (!board) continue;
                var hdr = board.querySelector('.pb-header .pb-name'); if (hdr) dojo.removeClass(hdr, 'clickable');
            }
            dojo.query('.card.clickable').removeClass('clickable');
        },

        _setupNotifications: function(){
            dojo.subscribe('declarePlay', this, function(notif){});
            dojo.subscribe('challengeMade', this, function(notif){});
            dojo.subscribe('revealPlayed', this, function(notif){ this.showMessage(_('Played card was ')+notif.args.printed, 'info'); }.bind(this));
            dojo.subscribe('revealHandCard', this, function(notif){ this.showMessage(_('Revealed ')+notif.args.card, 'info'); }.bind(this));
            dojo.subscribe('revealHerdCard', this, function(notif){ this.showMessage(_('Revealed ')+notif.args.card, 'info'); }.bind(this));
            dojo.subscribe('addToHerd', this, function(notif){ this.showMessage(_('A card was added to herd as ')+notif.args.decl, 'info'); }.bind(this));
            dojo.subscribe('scorePlayer', this, function(notif){ this.showMessage(_(notif.args.player_name+' scores '+notif.args.score), 'info'); }.bind(this));
        },
    });
});</file>
</codebase>
