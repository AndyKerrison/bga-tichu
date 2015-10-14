<?php
/*
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Tichu implementation : © Bryan McGinnis <bryanrm@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * tichu.game.php
 *
 * This is the main file for your game logic.
 * In this PHP file, you are going to defines the rules of the game.
 */  
require_once( APP_GAMEMODULE_PATH.'module/table/table.game.php' );
class Tichu extends Table {
	function Tichu( ) {
		// Your global variables labels:
		//  Here, you can assign labels to global variables you are using for this game.
		//  You can use any number of global variables with IDs between 10 and 99.
		//  If your game has options (variants), you also have to associate here a label to
		//  the corresponding ID in gameoptions.inc.php.
		// Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
		parent::__construct();self::initGameStateLabels( array(
			"currentHandType" 		=> 10,
			"maxCardValue"				=> 11,
			"alreadyFulfilledWish"	=> 12,
			"playType"					=> 13,
			"lastPlayPlayer"			=> 14,
			"firstOutPlayer"			=> 15,
			"lastOutPlayer"			=> 16,
			"OneTwoVictory"			=> 17,
			"dogNextPlayer"			=> 18,
			"gameLength"				=> 100,
			"grandTichu"				=> 101,
            "grandTichuPasses"          => 102,
		) );
		$this->cards = self::getNew( "module.common.deck" );
		$this->cards->init( "card" );
	}
	protected function getGameName() { return "tichu"; }	
	
	protected function setupNewGame( $players, $options = array() ) {    
        self::debug("setupNewGame");
		/*  setupNewGame:
		This method is called 1 time when a new game is launched.
		In this method, you must setup the game according to game rules, in order
		the game is ready to be played.    */
		$sql = "DELETE FROM player WHERE 1 ";
		self::DbQuery( $sql ); 
		
		// Set the colors of the players with HTML color code
		// The default below is red/green/blue/yellow
		// The number of colors defined here must correspond to the max number of players allowed for the games
		$default_color = array( "ff0000", "008000", "0000ff", "ffa500" );
		
		$start_points = 0;
		$end_points = self::getGameStateValue( 'gameLength' ) == 1 ? 600 : 1000;
		//$start_points = self::getGameStateValue( 'grandTichu' ) == 1 ? 75 : 100;
		
		// Create players
		// Note: if you added some extra field on "player" table in (dbmodel.sql), you can initialize it here.
		$sql = "INSERT INTO player (player_id, player_team, player_score, player_maxscore, player_color, player_canal, player_name, player_avatar) VALUES ";
		$values = array(); $i=0;
		foreach( $players as $player_id => $player ) {
			$color = array_shift( $default_color );
			$player_team = $i % 2; // Make 1st & 3rd player team 0, 2nd & 4th team 1
			$values[] = "('$player_id','$player_team','$start_points','$end_points','$color','".$player['player_canal']."','".addslashes( $player['player_name'] )."','".addslashes( $player['player_avatar'] )."')";
			$i++; }
		$sql .= implode(',',$values);
		self::DbQuery( $sql );
		self::reloadPlayersBasicInfos();
		
		/********************** Start the game initialization *******************/
		// Init global values with their initial values
		// Note: hand types: 0 = give 3 cards to player on the left
		//                   1 = give 3 cards to player on the right
		//                   2 = give 3 cards to player on tthe front
		//                   3 = keep cards
		//
		// Need to change to give 1 card to West, 1 to East, 1 to North
		// I would like to set it up to move first card clicked to W, second to E, third to N
		// Then have the Send cards button, and a back button
		self::setGameStateInitialValue( 'currentHandType', 0 ); // For passing
		// Set high card value
		self::setGameStateInitialValue( 'maxCardValue', 0 );
		// Mark if the wish has been fulfilled during this hand
		self::setGameStateInitialValue( 'alreadyFulfilledWish', 0 );
		// Play Type per hand, this can't change except bombs and off of dog, -1=not set, see line 259
		self::setGameStateInitialValue( 'playType', -1 );
		// Add Last person to play 
		self::setGameStateInitialValue( 'lastPlayPlayer', 0 );
		// First to go out
		self::setGameStateInitialValue( 'firstOutPlayer', 0 );
		// Last to to out
		self::setGameStateInitialValue( 'lastOutPlayer', 0 );
		// 1-2 Victory 
		self::setGameStateInitialValue( 'OneTwoVictory', -1 );
		// Dog Skip (Skip if this player if he is flagged or this prop > 0
		self::setGameStateInitialValue( 'dogNextPlayer', 0 );
        //number of passes during grand tichu phase
		self::setGameStateInitialValue( 'grandTichuPasses', 0 );
		// Count # of consecutive passes, if 3 then trick is over
		// self::setGameStateInitialValue( 'consecutivePassPlays', 0 );
		
		// Init game statistics
		// (note: statistics are defined in your stats.inc.php file)
		self::initStat( "table" , "handNbr", 0 );
		self::initStat( "player", "getQueenOfSpade", 0 );
		self::initStat( "player", "getHearts", 0 );
		self::initStat( "player", "getAllPointCards", 0 );
		self::initStat( "player", "getNoPointCards", 0 );
		// Create cards in deck
		$cards = array();
		foreach( $this->colors as  $color_id => $color ) { // club=1, spade=2, heart=3, diamond=4
			for( $value=1; $value<=14; $value++ ) {  //  2, 3, 4, ... K, A
				$cards[]=array('type'=>$color_id, 'type_arg'=>$value, 'nbr'=>1); } }
		$this->cards->createCards( $cards, 'deck' );
		// Reset plays_order & cards_order
		$sql="UPDATE card SET card_plays_order='0', card_cards_order='0'";
		self::DbQuery( $sql );
		// Reset all players have called tichu or grand tichu this hand 
		$sql="UPDATE player SET player_call_tichu='0', player_call_grand_tichu='0'";
		self::DbQuery( $sql );
		
        $this->activeNextPlayer();

		/********************* End of the game initialization ********************/
	}
	/*  getAllDatas: 
	Gather all informations about current game situation (visible by the current player).
	The method is called each time the game interface is displayed to a player, ie:
		_ when the game starts
		_ when a player refresh the game page (F5)  */
	protected function getAllDatas() {
        self::debug("getAllDatas");
		$result = array( 'players' => array() );
		$player_id = self::getCurrentPlayerId();  // !! We must only return informations visible by this player !!
		
		// Get information about players
		// Note: you can retrieve some extra field you add for "player" table in "dbmodel.sql" if you need it.
		$sql = "SELECT player_id id, player_score score, player_team team, player_call_tichu call_tichu, player_call_grand_tichu call_grand_tichu FROM player ORDER BY player_no";
		$dbres = self::DbQuery( $sql );
		while( $player=mysql_fetch_assoc($dbres) ) 
			$result['players'][ $player['id'] ] = $player;
		
		// Get cards in current player hand (1st person)
		$result['hand'] = $this->getCardsInLocation( 'hand', $player_id );
		// $playerHand2 = $this->cards->getCardsInLocation( 'hand', $player_id );
		// var_dump($result['hand']);
		// var_dump($playerHand2);
		
		// Cards played on the table (for all players)
		$result['cardsontable'] = $this->getCardsInLocation( 'cardsontable' );
		
		return $result;
	}
	
