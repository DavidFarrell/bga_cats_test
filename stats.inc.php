<?php
/**
 * stats.inc.php
 */
$stats_type = array(
    'table' => array(
        'rounds' => array(
            'id' => 10,
            'name' => totranslate('Turns played'),
            'type' => 'int'
        ),
    ),
    'player' => array(
        'bluffs_caught' => array( 'id'=>11, 'name'=> totranslate('Bluffs caught'), 'type'=>'int' ),
        'failed_challenges' => array( 'id'=>12, 'name'=> totranslate('Failed challenges'), 'type'=>'int' ),
        'intercepts' => array( 'id'=>13, 'name'=> totranslate('Successful intercepts'), 'type'=>'int' ),
        'cards_in_herd' => array( 'id'=>14, 'name'=> totranslate('Cards in herd at end'), 'type'=>'int' ),
    )
);