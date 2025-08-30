-- Standard BGA deck tables
CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` int(10) unsigned NOT NULL,
  `card_type_arg` int(11) NOT NULL DEFAULT 0,
  `card_location` varchar(32) NOT NULL,
  `card_location_arg` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `card_global` (
  `global_id` int(10) unsigned NOT NULL,
  `global_value` int(10) unsigned NOT NULL,
  PRIMARY KEY (`global_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;