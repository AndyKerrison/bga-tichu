<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Tichu implementation : © Bryan McGinnis <bryanrm@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * material.inc.php
 *
 * Hearts game material description
 * Here, you can describe the material of your game with PHP variables.
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code. *
 */
$this->colors = array(
	1 => array( 'name' => clienttranslate('Club'),	 'nametr' => self::_('Club') ),
	2 => array( 'name' => clienttranslate('Spade'),  'nametr' => self::_('Spade') ),
	3 => array( 'name' => clienttranslate('Heart'),  'nametr' => self::_('Heart') ),
	4 => array( 'name' => clienttranslate('Diamond'),'nametr' => self::_('Diamond') )
);
$this->values_label = array(
	2 =>'2',
	3 => '3',
	4 => '4',
	5 => '5',
	6 => '6',
	7 => '7',
	8 => '8',
	9 => '9',
	10 => '10',
	11 => clienttranslate('Jack'),
	12 => clienttranslate('Queen'),
	13 => clienttranslate('King'),
	14 => clienttranslate('Ace')
);
$this->specials_label = array(
	1 => clienttranslate('Dog'),
	2 => clienttranslate('Mah Jong'),
	3 => clienttranslate('Phoenix'),
	4 => clienttranslate('Dragon')
);
$this->specials_value = array(
	1 => clienttranslate('0.5'),
	2 => clienttranslate('1'),
	3 => clienttranslate('1.5'), // This changes
	4 => clienttranslate('15')
);
$this->play_type = array(
	0  => 'Single',
	1  => 'Doubles',
	2  => 'Triples',
	3  => 'Full House',
	4  => 'Consecutive Doubles',
	5  => 'Run of 5',
	6  => 'Run of 6',
	7  => 'Run of 7',
	8  => 'Run of 8',
	9  => 'Run of 9',
	10 => 'Run of 10',
	11 => 'Run of 11',
	12 => 'Run of 12',
	13 => 'Run of 13',
	14 => 'Run of 14',
	15 => 'Dog',
	20 => 'Bomb'
);