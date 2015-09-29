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
 * hearts.view.php
 *
 * This is your "view" file.
 *
 * The method "build_page" below is called each time the game interface is displayed to a player, ie:
 * _ when the game starts
 * _ when a player refreshes the game page (F5)
 *
 * "build_page" method allows you to dynamically modify the HTML generated for the game interface. In
 * particular, you can set here the values of variables elements defined in emptygame_emptygame.tpl (elements
 * like {MY_VARIABLE_ELEMENT}), and insert HTML block elements (also defined in your HTML template file)
 *
 * Note: if the HTML of your game interface is always the same, you don't have to place anything here.
 *
 */
require_once( APP_BASE_PATH."view/common/game.view.php" );
class view_tichu_tichu extends game_view {
	function getGameName() { return "tichu"; }    
	function build_page( $viewArgs ) {		
		// Get players & players number
		$players = $this->game->loadPlayersBasicInfos();
		$players_nbr = count( $players );
		
		/*********** Place your code below:  ************/
		// Arrange players so that I am on south
		$player_to_dir = $this->game->getPlayersToDirection();
		
		$this->page->begin_block( "tichu_tichu", "player" );
		foreach( $player_to_dir as $player_id => $dir )	{
			$this->page->insert_block( "player", array( "PLAYER_ID" => $player_id,
			                                            "PLAYER_NAME" => $players[$player_id]['player_name'],
			                                            "PLAYER_COLOR" => $players[$player_id]['player_color'],
			                                            "DIR" => $dir ) );
		}
		
		$this->tpl['MY_HAND'] = self::_("My hand");
		/*********** Do not change anything below this line  ************/
	}
}