	/*  getGameProgression:
	Compute and return the current game progression.
	The number returned must be an integer beween 0 (=the game just started) and
	100 (= the game is finished or almost finished).
	
	This method called each time in a game state with "updateGameProgression" property (see states.inc.php) */
	function getGameProgression() { // Game progression: get player minimum score
		$maximumScore = self::getUniqueValueFromDb( "SELECT MAX( player_score ) FROM player" );
		$end_points = self::getGameStateValue( 'gameLength' ) == 1 ? 600 : 1000;
		return max( 0, min( 100, intval($maximumScore/$end_points*100) ) );   // Note: 0 => 100
	}

//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    
	/*  At this place, you can put any utility methods useful for your game logic  */
	// Return players => direction (N/S/E/W) from the point of view
	//   of current player (current player must be on south)
	function getPlayersToDirection() {
		$result = array();
		
		$players = self::loadPlayersBasicInfos(); // Get all players info
		$nextPlayer = self::createNextPlayerTable( array_keys( $players ) );
		$current_player = self::getCurrentPlayerId();
		$directions = array( 'S', 'E', 'N', 'W' ); // Tichu goes counter clockwise
		
		if( ! isset( $nextPlayer[ $current_player ] ) ) {
			// Spectator mode: take any player for south
			$player_id = $nextPlayer[0];
			$result[ $player_id ] = array_shift( $directions );
		} else {
			// Normal mode: current player is on south
			$player_id = $current_player;
			$result[ $player_id ] = array_shift( $directions ); 
		}
		while( count( $directions ) > 0 ) {
			$player_id = $nextPlayer[ $player_id ];
			$result[ $player_id ] = array_shift( $directions );
		}
		return $result;
	}
	// This is my own version of this. The one by the same named called by $this->cards->getCardsInLocation()
	//      was not pulling the new fields that were added to database (cards_order & plays_order)
	function getCardsInLocation($location,$player_id=0) {
		$sql="SELECT * FROM card WHERE card_location='$location'";
		if ($player_id>0)
			$sql.=" AND card_location_arg='$player_id'";
		// echo $sql;
		$result=self::DbQuery( $sql );
		$arr3=array();
		while ($arr1=mysql_fetch_assoc($result)) {
			if($arr1['card_id']>0) {
				$id=$arr2['id']=$arr1['card_id'];
				$arr2['type']=$arr1['card_type'];
				$arr2['type_arg']=$arr1['card_type_arg'];
				$arr2['location']=$arr1['card_location'];
				$arr2['location_arg']=$arr1['card_location_arg'];
				$arr2['cards_order']=$arr1['card_cards_order'];
				$arr2['plays_order']=$arr1['card_plays_order'];
				$arr3[$id]=$arr2;
			}
		}
		if ($arr3) return $arr3;
	}

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 
	/*  Each time a player is doing some game action, one of this method below is called.
		(note: each method below correspond to an input method in tichu.action.php) */
    function passGrandTichu(){
        self::notifyAllPlayers( 'grandTichuPass',
            clienttranslate('${player_name} passes'), array(
            'player_name' => self::getActivePlayerName()
            ) );

        $grandTichuPasses = self::getGameStateValue( 'grandTichuPasses');
        $grandTichuPasses = $grandTichuPasses + 1;
        self::setGameStateValue( 'grandTichuPasses', $grandTichuPasses);
        if ($grandTichuPasses < 4)
        {
            $this->gamestate->nextState( 'nextGrandTichu' ); // Next player
        }
        else
        {
            $this->gamestate->nextState( 'allSkipped' );
        }
    }
	function callGrandTichu()
    { // More than 1 player can call Grand Tichu!
        // In player table, set player_call_grand_tichu to 1
		  $player_id = self::getActivePlayerId();
		  $sql="UPDATE player SET player_call_grand_tichu='1' WHERE player_id='$player_id'";
		  self::DbQuery( $sql );

        self::notifyAllPlayers( 'grandTichuCall',
            clienttranslate('${player_name} calls Grand Tichu'), array(
				'player_id' => $player_id,
            'player_name' => self::getActivePlayerName()
            ) );
        
        // Change player's name, adding " (Grand Tichu)"

        $grandTichuPasses = self::getGameStateValue( 'grandTichuPasses');
        $grandTichuPasses = $grandTichuPasses + 1;
        self::setGameStateValue( 'grandTichuPasses', $grandTichuPasses);
        if ($grandTichuPasses < 4)
        {
            $this->gamestate->nextState( 'nextGrandTichu' ); // Next player
        }
        else
        {
            $this->gamestate->nextState( 'allSkipped' );
        }
    }
	function passPlay(){ // Press Pass button, skip turn
		self::checkAction( "passPlay" );
		if (self::getGameStateValue( 'playType' )<0) // If no cards or only dog on table you can't pass
			throw new feException( 'Must play a card' );
		$player_id = self::getActivePlayerId();
		self::notifyAllPlayers( 'passPlay',
			clienttranslate('${player_name} passes'), array(
				// 'i18n' => array( 'specials_label' ),
				'player_id' => $player_id,
				'player_name' => self::getActivePlayerName()
		) );
		// Directs to states.inc.php:104,:110->stNextPlayer(tichu.game.php:513)
		$this->gamestate->nextState( 'playCards' ); 
	}
	function playCards( $playCardsIds ) { // Press Play button, play cards from player hand
        self::debug("PLAYCARDS server action called with ids [".$playCardsIds."]");
		self::checkAction( "playCards" );
		$player_id = self::getActivePlayerId();
		// Get all cards in player hand (for checking if the cards played are in player's hand)
		$playerHand = $this->getCardsInLocation( 'hand', $player_id );
		// $bFirstCard = ( count( $playerHand ) == 14 ); // Not necessary in Tichu to track the first play
		$maxCardValue = self::getGameStateValue( 'maxCardValue' );
		 
		// Check that the card is in this hand (for Hearts)
		// I might need some of this validation (for Tichu)
		$currentCards = null;
		$bIsInHand = false;
		//$bAtLeastOneCardOfCurrentTrickColor = false;
		//$bAtLeastOneCardWithoutPoints = false;
		//$bAtLeastOneCardNotHeart = false;
		
		// Build an array of card_id's in the player's hand
		$playerHandIds=array();$playCards=array();
		foreach ($playerHand as $card)
        {
			$playerHandIds[]=$card['id'];
			if (in_array($card['id'],$playCardsIds))
            {
                $playCards[]=$card;
            }
        }
		// Check each card to be played and make sure they are ALL in this player's hand
		$bIsInHand=(count($playCardsIds)==count(array_intersect($playCardsIds,$playerHandIds)))?true:false;
		if( !$bIsInHand )	throw new feException( "Cards are not in your hand" );
		
		// Figure out what type of play is happening:
		//-1 = Dog (This will actually keep playType at -1 so any type can be played, but not bomb)
		//	0 = Singles
		//	1 = Doubles
		//	2 = Triples
		//	3 = Full House
		//	4 = Consecutive Doubles
		//	5-14 = Run of 5 or more
		// 20+?= Bomb
		//
		// First check if it is a bomb play
		$playType=self::getGameStateValue( 'playType' );
		switch (count($playCardsIds)) {
			case 1:
				if ($playType>0) // If the current play type is not singles or not first play
					throw new feException( 'Must play a higher card of type: '.$this->play_type[$playType] );
                $playValue=$playCards[0]['type_arg']*10; // Normal cards value
				if ($playValue==10) { // Specials
					switch ($playCards[0]['type']) {
						case 1:	// Dog
							if ($playType!=-1)
								throw new feException( 'Dog can only be played as first card of a trick' );
							// playType is kept at -1 for next player to determine playType
							self::notifyAllPlayers( 'playCards', // Notify of Dog play
								clienttranslate('${player_name} plays ${specials_label}'), array(
									'i18n' => array( 'specials_label' ),
									'card_ids' => $playCardsIds,
									'player_id' => $player_id,
									'player_name' => self::getActivePlayerName(),
									'value' => $playCards[0]['type_arg'],
									'color' => $playCards[0]['type'],
									'specials_label' => $this->specials_label[ $playCards[0]['type'] ],
									'cards_order' =>$playCards[0]['cards_order'],
									'plays_order' =>$playCards[0]['plays_order']
							) );
							// self::setGameStateValue( 'lastPlayPlayer', $player_id );
							self::setGameStateValue( 'maxCardValue', -5 );
							$this->gamestate->nextState( 'playCards' ); // Next player
							return;
							// break;
						case 2:	// Mah Jong
							$playValue=10;
							break;
						case 3:	// Phoenix
							if ($maxCardValue==150) $playValue=145;	// Phoenix can't beat dragon
							elseif ($maxCardValue<10) $playValue=15;	// Played on Dog or on nothing
							else $playValue=$maxCardValue+5;				// 1/2 pt higher than last play
							break;
						case 4:	// Dragon
							$playValue=150;
							break;
						default:
							throw new feException( 'error type_arg:1, type:'+$playCards[0]['type'] );
					}
				}
				if ( $maxCardValue >= $playValue ) // Invalid move: play must be higher
					throw new feException( 'Must play a card that is higher than the highest play' );

                //set $playType after validation as later code needs it.
                //save $playValue/$playType for later plays
                $playType=0;
                self::setGameStateValue( 'maxCardValue', $playValue );
				self::setGameStateValue( 'playType', $playType );
                
                //update db and pass back changes to $playCards (card_order and plays_order) 
				$playCards = self::UpdateCardsInDatabase($player_id, $playCardsIds, $playCards);
				break;
            case 2:
                if ($playType>-1 && $playType != 1) // If the current play type is not first play/doubles
					throw new feException( 'Must play a higher card of type: '.$this->play_type[$playType] );
                
                $playType = 1;
                $playValue=$playCards[0]['type_arg']*10; // Normal cards value

                if ( $maxCardValue >= $playValue ) // Invalid move: play must be higher
					throw new feException( 'Must play a card that is higher than the highest play' );

                self::setGameStateValue( 'maxCardValue', $playValue );
				self::setGameStateValue( 'playType', $playType); //doubles

                //update cards in database
                $playCards = self::UpdateCardsInDatabase($player_id, $playCardsIds, $playCards);
				
                break;
            case 3:
                if ($playType>-1 && $playType != 2) // If the current play type is not first play/doubles
					throw new feException( 'Must play a higher card of type: '.$this->play_type[$playType] );
                
                $playType = 1;
                $playValue=$playCards[0]['type_arg']*10; // Normal cards value

                if ( $maxCardValue >= $playValue ) // Invalid move: play must be higher
					throw new feException( 'Must play a card that is higher than the highest play' );

                self::setGameStateValue( 'maxCardValue', $playValue );
				self::setGameStateValue( 'playType', $playType); //doubles

                //update cards in database
                $playCards = self::UpdateCardsInDatabase($player_id, $playCardsIds, $playCards);
				
                break;

            case 4:
                //4 cards could be: bomb, consecutive pairs
                //5 cards could be: full house, straight
                //6+ cards (even) could be: straight, consecutive pairs
                //7+ cards (odd) could be: straight
                if ($playType>-1 && $playType != 2) // If the current play type is not first play/doubles
					throw new feException( 'Must play a higher card of type: '.$this->play_type[$playType] );
                
                $playType = 1;
                $playValue=$playCards[0]['type_arg']*10; // Normal cards value

                if ( $maxCardValue >= $playValue ) // Invalid move: play must be higher
					throw new feException( 'Must play a card that is higher than the highest play' );

                self::setGameStateValue( 'maxCardValue', $playValue );
				self::setGameStateValue( 'playType', $playType); //doubles

                //update cards in database
                $playCards = self::UpdateCardsInDatabase($player_id, $playCardsIds, $playCards);
				
                break;
			default:
				throw new feException( 'Play type not yet implemented' );
		}
		
		// Checks are done! now we can play our card
		// Update database, change card_location from 'hand' to 'cardsontable'
		// Actual visual movement of card is done below in Notifications
		$this->cards->moveCards( $playCardsIds, 'cardsontable', $player_id );
		
		//**  NOTIFICATIONS  **  //  These sent to notif_playCards: tichu.js:318
		// self::notifyAllPlayers( $notification_type, $notification_log, $notification_args )
		// notification_type: String that defines type of your notification (and to trigger corresponding method)
		// notification_log: A string that defines what is to be displayed in the game log.
		// notification_args: The arguments of your notifications, as an associative array.
		// from tichu.js: this.playCardOnTable( notif.args.player_id, notif.args.color, notif.args.value, notif.args.card_ids, notif.args.cards_order, notif.args.plays_order );
		// Future also notify of Wish and Tichu calls and Bombs
        self::debug("PLAYCARDS notification start");
        self::debug("PLAYCARDS playtype [".$playType."]");


        //client call is very similar in each case, shared code between all play types,
        //but with slightly different text
        if ($playType==0) { // For Singles
            if ($playCards[0]['type_arg']>1) { // Not a Special card
                $displayText = clienttranslate('${player_name} plays ${value_displayed}');
                $displayValue = $this->values_label[ $playCards[0]['type_arg'] ];
                $i18n = array( 'value_displayed' ); //not needed now?
            }
            else{
                $displayText = clienttranslate('${player_name} plays ${value_displayed} (${playValue})');
                $displayValue = $this->specials_label[ $playCards[0]['type'] ];
                $i18n = array( 'specials_label' ); //check if this is needed
            }
        }
        else if ($playType == 1) //doubles
        {
            $displayText = clienttranslate('${player_name} plays a double ${value_displayed}');
            $displayValue = $this->values_label[ $playCards[0]['type_arg'] ];
            $i18n = array( 'value_displayed' ); //check if needed
        }
        else if ($playType == 2) //triples
        {
            $displayText = clienttranslate('${player_name} plays a triple ${value_displayed}');
            $displayValue = $this->values_label[ $playCards[0]['type_arg'] ];
            $i18n = array( 'value_displayed' ); //check if needed
        }

        self::notifyAllPlayers( 
            'playCards', // The notification to call tichu.js:306
            $displayText,
            array( // Notif.args to pass to JS
						'i18n' => array( 'value_displayed' ),
						'card_ids' => $playCardsIds,
						'player_id' => $player_id,
						'player_name' => self::getActivePlayerName(),
						'value' => $playCards[0]['type_arg'],
						'color' => $playCards[0]['type'],
                        'playValue' => $playValue,
						'value_displayed' => $displayValue,
						'cards_order' =>$playCards[0]['cards_order'],
						'plays_order'=>$playCards[0]['plays_order']
					) 
		);
                
        self::debug("PLAYCARDS card notifications finished");
		self::setGameStateValue( 'playType', $playType );		
		self::setGameStateValue( 'lastPlayPlayer', $player_id );
		$this->gamestate->nextState( 'playCards' ); // Next player
	}
    function UpdateCardsInDatabase($player_id, $playCardsIds, $playCards){
        $playerCardsOnTable = $this->getCardsInLocation( 'cardsontable', $player_id );
        $plays_order=0; // Find the highest plays_order for this player before playing the new cards
        if ($playerCardsOnTable) // Check if this player already has cards on table to get plays_order
            foreach ($playerCardsOnTable as $card) // Scan his played cards to get the current highest
                $plays_order=max($card['plays_order'],$plays_order);
        else $plays_order=0;
        $plays_order++; // Increment plays_order by 1, send with playing card
        $sql=''; // Set the plays_order one higher, and make each card played a higher cards_order
        for ( $cards_order=1; $cards_order <= count($playCardsIds); $cards_order++) {
            $card_id=$playCards[$cards_order-1]['id'];
            $playCards[$cards_order-1]['cards_order']=$cards_order; // This will have to be sorted by weight
            $playCards[$cards_order-1]['plays_order']=$plays_order; // What play # is this player on
            $sql ="UPDATE card SET card_cards_order='$cards_order',card_plays_order='$plays_order' ".
                "WHERE card_id = '$card_id';";
            if ($sql) self::DbQuery($sql);
        }
        return $playCards;
    }
	function giveLeft( $card_ids ) {
		self::checkAction( "giveLeft" );
		$player_id = self::getCurrentPlayerId();
		$this->cards->getCardsInLocation( 'cardsontable' );
		
	}
	function giveRight( $card_ids ) {
		self::checkAction( "giveRight" );
		$player_id = self::getCurrentPlayerId();
	}
	function giveCards( $card_ids ) { // Give some cards (before the hands begin)
        self::debug("giveCards");
		self::checkAction( "passCards" );

        //todo - pass the cards
        $player_id = self::getCurrentPlayerId();
        $this->gamestate->setPlayerNonMultiactive( $player_id, "passCards" );
        return;
		// !! Here we have to get CURRENT player (= player who send the request) and not
		//    active player, cause we are in a multiple active player state and the "active player"
		//    correspond to nothing.
		$player_id = self::getCurrentPlayerId();
		if( count( $card_ids ) != 3 )
			throw new feException( self::_("You must give exactly 3 cards") );
		// Check if these cards are in player hands
		$cards = $this->cards->getCards( $card_ids );
		if( count( $cards ) != 3 )
			throw new feException( self::_("Some of these cards don't exist") );
		foreach( $cards as $card ) { // Verify cards are in correct player's hand
			if( $card['location'] != 'hand' || $card['location_arg'] != $player_id )
				throw new feException( self::_("Some of these cards are not in your hand") ); }
		
		// To which player should I give these cards ?
		$player_to_give_cards = null;
		$player_to_direction = self::getPlayersToDirection();   // Note: current player is on the south
		//$handType = self::getGameStateValue( "currentHandType" );
		//if( $handType == 0 )
		//	$direction = 'W';
		//else if( $handType == 1 )
		//	$direction = 'N';
		//else if( $handType == 2 )
		//	$direction = 'E';
		foreach( $player_to_direction as $opponent_id => $opponent_direction ) {
			if( $opponent_direction == $direction )
				$player_to_give_cards = $opponent_id; }
		if( $player_to_give_cards === null )
			throw new feException( self::_("Error while determining to who give the cards") );
		// Allright, these cards can be given to this player
		// (note: we place the cards in some temporary location in order he can't see them before the hand starts)
		// This updates database, notifyPlayer below does visuals
		// Need to switch this to 3 moveCards, 1 to each player
		$this->cards->moveCards( $card_ids, "temporary", $player_to_give_cards );
		// Notify the player so we can make these cards disapear
		self::notifyPlayer( $player_id, "giveCards", "", array( "cards" => $card_ids ) );
		// Make this player unactive now
		// (and tell the machine state to use transtion "giveCards" if all players are now unactive
		$this->gamestate->setPlayerNonMultiactive( $player_id, "giveCards" );
	}
    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////
	/*  Here, you can create methods defines as "game state arguments" (see "args" property in states.inc.php).
	These methods are returning some additional informations that are specific to the current game state.  */


//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////
// function stNewHand() {}
// function stGiveCards() {}
// function stTakeCards() {}
// function stNewTrick() {}
// function stBeforePlayerTurn() {}
// function stPlayCards() {}
// function stNextPlayer() {}
// function stEndHand() {}
// function stGameEnd() {}
	/*  Here, you can create methods defines as "game state actions" (see "action" property in states.inc.php).
	The action method of state X is called everytime the current game state is set to X.  */
	function stNewHand() {
        self::debug("stNewHand");
		self::incStat( 1, "handNbr" );
		// Take back all cards (from any location => null) to deck
		$this->cards->moveAllCardsInLocation( null, "deck" );
		$this->cards->shuffle( 'deck' );
		
		// Deal 8 cards to each players
		// Create deck, shuffle it and give 8 initial cards
		$players = self::loadPlayersBasicInfos();
		foreach( $players as $player_id => $player ) {
			$cards = $this->cards->pickCards( 8, 'deck', $player_id );
			
			// Notify player about his cards
			self::notifyPlayer( $player_id, 'newHand', '', array( 'cards' => $cards ) );
		}
		self::setGameStateValue( 'alreadyFulfilledWish', 0 );
		$this->gamestate->nextState( "" );
	}
    function stPassCards() {
        self::debug("stPassCards");

        //must deal out the remaining 6 cards each, and notify players
        $players = self::loadPlayersBasicInfos();
        foreach( $players as $player_id => $player ) {
			$cards = $this->cards->pickCards( 6, 'deck', $player_id );
			
			// Notify player about his cards
			self::notifyPlayer( $player_id, 'addToHand', '', array( 'cards' => $cards ) );
		}

        $this->gamestate->setAllPlayersMultiactive();
	}
	function stGiveCards() {
        self::debug("stGiveCards");
        self::setGameStateValue( 'grandTichuPasses', 0);
        $this->gamestate->nextState( "cardsDealt" );
	}
	function stTakeCards() {
        self::debug("stTakeCards");
		// Take cards given by the other player
		$players = self::loadPlayersBasicInfos();
		foreach( $players as $player_id => $player ) {
			// Each player takes cards in the "temporary" location and place it in his hand
			$cards = $this->getCardsInLocation( "temporary", $player_id );
			$this->cards->moveAllCardsInLocation( "temporary", "hand", $player_id, $player_id );
			self::notifyPlayer( $player_id, "takeCards", "", array("cards" => $cards) );
		}
		// Find the player with the Mah Jong (1), they are starting player
		$mahJongCardOwner = self::getUniqueValueFromDb( "SELECT card_location_arg FROM card
			WHERE card_location='hand'	AND card_type='2' AND card_type_arg='1' " );
		if( $mahJongCardOwner !== null ) {
			$this->gamestate->changeActivePlayer( $mahJongCardOwner );
		} else throw new feException( self::_("Cant find Mah Jong (1)") );
		$this->gamestate->nextState( "startHand" );  // For now
	}
	function stNewTrick() {
        self::debug("stNewTrick");
		// New trick: active the player who wins the last trick, or the player who owns the Mah Jong card
		// $current_player = self::getCurrentPlayerId();
		// Then have the Send cards button, and a back button
		//self::setGameStateValue( 'currentHandType', 0 );
		// Set high card value
		self::setGameStateValue( 'maxCardValue', 0 );
		// Reset lastPlayPlayer
		self::setGameStateValue( 'lastPlayPlayer', 0 );
		// Play Type per hand, this can't change except bombs and off of dog, -1=not set, see line 259
		self::setGameStateInitialValue( 'playType', -1 );
		// Mark if the wish has been fulfilled during this hand
		// self::setGameStateInitialValue( 'alreadyFulfilledWish', 0 );
		// Count # of consecutive passes, if 3 then trick is over
		// self::setGameStateInitialValue( 'consecutivePassPlays', 0 );
		$this->gamestate->nextState('');
	}
    function stNextPlayerDeclareGrandTichu() { 
        self::debug("stNextPlayerDeclareGrandTichu");
        $this->activeNextPlayer();
		$this->gamestate->nextState( "" ); 
	}
	function stBeforePlayerTurn() { // This can also redirect to Bomb play maybe
        self::debug("stBeforePlayerTurn");
		// $x=false;
		// $dogNextPlayer = self::getGameStateValue( "dogNextPlayer" );
		// $current_player = self::getActivePlayerId();
		// for ($x=0;$x<6;$x++) { // Search for the next player with cards in hand
		// 	$current_player = self::getPlayerAfter($current_player);
		// 	$num_cards = $this->cards->countCardInLocation( 'hand', $current_player ); 
		// 	if ($dogNextPlayer) { // If a dog is played is this player skipped?
		// 		if ($current_player==$dogNextPlayer) {
		// 			self::setGameStateValue( "dogNextPlayer", 0 ); $dogNextPlayer=0; }// Dog has completed mission
		// 	} elseif ($num_cards>0) {
		// 		self::giveExtraTime( $current_player ); // Give extra time to next player
		// 		$this->gamestate->changeActivePlayer($current_player);
				$this->gamestate->nextState( "startTurn" ); 
		// 	}
		// }
		// if ($x==6) throw new feException( "Looped too many times searching for next player" );
	}
	function stEndTurn() {
        self::debug("stEndTurn");
		// Active next player OR end the trick and go to the next trick OR end the hand
		// Need to change this to: If it's your turn and you were the last to play then pass trick to player 
		//		and pass "nextTrick" => 30
		// Figure out next player that is holding cards
		$player_nos=array(1,2,3,4,1,2,3);
		$current_player = self::getCurrentPlayerId();
		$playersHands=array();
		if (self::getGameStateValue('maxCardValue')==-10) { $dog=1; self::getGameStateValue('maxCardValue', 0); } else $dog=0;

		// Get current player number in DB
		$current_player_no=self::getUniqueValueFromDb( "SELECT player_no FROM player WHERE player_id='$current_player'" );
		$resHand = $this->getCardsInLocation( 'hand' );
		$player_nos=array_slice($player_nos,$current_player_no-1,4);
		// var_dump($resHand);
		foreach($resHand as $card)
			$playersHands[$card['location_arg']]=$card;
		if (count($playersHands[$current_player])==0) { // Player goes out on this play
			if (self::getGameStateValue( 'firstOutPlayer' )==0) // If he is the first, record it
				self::setGameStateValue( 'firstOutPlayer', $current_player ); }
			
		// First check if him and his teammate are out
		$sql2="SELECT player_no, player_id, count(card_id) as num, player_team FROM player
				LEFT JOIN card ON player_id=card_location_arg
				WHERE card_location='hand'
				GROUP BY card_location_arg
				ORDER BY FIELD(`player`.`player_no`,".implode(',',$player_nos).")";
		$result = self::DbQuery( $sql2 );
		if (!$result) die($sql2);
		$nTeam=array();$nTeam[0]=$nTeam[1]=$n=$next_player=$dogNextPlayer=0;
		$playersStillIn=array();$dogSkip=0;
		while($arr1=mysql_fetch_assoc($result)) {
			if ($arr1['num']>0) $nTeam[$arr1['player_team']]++; // Count # players on each team with cards
			else $playersStillIn[]=$arr1;
			if (!$n) $team=$arr1['player_team']; // Get the current player's team #
			if ($n && $arr1['player_team']==$team) $dogSkip=1; // Get the current player's teammate
			if ($n && $arr1['num']>0 && $dogSkip && !$dogNextPlayer) $dogNextPlayer=$arr1['player_id'];
			if ($n && $arr1['num']>0 && $next_player==0) $next_player=$arr1['player_id']; // Get the next player's ID
		// echo $bStartFindDogNext?'true':'false';echo " n:$n no:".$arr1['player_no'];
		//  echo " $dog $dogTeam dogNextPlayer:$dogNextPlayer ";
		//  var_dump($arr1);
			$n++; } 
		if ($dog && !$dogNextPlayer)
			$dogNextPlayer=$current_player; // If north and west have no cards
		if ($dog && $dogNextPlayer)
			self::setGameStateValue( 'dogNextPlayer', $dogNextPlayer );
		if ($nTeam[0]==0 || $nTeam[1]==0) { // Check if one team is out
			// Figure out what kind of victory
			if ($nTeam[1]==2) 		self::setGameStateValue( 'OneTwoVictory', 0 );
			elseif ($nTeam[0]==2) 	self::setGameStateValue( 'OneTwoVictory', 1 );
			elseif (count($playersStillIn)==1) self::setGameStateValue('lastOutPlayer',$playersStillIn[0]['player_id']);
			else throw new feException( "Unknown condition (game.php:560)" );
			$this->gamestate->nextState( "endHand" );
		}

		$lastPlayPlayer = self::getGameStateValue( 'lastPlayPlayer' );
		$maxCardValue = self::getGameStateValue( 'maxCardValue' );
		// echo $bStartFindDogNext?'true':'false';
		//  echo " $dogTeam dogNextPlayer:$dogNextPlayer nextplayer:$next_player currentplayer:$current_player lastplayplayer:$lastPlayPlayer current_player_no:$current_player_no player_nos:";print_r($player_nos);
		if ($next_player==$lastPlayPlayer && $dog==0) { // Everyone in game passed, trick won
			$winTrickPlayer = $lastPlayPlayer;
			// $winTrickPlayer = self::getGameStateValue( 'lastPlayPlayer' );
			// Move all cards to "cardswon" of the given player
			if ($maxCardValue==150) { // Dragon wins trick, prompt whom to pass to
				$dragon_winner = self::activeNextPlayer(); // Set the next player as active (he won with dragon)
				$this->gamestate->nextState( "passDragonTrick" );
			} else {
				$cards_on_table = $this->getCardsInLocation( 'cardsontable' );
				$this->cards->moveAllCardsInLocation( 'cardsontable', 'cardswon', null, $winTrickPlayer );
				// ** Also need to change notif for dragon win trick
				// Note: we use 2 notifications here in order we can pause the display during the first notification
				//  before we move all cards to the winner (during the second)
				$players = self::loadPlayersBasicInfos();
				// This sends message and changes db for cardsontable to cardswon
				self::notifyAllPlayers(
					'trickWin', // Notification to call, tichu.js:307
					clienttranslate('${player_name} wins the trick'), // Notifaction message 
					array(
						'player_id' => $winTrickPlayer,
						'player_name' => $players[ $winTrickPlayer ]['player_name'],
					)
				);
				// This performs animation of moving cards to winner then destroying them
				self::notifyAllPlayers( 
					'giveAllCardsToPlayer','', // Notification to call, tichu.js:308 ; No text
					array(
						'player_id' => $winTrickPlayer,
						'cards_won' => $cards_on_table
					) 
				);
				// Active this player => he's the one who starts the next trick
				$this->gamestate->changeActivePlayer( $lastPlayPlayer );
				//// Need to change this to 
				if( $this->cards->countCardInLocation( 'hand' ) == 0 ) // End of the hand
					$this->gamestate->nextState( "endHand" );
				else $this->gamestate->nextState( "nextTrick" ); // End of the trick
			}
		} else { // Next player
			$x=false;
			$dogNextPlayer = self::getGameStateValue( "dogNextPlayer" );
			$current_player = self::getActivePlayerId();
			// $next_player = self::getPlayerAfter($current_player);
			$next_player = self::activeNextPlayer();
			// $this->gamestate->nextState( "skipPlayer" );
			// $this->gamestate->nextState( "nextPlayer" ); 
			$num_cards = $this->cards->countCardInLocation( 'hand', $next_player ); 
			if ($dogNextPlayer) {// If a dog is played is this player skipped?
				if ($next_player==$dogNextPlayer) {
					self::setGameStateValue( "dogNextPlayer", 0 ); $dogNextPlayer=0; 
					$this->gamestate->nextState( "nextPlayer" ); }// Dog has completed mission
				else $this->gamestate->nextState( "skipPlayer" ); // Dog needs to skip this player
			} elseif ($num_cards==0) {
				$this->gamestate->nextState( "skipPlayer" ); // Skip player if he has no cards
			} else {
				self::giveExtraTime( $next_player ); // Give extra time to next player
				$this->gamestate->nextState( "nextPlayer" ); }
         // $next_player = self::activeNextPlayer();
			// self::giveExtraTime( $next_player ); // Give extra time to next player
			// $this->gamestate->changeActivePlayer( $next_player );
			// $this->gamestate->nextState( 'nextPlayer' );
		}
	}
	function stSkipPlayerTurn() {
        self::debug("stSkipPlayerTurn");
		$this->gamestate->nextState('');
	}
	function stEndHand() { // Count and score points, then end the game or go to the next hand.
        self::debug("stEndHand");
		$players = self::loadPlayersBasicInfos();
		
		// Get all point cards: 5,10,K(13),Phoenix(3,type:1),Dragon(15)
		$player_with_queen_of_spades = null;
		$player_to_hearts = array();
		$player_to_points = array();
		foreach( $players as $player_id => $player ) {
			$player_to_hearts[ $player_id ] = 0;
			$player_to_points[ $player_id ] = 0;
		}   
		
		// $cards = $this->getCardsInLocation( "cardswon" );
		// foreach( $cards as $card ) {
		// 	$player_id = $card['location_arg'];
			
		// 	if( $card['type'] == 2 && $card['type_arg'] == 12 ) {   // Note: 1 = spade && 12 = queen
		// 		// Queen of club => 13 points
		// 		$player_to_points[ $player_id ] += 13;
		// 		$player_with_queen_of_spades = $player_id;
		// 	} else if( $card['type'] == 3 ) {  // Note: 2 = heart
		// 		$player_to_hearts[ $player_id ] ++;                    
		// 		$player_to_points[ $player_id ] ++; 
		// 	}
		// }
		
		// // If someone gets all hearts and the queen of club => 26 points for eveyone
		// $nbr_nonzero_score = 0;
		// foreach( $player_to_points as $player_id => $points ) {
		// 	if( $points != 0 )
		// 		$nbr_nonzero_score ++;
		// }
		
		// $bOnePlayerGetsAll = ( $nbr_nonzero_score == 1 );
		
		// if( $bOnePlayerGetsAll ) {
		// // Only 1 player score points during this hand
		// // => he score 0 and everyone scores -26
		// foreach( $player_to_hearts as $player_id => $points ) {
		// 	if( $points != 0 ) {
		// 		$player_to_points[ $player_id ] = 0;
				
		// 		// Notify it!
		// 		self::notifyAllPlayers( "onePlayerGetsAll", 
		// 			clienttranslate( '${player_name} gets all hearts and the Queen of Spades: everyone else loose 26 points!' ), 
		// 			array(
		// 				'player_id' => $player_id,
		// 				'player_name' => $players[ $player_id ]['player_name'] )
		// 		);
		// 		self::incStat( 1, "getAllPointCards", $player_id );
		// 	} else
		// 		$player_to_points[ $player_id ] = 26;
		// 	}
		// }
		
		// // Apply scores to player
		// foreach( $player_to_points as $player_id => $points ) {
		// 	if( $points != 0 ) {
		// 		$sql = "UPDATE player SET player_score=player_score+$points
		// 		      WHERE player_id='$player_id' " ;
		// 		self::DbQuery( $sql );
				
		// 		// Now, notify about the point lost.                
		// 		if( ! $bOnePlayerGetsAll ) { // Note: if one player gets all, we already notify everyone so there's no need to send additional notifications
		// 			$heart_number = $player_to_hearts[ $player_id ];
		// 			if( $player_id == $player_with_queen_of_spades ) {
		// 				self::notifyAllPlayers( "points", 
		// 					clienttranslate( '${player_name} gets ${nbr} hearts and the Queen of Spades and looses ${points} points' ), array(
		// 						'player_id' => $player_id,
		// 						'player_name' => $players[ $player_id ]['player_name'],
		// 						'nbr' => $heart_number,
		// 						'points' => $points )
		// 				);
		// 			} else {
		// 				self::notifyAllPlayers( "points", 
		// 					clienttranslate( '${player_name} gets ${nbr} hearts and looses ${nbr} points' ), 
		// 					array(
		// 						'player_id' => $player_id,
		// 						'player_name' => $players[ $player_id ]['player_name'],
		// 						'nbr' => $heart_number )
		// 				);
		// 			}
		// 		}
		// 	} else {
		// 		// No point lost (just notify)
		// 		self::notifyAllPlayers( "points",clienttranslate('${player_name} did not get any hearts' ), array(
		// 			'player_id' => $player_id,
		// 			'player_name' => $players[ $player_id ]['player_name']
		// 		) );
		// 			self::incStat( 1, "getNoPointCards", $player_id );
		// 	}
		// }
		
		// $newScores = self::getCollectionFromDb( "SELECT player_id, player_score FROM player", true );
		// self::notifyAllPlayers( "newScores", '', array( 'newScores' => $newScores ) );
		
		// //////////// Display table window with results /////////////////
		// $table = array();
		
		// // Header line
		// $firstRow = array( '' );
		// foreach( $players as $player_id => $player ) {
		// 	$firstRow[] = array(
		// 		'str' => '${player_name}',
		// 		'args' => array( 'player_name' => $player['player_name'] ),
		// 		'type' => 'header'); }
		// $table[] = $firstRow;
		
		// // Hearts
		// $newRow = array( array( 'str' => clienttranslate('Hearts'), 'args' => array() ) );
		// foreach( $player_to_hearts as $player_id => $hearts ) {
		// 	$newRow[] = $hearts;
		// 	if( $hearts > 0 )
		// 		self::incStat( $hearts, "getHearts", $player_id );
		// }
		// $table[] = $newRow;
		
		// // Queen of spades
		// $newRow = array( array( 'str' => clienttranslate('Queen of Spades'), 'args' => array() ) );
		// foreach( $player_to_hearts as $player_id => $hearts ) {
		// 	if( $player_id == $player_with_queen_of_spades ) {
		// 		$newRow[] = '1';
		// 		self::incStat( 1, "getQueenOfSpade", $player_id );
		// 	} else
		// 		$newRow[] = '0';
		// }
		// $table[] = $newRow;
		
		// // Points
		// $newRow = array( array( 'str' => clienttranslate('Points'), 'args' => array() ) );
		// foreach( $player_to_points as $player_id => $points ) {
		// 	$newRow[] = $points;
		// }
		// $table[] = $newRow;
		
		// $this->notifyAllPlayers( "tableWindow", '', array(
		// 	"id" => 'finalScoring',
		// 	"title" => clienttranslate("Result of this hand"),
		// 	"table" => $table
		// ) ); 
		
		///// Test if this is the end of the game
		$gameLength = self::getGameStateValue( "gameLength" );
		foreach( $newScores as $player_id => $score ) {
			if( $score >= $gameLength ) {
				// Trigger the end of the game !
				$this->gamestate->nextState( "endGame" );
				return ;
			}
		}
		// Otherwise... new hand !
		$this->gamestate->nextState( "nextHand" );
	}
}