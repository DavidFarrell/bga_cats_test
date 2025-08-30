<?php
/**
 * Herding Cats - gameinfos
 * Keep the game name equal to the Studio project directory.
 */
$gameinfos = array(
    'game_name' => 'Herding Cats',
    'designer' => 'Internal',
    'artist' => 'Internal',
    'year' => 2025,
    'publisher' => '—',
    'publisher_website' => '',
    'players' => array( 2, 3, 4, 5, 6 ),
    'suggest_player_number' => 4,
    'not_recommend_player_number' => array(),
    'estimated_duration' => 15,
    'fast_additional_time' => 2,
    'slow_additional_time' => 5,
    'tie_breaker_description' => '',
    'losers_not_ranked' => false,
    'interface_version' => 2,
    'is_beta' => 1,
    'tags' => array( 'bluff', 'hand-management', 'deduction' ),
    'presentation' => array(
        totranslate('Bluff, challenge and herd the highest-scoring cats.'),
    ),
    'game_interface' => array(
        'minimum' => array( 'notifs' => 1, 'chat' => 1 ),
        'recommended' => array( 'tooltip' => 1, 'zoom' => 1, 'mobile' => 1 )
    ),
    'player_colors' => array( 'ff0000', '008000', '0000ff', 'ffa500', 'e94190', '982fff' )
);