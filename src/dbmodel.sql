-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- HerdingCats implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you HAVE TO express yourself with SQL language
-- 
-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- Note: The database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The players table already contains some standard fields you can use directly:
--       "player_id" (int, primary key), "player_name" (string), "player_avatar" (string), "player_color" (string),
--       "player_eliminated" (bool), "player_score" (int), "player_score_aux" (int/tie breaker), "player_zombie" (bool)
-- But you can add here some supplementary fields for your game
-- you can also override the default SQL for "player" table here

-- Card table for BGA Deck component
CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` int(11) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

-- Pending action tracking (single row table)
CREATE TABLE IF NOT EXISTS `pending_action` (
  `id` int(1) NOT NULL DEFAULT 1,
  `actor_player_id` int(10) DEFAULT NULL,
  `declared_identity` int(11) DEFAULT NULL,
  `played_card_id` int(10) DEFAULT NULL,
  `target_player_id` int(10) DEFAULT NULL,
  `target_zone` varchar(16) DEFAULT NULL,
  `selected_card_id` int(10) DEFAULT NULL,
  `challengers` varchar(255) DEFAULT NULL,
  `intercept_player_id` int(10) DEFAULT NULL,
  `intercept_zone` varchar(16) DEFAULT NULL,
  `intercept_card_id` int(10) DEFAULT NULL,
  `intercept_challengers` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
