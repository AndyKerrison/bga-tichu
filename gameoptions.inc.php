<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Tichu implementation : © Bryan McGinnis <bryanrm@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * gameoptions.inc.php
 *
 * Hearts game options description
 *
 * In this file, you can define your game options (= game variants).
 *
 * Note: If your game has no variant, you don't have to modify this file.
 *
 * Note²: All options defined in this file should have a corresponding "game state labels"
 *        with the same ID (see "initGameStateLabels" in emptygame.game.php)
 *
 * !! It is not a good idea to modify this file when a game is running !!
 *
 */

$game_options = array(
	100 => array(
		'name' => totranslate('Game length'),
		'values' => array(
				1 => array( 'name' => totranslate( 'Quick game (600 points)' ) ),
				2 => array( 'name' => totranslate( 'Standard game (1000 points)' ) )
		),
		'default' => 2
	)
	,101 => array(
		'name' => totranslate('Grand Tichu'),
		'values' => array(
				// 1 => array( 'name' => totranslate( 'Yes' ) ),
				2 => array( 'name' => totranslate( 'No' ) )
		),
		'default' => 2
	)
);