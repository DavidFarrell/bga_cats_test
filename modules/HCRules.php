<?php
/**
 * Modules - rules helpers and constants mapping
 */
class HCRules {

    // Toggle the Laser Pointer buff: on truthful intercept, put Laser into herd instead of discard
    public static $BUFF_LASER_TO_HERD = true;

    public static $CARD_NAMES = array(
        HC_TYPE_KITTEN => 'Kitten',
        HC_TYPE_SHOWCAT => 'Show Cat',
        HC_TYPE_ALLEY => 'Alley Cat',
        HC_TYPE_CATNIP => 'Catnip',
        HC_TYPE_ANIMAL => 'Animal Control',
        HC_TYPE_LASER => 'Laser Pointer',
    );

    // Base values at scoring
    public static $CARD_VALUES = array(
        HC_TYPE_KITTEN => 2,
        HC_TYPE_SHOWCAT => 5, // may become 7 if any kitten present
        HC_TYPE_ALLEY => 1,
        HC_TYPE_CATNIP => 1,
        HC_TYPE_ANIMAL => 0,
        HC_TYPE_LASER => 0,
    );

    public static function getCardName($type) {
        return self::$CARD_NAMES[$type];
    }

    public static function getTargetZoneForDeclared($type) {
        if ($type == HC_TYPE_ALLEY || $type == HC_TYPE_CATNIP) return HC_TGT_HAND;
        if ($type == HC_TYPE_ANIMAL) return HC_TGT_HERD;
        return HC_TGT_NONE;
    }

    public static function declaredToText($type) {
        return self::getCardName($type);
    }

    public static function faceValueForShowCat($hasKitten) {
        return $hasKitten ? 7 : 5;
    }

    // Utility: compute hand index list from cards ordered by card_location_arg
    public static function handIndexMap($cards) {
        // returns map index(1-based)->card
        $res = array();
        $pos = 1;
        foreach ($cards as $c) {
            $res[$pos] = $c;
            $pos++;
        }
        return $res;
    }

    public static function normalizeHandPositions($cards, $deck, $pid) {
        $pos = 1;
        foreach ($cards as $c) {
            if ((int)$c['location_arg'] != $pos) {
                $deck->moveCard($c['id'], HC_LOC_HAND, $pid);
                self::setCardPos($deck, $c['id'], $pos);
            }
            $pos++;
        }
    }

    public static function setCardPos($deck, $card_id, $pos) {
        $sql = "UPDATE card SET card_location_arg=$pos WHERE card_id=$card_id";
        $deck->DbQuery($sql);
    }
}