
--------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Tichu implementation : © Bryan McGinnis <bryanrm@gmail.com>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-------

-- dbmodel.sql

-- This is the file where your are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- these export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here
--

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.

-- add items to database
ALTER TABLE `player` ADD `player_first` BOOLEAN NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_team` int(3) NOT NULL DEFAULT '-1';
ALTER TABLE `player` ADD `player_maxscore` int(7) NOT NULL DEFAULT '1000';
ALTER TABLE `player` ADD `player_call_tichu` BOOLEAN NOT NULL DEFAULT '0';
ALTER TABLE `player` ADD `player_call_grand_tichu` BOOLEAN NOT NULL DEFAULT '0';

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,	
  `card_type` int(11) NOT NULL,								
  `card_type_arg` int(11) NOT NULL,							
  `card_location` varchar(16) NOT NULL,					
  `card_location_arg` int(11) NOT NULL,					
  `card_cards_order` int(11) NOT NULL DEFAULT -1,		
  `card_plays_order` int(11) NOT NULL DEFAULT -1,		
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
-- card_id = Random ID Order for keeping unknown
-- card_type = Color/Suit (or type of special)
-- card_type_arg = Number of card 2-14 (20-140) or Special (10)
-- card_location = Hand, temporary(trading), cardsontable, trick
-- card_location_arg = Player ID for the card
-- card_cards_order = Placement (Card #1-14; multiple cards in one play)
-- card_plays_order = Placement (Play #1-14; multiple plays in one trick)