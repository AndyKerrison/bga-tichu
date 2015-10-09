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
 * Tichu game states description
 */

/*  Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
*   in a very easy way from this configuration file.
*
*   Please check the BGA Studio presentation about game state to understand this, and associated documentation.
*
*   Summary:
*
*   States types:
*   _ activeplayer: in this type of state, we expect some action from the active player.
*   _ multipleactiveplayer: in this type of state we expect some action from multiple players (the active players)
*   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
*   _ manager: special type for initial and final state
*
*   Arguments of game states:
*   _ name: the name of the GameState, in order you can recognize it on your own code.
*   _ description: the description of the current game state is always displayed in the action status bar on
*                  the top of the game. Most of the time this is useless for game state with "game" type.
*   _ descriptionmyturn: the description of the current game state when it's your turn.
*   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
*   _ action: (required for type game) name of the method to call when this game state become the current game
*				state. Usually, the action method is prefixed by "st" (ex: "stMyGameStateName").
*   _ possibleactions: (required for types activeplayer & multipleactiveplayer)
*					array that specify possible player actions on this step. It allows you to use "checkAction"
*              method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
*   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
*                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
*                  specify the next game state for each transition.
*   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
*           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
*   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
*                            method).
*		!! It is not a good idea to modify this file when a game is running !!
*		http://en.doc.boardgamearena.com/Your_game_state_machine:_states.inc.php
*/
$machinestates = array(
	// The initial state. Please do not modify.
	1 => array(
	  "name" => "gameSetup",
	  "description" => clienttranslate("Game setup"),
	  "type" => "manager",
	  "action" => "stGameSetup",
	  "transitions" => array( "" => 20 )
	),
	
	/// New hand
	20 => array(
	  "name" => "newHand",
	  "description" => "",
	  "type" => "game",
	  "action" => "stNewHand",
	  "updateGameProgression" => true,   
	  "transitions" => array( "" => 21 )
	),
	21 => array(       
	  "name" => "giveCards1",
	   "type" => "game",
	  "action" => "stGiveCards",
	  "args" => "argGiveCards",
	  "transitions" => array( "cardsDealt" => 120)        
	),
    120 => array(       
	  "name" => "declareGrandTichu",
	  "description" => clienttranslate('${actplayer} must choose to call Grand Tichu or Pass'),
	  "descriptionmyturn" => clienttranslate('${you} must choose to call Grant Tichu or Pass'),
	  "type" => "activeplayer",
	  "possibleactions" => array( "callGrandTichu", "passGrandTichu" ),
	  "transitions" => array( "grandTichuCalled" => 130, "passGrandTichu" => 125, "allSkipped" => 130)        
	),
    125 => array(       
	  "name" => "nextPlayerDeclareGrandTichu",
      "type" => "game",
      "action" => "stNextPlayerDeclareGrandTichu",
	  "transitions" => array( "" => 120)        
	),
    130 => array(       
	  "name" => "passCards",
	  "type" => "multipleactiveplayer",
      "action" => "stPassCards",
      "descriptionmyturn" => clienttranslate('${you} must pass a card left, right, and across'),
      "possibleactions" => array( "passCards" ),
      "transitions" => array( "passCards" => 22)        
	),
	22 => array(
	  "name" => "takeCards",
	  "description" => "",
	  "type" => "game",
	  "action" => "stTakeCards",
	  "transitions" => array( "startHand" => 30, "skip" => 30  )
	),        
	
	// Trick
	30 => array(
	  "name" => "newTrick",
	  "description" => "",
	  "type" => "game",
	  "action" => "stNewTrick",
	  "transitions" => array( "" => 31 )
	),
	31 => array(
	 "name" => "beforePlayerTurn",
	 "description" => "",
	 "type" => "game",
	 "action" => "stBeforePlayerTurn",
	 "transitions" => array( "startTurn" => 32, "skipPlayer" => 33 )
	), 
	32 => array( 
	  "name" => "playerTurn",
	  "description" => clienttranslate('${actplayer} must play a card'),
	  "descriptionmyturn" => clienttranslate('${you} must play a card'),
	  "type" => "activeplayer",
	  //"args" => "argPlayerTurn" // This can be added to validate play on client side (Tichu,Pass,Bomb,PlayType)
	  			// Also used to change description to You must/ You may play a card
	  			// Add to game.php: function argPlayerTurn(){return array('possibleMoves'=>self::getPossibleMoves);}
	  "possibleactions" => array( "playCards", "passPlay" ), // These are the buttons on GUI
	  "transitions" => array( "playCards" => 34/*, "passPlay" => 33*/ ) // After action, then run playCards() transition
	),
	33 => array(
	  "name" => "skipPlayerTurn",
	  "description" => "",
	  "type" => "game",
	  "action" => "stSkipPlayerTurn",
	  "transitions" => array( "" => 34 )
	),
	34 => array(
	  "name" => "endTurn",
	  "description" => "",
	  "type" => "game",
	  "action" => "stEndTurn",
	  "transitions" => array( "nextPlayer" => 31, "skipPlayer" => 33, "nextTrick" => 30, "endHand" => 40, "passDragonTrick" => 35 )
	), 
	35 => array(
		"name" => "passDragonTrickDecision",
		"descriptionmyturn" => clienttranslate('${you} must choose a player from other team to pass trick to'),
		"type" => "activeplayer",
		"possibleactions" => array( "giveLeft", "giveRight" ),
		"transitions" => array( "nextTrick" => 30, "endHand" => 40 )
	),

	// End of the hand (scoring, etc...)
	40 => array(
	  "name" => "endHand",
	  "description" => "",
	  "type" => "game",
	  "action" => "stEndHand",
	  "transitions" => array( "nextHand" => 20, "endGame" => 99 )
	),     
	
	// Final state.
	// Please do not modify.
	99 => array(
	  "name" => "gameEnd",
	  "description" => clienttranslate("End of game"),
	  "type" => "manager",
	  "action" => "stGameEnd",
	  "args" => "argGameEnd"
	)
);