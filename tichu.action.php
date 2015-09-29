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
 * Tichu main action entry point
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/emptygame/emptygame/myAction.html", ...)
 */
class action_tichu extends APP_GameAction { 
	public function __default() {
		if( self::isArg( 'notifwindow') ) {
			$this->view = "common_notifwindow";
			$this->viewArgs['table'] = self::getArg( "table", AT_posint, true );
		} else {
			$this->view = "tichu_tichu";
			self::trace( "Complete reinitialization of board game" );
		}
	} 
	public function playCards() {
		self::setAjaxMode();     
		// For Hearts play 1 card
		// $card_id = self::getArg( "id", AT_posint, true );
		// $this->game->playCards( $card_id );
		
		// Tichu: Play multiple cards
		$cards_raw = self::getArg( "cards", AT_numberlist, true );
		// Removing last ';' if exists
		if( substr( $cards_raw, -1 ) == ';' ) $cards_raw = substr( $cards_raw, 0, -1 );
		// Extract array of cards to play or blank array
		if( $cards_raw == '' ) $card_ids = array();
		else $card_ids = explode( ';', $cards_raw );
		// echo 'Play cards:<pre>';print_r($card_ids);echo '</pre>';
		$this->game->playCards( $card_ids );
		self::ajaxResponse( );
	}
	public function giveCards() {
		self::setAjaxMode();     
		$cards_raw = self::getArg( "cards", AT_numberlist, true );
		// Removing last ';' if exists
		if( substr( $cards_raw, -1 ) == ';' )
			$cards_raw = substr( $cards_raw, 0, -1 );
		if( $cards_raw == '' )
			$cards = array();
		else
			$cards = explode( ';', $cards_raw );
		
		$this->game->giveCards( $cards );
		self::ajaxResponse( );    
	}
	public function passPlay() {
		self::setAjaxMode();     
		$this->game->passPlay();
		self::ajaxResponse( );    
	}
}