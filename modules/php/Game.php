<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * SuperTeam implementation : © <Shayan Aminnezhad> <shayanaminnjad.sa@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\HexLib\HexLib;
use Bga\Games\SuperTeam\GameDice\GameDice;


use BgaUserException;
use Exception;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");

enum GameActions : int
{
    case Unknown = 0;
    case Move = 1;
}


class PassForwardCommand
{
    private Board $board;
    private $currentPlayer;
    private $opponentPlayer;

    public function __construct(Board $board, $currentPlayer, $oppoenentPlayer)
    {
        $this->board = $board;
        $this->currentPlayer = $currentPlayer;
        $this->opponentPlayer = $oppoenentPlayer;
    }

    public function canBePlayed() : bool
    {
        $ballHex = $this->board->getBallHex();
        $playerPiece = null;
        
        if ($this->board->tryGetPlayerPieceInHex($ballHex, $playerPiece))
        {
            if ($playerPiece->getOwner() === $this->currentPlayer)
            {
                return count($this->board->getPlayerPiecesInForwardColumns($ballHex->getColumn(), $this->currentPlayer)) > 0;
            }
        }

        return false;
    }

    public function getCommandArgs() : array
    {
        //TODO: In general a pass can be onto any space that is not occupied by your opponent! however this card require us to pass in a certain way.

        $ballHex = $this->board->getBallHex();
        
        $playerPiecesInForwardColumns = $this->board->getPlayerPiecesInForwardColumns($ballHex->getColumn(), $this->currentPlayer);
        $piecesIds = [];
        for ($i = 0; $i < count($playerPiecesInForwardColumns); $i++)
        {
            $piecesIds[] = $playerPiecesInForwardColumns[$i]->getId();
        }

        return ["possibleTargets" => $piecesIds];
    }

    public function performAct(array $actData) : void
    {
        $passTarget = $actData['target'];
        $spentMorale = $actData['spentMorale'];

        //TOOD: This should be inside validation function!
        if (intval($spentMorale) > 3)
        {
            throw new \BgaUserException("You cannot spend more than 3 morale on an action!");
        }

        //TODO: target can't be current ball location!

        $targetHex = new HexID($passTarget['column'], $passTarget['row']);
        $ballHex = $this->board->getBallHex();
        //TODO: This logic for pass must be encapsulated inside another class, it doesn't relate to here.
        $passDistance = HexLib::getOffsetDistance($ballHex, $targetHex);
        
        $opponentPieces = $this->board->getPlayerPiecesByOwner($this->opponentPlayer);
        $opponentPiecesInPath = HexLib::getHexesInPath($ballHex, $targetHex, 1, $opponentPieces);
        
        $powerNeeded = ceil($passDistance * 0.5) + count($opponentPiecesInPath);

        $greenRoll = GameDice::greenDie()->roll();
        $whiteDieRes = [];

        //TODO: get coach markers for pass action.
        $coachMarkerCount = 1;
        $whiteRollSum = 0;
        for ($i = 0; $i < $coachMarkerCount; $i++)
        {
            $currentWhiteRollRes = GameDice::whiteDie()->roll();
            $whiteRollSum += $currentWhiteRollRes;
            $whiteDieRes[] = $currentWhiteRollRes;
        }

        $totalPlayerPower = $whiteRollSum + $greenRoll + $spentMorale;

        $finalBallLocation = null;

        if ($totalPlayerPower >= $powerNeeded)
        {
            $finalBallLocation = $targetHex;
        }
        else
        {
            if (count ($opponentPiecesInPath) > 0)
            {
                $nearestOpponent = null;
                $nearestOpponentDistance = 1000; //1000 is way beyond the furthest distance we can have.

                //TODO: asked amir about this, if two opponent pieces have the same distance from the ball, then how should we determine which one will get the ball?
                //right now I only find the closest one, and don't solve the above edge case.
                for ($i = 0; $i < count ($opponentPiecesInPath); $i++)
                {
                    $currentOpponentDistance = HexLib::getOffsetDistance($ballHex, $opponentPiecesInPath[$i]);

                    if ($currentOpponentDistance < $nearestOpponentDistance)
                    {
                        $nearestOpponent = $opponentPiecesInPath[$i];
                    }
                }

                $finalBallLocation = $nearestOpponent;
            }
            else 
            {
                $hexesInLine = HexLib::getHexesInLine($ballHex, $targetHex);
                $hexInMiddle = ceil((count($hexesInLine) - 1) / 2.0);

                $finalBallLocation = $hexesInLine[$hexInMiddle];
            }
        }

        DBAdapter::getInstance()->DbQuery("UPDATE ball SET hex_column= {$finalBallLocation->getColumn()}, hex_row= {$finalBallLocation->getRow()}");

        //TODO: notify all about all these changes.
    }
}

class Game extends \Table
{
    /**
     * Your global variables labels:
     *
     * Here, you can assign labels to global variables you are using for this game. You can use any number of global
     * variables with IDs between 10 and 99. If your game has options (variants), you also have to associate here a
     * label to the corresponding ID in `gameoptions.inc.php`.
     *
     * NOTE: afterward, you can get/set the global variables with `getGameStateValue`, `setGameStateInitialValue` or
     * `setGameStateValue` functions.
     */
    public function __construct()
    {
        parent::__construct();

        BoardInfo::init();
        DBAdapter::getInstance()->init(
     fn($sql) => $this->getObjectListFromDB($sql),
      fn($sql)=> $this->getObjectFromDB($sql),
        fn($sql) => $this->DbQuery($sql));

        $this->initGameStateLabels([
            "game_half" => 10,
            "user_in_left_id" => 11,
            "user_in_right_id" => 12,
            "selected_piece" => 13,
        ]);        

        /* example of notification decorator.
        // automatically complete notification args when needed
        $this->notify->addDecorator(function(string $message, array $args) {
            if (isset($args['player_id']) && !isset($args['player_name']) && str_contains($message, '${player_name}')) {
                $args['player_name'] = $this->getPlayerNameById($args['player_id']);
            }
        
            if (isset($args['card_id']) && !isset($args['card_name']) && str_contains($message, '${card_name}')) {
                $args['card_name'] = self::$CARD_TYPES[$args['card_id']]['card_name'];
                $args['i18n'][] = ['card_name'];
            }
            
            return $args;
        });*/
    }

    /**
     * Player action, example content.
     *
     * In this scenario, each time a player plays a card, this method will be called. This method is called directly
     * by the action trigger on the front side with `bgaPerformAction`.
     *
     * @throws BgaUserException
     */
    public function actPlayCard(int $card_id): void
    {
        /*
        // Retrieve the active player ID.
        //$player_id = (int)$this->getActivePlayerId();

        // check input values
        $args = $this->argPlayerTurn();
        $playableCardsIds = $args['playableCardsIds'];
        if (!in_array($card_id, $playableCardsIds)) {
            throw new \BgaUserException('Invalid card choice');
        }

        // Add your game logic to play a card here.
        $card_name = self::$CARD_TYPES[$card_id]['card_name'];

        // Notify all players about the card played.
        $this->notify->all("cardPlayed", clienttranslate('${player_name} plays ${card_name}'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
            "card_name" => $card_name, // remove this line if you uncomment notification decorator
            "card_id" => $card_id,
            "i18n" => ['card_name'], // remove this line if you uncomment notification decorator
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("playCard");*/
    }

    public function actPass(): void
    {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Notify all players about the choice to pass.
        $this->notify->all("pass", clienttranslate('${player_name} passes'), [
            "player_id" => $player_id,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
        ]);

        // at the end of the action, move to the next state
        $this->gamestate->nextState("pass");
    }

    //TODO: I can have a single entry to player actions, and a dispatcher dispatches each action to it's proper handler.
    public function actPassForward(): void
    {
        $activePlayerID = $this->getActivePlayerId();
        $Command = new PassForwardCommand($this->getBoard(), $activePlayerID , $this->getInactivePlayerId());

        if ($Command->canBePlayed())
        {
            $this->notifyPlayer($activePlayerID, "actionArgs", "", $Command->getCommandArgs());
        }
        else
        {
            throw new Exception("Pass forward can't be played!");
        }
    }

    public function argPlayerPieceSetup(): array
    {
        //get pieces that can be placed.
        $board = $this->getBoard();
        $playerPieces = $board->getPlayerPiecesByOwner(intval($this->getActivePlayerId()));
        
        $availablePieces = array_filter($playerPieces, function($piece)
        {
            return !$piece->hasValidPosition();
        });
        
        usort($availablePieces, function ($a, $b)
        {
            return $a->getId() <=> $b->getId();
        });
        
        $pieceToPlaceId = 0;
        if (count($availablePieces) > 0)
        {
            $pieceToPlaceId = $availablePieces[0]->getId();
        }
            
        //get available positions
        $playerSide = $this->getPlayerSide(intval($this->getActivePlayerId()));
        $IsFirstPlayer = $this->isCurrentPlayerFirstPlayer();

        $possibleLocations = [];
        //Special case where center is not set by the first player.
        if ($IsFirstPlayer && count($availablePieces) == 1 && !$board->hasPlayerInHex(BoardInfo::$CENTER_POSITION))
        {
            $possibleLocations = [BoardInfo::$CENTER_POSITION->convertToArray()];
        }
        else
        {
            $playerStartingPositions = $playerSide === PlayerSide::Left ? BoardInfo::$LEFT_PLAYER_STARTING_POSITIONS : BoardInfo::$RIGHT_PLAYER_STARTING_POSITIONS;

            if ($IsFirstPlayer) 
            {
                $playerStartingPositions[] = BoardInfo::$CENTER_POSITION;
            }

            for ($i = 0; $i < count($playerStartingPositions); $i++) 
            {
                if ($board->hasPlayerInHex($playerStartingPositions[$i]) == false) 
                {
                    $possibleLocations[] = $playerStartingPositions[$i]->convertToArray();
                }
            }
        }

        return [
            "availableStartingPositions" => $possibleLocations,
            "pieceToPlace"=> $pieceToPlaceId,
        ];
    }

    public function actPlacedPiece(int $placedPiece, int $hexColumn, int $hexRow): void
    {
        $playerPieceSetupArgs = $this->argPlayerPieceSetup();

        $availablePositions = $playerPieceSetupArgs["availableStartingPositions"];
        $pieceToPlace = $playerPieceSetupArgs["pieceToPlace"];

        $isValidPosition = false;
        for ($i = 0; $i < count($availablePositions); $i++ )
        {
            $currentPosition= $availablePositions[$i];

            if ($currentPosition['hex_column'] === $hexColumn && $currentPosition['hex_row'] === $hexRow)
            {
                $isValidPosition = true;
                break;
            }
        }

        if (!$isValidPosition) 
        {
            throw new BgaUserException("Invalid hex position for placing a piece! (" . $hexColumn . ", " . $hexRow . ")");
        }

        if ($placedPiece !== intval($pieceToPlace))
        {
            throw new BgaUserException("peice placed is not valid! piece to be placed id was " .  $pieceToPlace . ", received " . $placedPiece);
        }

        $this->DbQuery("UPDATE `player_pieces` SET `hex_column` = $hexColumn, `hex_row` = $hexRow WHERE `id` = $placedPiece ");

        $playerId = (int)$this->getActivePlayerId();

        $this->notify->all("placedPiece", clienttranslate('${player_name} places a piece'), [
            "player_id" => $playerId,
            "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
            "placed_piece" => $placedPiece,
            "hex_column" => $hexColumn,
            "hex_row" => $hexRow,
        ]);

        if (!$this->isPlayerSetupComplete($playerId))
        {
            $this->gamestate->nextState("playerPieceSetup");
        }
        else
        {
            $this->gamestate->nextState("setupComplete");
        }
    }

    public function actActionSelected(int $actionId) : void
    {
        // TODO: a list of handlers is more proper, however it is ok right now.

        switch($actionId)
        {
            case GameActions::Move->value:
                $this->gamestate->nextState("moveAction");
                break;
        }

        //TODO: Probably I get the board here, so updateAttacker and defender can work on it.
        //$this->updateAttackerAndDefender();
    }

    public function argActionSelection() : array
    {
        $this->setGameStateValue('selected_piece', -1);

        return ['possibleActions' => [
            ['name' => 'move', 'id' => GameActions::Move]
            ]];
    }

    public function argPieceSelection() : array
    {
        $board = $this->getBoard();

        $activePlayerPieces = $board->getPlayerPiecesByOwner(intval($this->getActivePlayerId()));

        $pieceIDs = [];

        for ($i = 0; $i < count($activePlayerPieces); $i++)
        {
            if( $activePlayerPieces[$i]->getRole() != PlayerRole::GoalKeeper->value)
            {
                $pieceIDs[] = $activePlayerPieces[$i]->getId();
            }
        }

        return ['selectablePieces' => $pieceIDs];
    }

    public function actPieceSelected(int $pieceId) : void
    {
        $selectablePieces = $this->argPieceSelection();

        if (!in_array($pieceId, $selectablePieces['selectablePieces'], true))
        {
            throw new BgaUserException("Piece Id {$pieceId} is not selectable.");
        }

        $this->setGameStateValue('selected_piece', $pieceId);

        $this->gamestate->nextState("moveAction");
    }

    public function argMoveAction() : array
    {
        $selectedPiece = intval($this->getGameStateValue('selected_piece'));

        assert($selectedPiece != -1, "move action must have a valid piece selected, before it gets called!");

        $moveableHexes = $this->getBoard()->getMoveableHexesByPiece($selectedPiece);

        return ["moveableHexes" => $moveableHexes,
                "selectedPiece" => $selectedPiece];
    }

    public function actPieceMoved(int $hexColumn, int $hexRow) : void
    {

    }

    //DEBUG ACTIONS
    public function actDebugDefaultPlacePieces() : void
    {
        $playerId = (int)$this->getActivePlayerId();

        while(!$this->isPlayerSetupComplete($playerId))
        {

            $playerPieceSetupArgs = $this->argPlayerPieceSetup();

            $availablePositions = $playerPieceSetupArgs["availableStartingPositions"];
            $pieceToPlace = $playerPieceSetupArgs["pieceToPlace"];

            $firstAvailablePos = $availablePositions[0];
            $hexColumn = $firstAvailablePos['hex_column'];
            $hexRow = $firstAvailablePos['hex_row'];

            $this->DbQuery("UPDATE `player_pieces` SET `hex_column` = $hexColumn, `hex_row` = $hexRow WHERE `id` = $pieceToPlace ");


            $this->notify->all("placedPiece", clienttranslate('${player_name} places a piece'), [
                "player_id" => $playerId,
                "player_name" => $this->getActivePlayerName(), // remove this line if you uncomment notification decorator
                "placed_piece" => $pieceToPlace,
                "hex_column" => $hexColumn,
                "hex_row" => $hexRow,
            ]);
        }
    
        $this->gamestate->nextState("setupComplete");
    }

    //=======================================

    public function stSetupComplete() : void
    {
        $previousPlayerId = (int)$this->getActivePlayerId();
        $this->giveExtraTime($previousPlayerId);
               
        $playerId = intval($this->activeNextPlayer());

        if (!$this->isPlayerSetupComplete($playerId)) 
        {
            $this->gamestate->nextState("playerPieceSetup");
        } 
        else 
        {
            $this->gamestate->nextState("playerTurn");
        }
    }

    public function stBeforePlayerTurn() : void
    {
        //TODO: fresh all exhusted players

        //TODO: restart card played counter

        $this->gamestate->nextState("");
    }

    /**
     * Compute and return the current game progression.
     *
     * The number returned must be an integer between 0 and 100.
     *
     * This method is called each time we are in a game state with the "updateGameProgression" property set to true.
     *
     * @return int
     * @see ./states.inc.php
     */
    public function getGameProgression()
    {
        // TODO: compute and return the game progression

        return 0;
    }

    /**
     * Game state action, example content.
     *
     * The action method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    public function stNextPlayer(): void {
        // Retrieve the active player ID.
        $player_id = (int)$this->getActivePlayerId();

        // Give some extra time to the active player when he completed an action
        $this->giveExtraTime($player_id);
        
        $this->activeNextPlayer();

        // Go to another gamestate
        // Here, we would detect if the game is over, and in this case use "endGame" transition instead 
        $this->gamestate->nextState("nextPlayer");
    }

    /**
     * Migrate database.
     *
     * You don't have to care about this until your game has been published on BGA. Once your game is on BGA, this
     * method is called everytime the system detects a game running with your old database scheme. In this case, if you
     * change your database scheme, you just have to apply the needed changes in order to update the game database and
     * allow the to continue to run with your new version.
     *
     * @param int $from_version
     * @return void
     */
    public function upgradeTableDb($from_version)
    {
//       if ($from_version <= 1404301345)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
//
//       if ($from_version <= 1405061421)
//       {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            $this->applyDbUpgradeToAllDB( $sql );
//       }
    }

    /*
     * Gather all information about current game situation (visible by the current player).
     *
     * The method is called each time the game interface is displayed to a player, i.e.:
     *
     * - when the game starts
     * - when a player refreshes the game page (F5)
     */
    protected function getAllDatas(): array
    {
        $result = [];

        // WARNING: We must only return information visible by the current player.
        $current_player_id = (int) $this->getCurrentPlayerId();

        // Get information about players.
        // NOTE: you can retrieve some extra field you added for "player" table in `dbmodel.sql` if you need it.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `player_score` `score` FROM `player`"
        );

        $result["player_pieces"] = $this->getObjectListFromDB("SELECT * FROM `player_pieces`");
        $result["ball"] = $this->getObjectFromDB("SELECT * FROM `ball`");

        return $result;
    }

    /**
     * Returns the game name.
     *
     * IMPORTANT: Please do not modify.
     */
    protected function getGameName()
    {
        return "superteam";
    }

    /**
     * This method is called only once, when a new game is launched. In this method, you must setup the game
     *  according to the game rules, so that the game is ready to be played.
     */
    protected function setupNewGame($players, $options = [])
    {
        $default_colors = ['FC5414', '00A3E5'];

        foreach ($players as $player_id => $player) {
            // Now you can access both $player_id and $player array
            $query_values[] = vsprintf("('%s', '%s', '%s', '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                $player["player_canal"],
                addslashes($player["player_name"]),
                addslashes($player["player_avatar"]),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reloadPlayersBasicInfos();

        // Init game statistics.
        //
        // NOTE: statistics used in this file must be defined in your `stats.inc.php` file.
        
        // Dummy content.
        // $this->initStat("table", "table_teststat1", 0);
        // $this->initStat("player", "player_teststat1", 0);
        
        $this->initGameState($players);

        $this->initBoard($players);

        $this->activeNextPlayer();
    }

    /**
     * This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
     * You can do whatever you want in order to make sure the turn of this player ends appropriately
     * (ex: pass).
     *
     * Important: your zombie code will be called when the player leaves the game. This action is triggered
     * from the main site and propagated to the gameserver from a server, not from a browser.
     * As a consequence, there is no current player associated to this action. In your zombieTurn function,
     * you must _never_ use `getCurrentPlayerId()` or `getCurrentPlayerName()`, otherwise it will fail with a
     * "Not logged" error message.
     *
     * @param array{ type: string, name: string } $state
     * @param int $active_player
     * @return void
     * @throws feException if the zombie mode is not supported at this game state.
     */
    protected function zombieTurn(array $state, int $active_player): void
    {
        $state_name = $state["name"];

        if ($state["type"] === "activeplayer") {
            switch ($state_name) {
                default:
                {
                    $this->gamestate->nextState("zombiePass");
                    break;
                }
            }

            return;
        }

        // Make sure player is in a non-blocking status for role turn.
        if ($state["type"] === "multipleactiveplayer") {
            $this->gamestate->setPlayerNonMultiactive($active_player, '');
            return;
        }

        throw new \feException("Zombie mode not supported at this game state: \"{$state_name}\".");
    }

    private function initGameState(array $players): void
    {
        list ($orangePlayer, $bluePlayer) = array_keys($players);

        $this->setGameStateInitialValue('selected_piece', -1);
        $this->setGameStateInitialValue('game_half', 1);
        $this->setGameStateInitialValue('user_in_left_id', $orangePlayer);
        $this->setGameStateInitialValue('user_in_right_id', $bluePlayer);
    }

    private function initBoard(array $players):void
    {
        $centerPos = BoardInfo::$CENTER_POSITION;
        $this->DbQuery("INSERT INTO ball (hex_column, hex_row) VALUES ({$centerPos->getColumn()},{$centerPos->getRow()})");

        list ($orangePlayer, $bluePlayer) = array_keys($players);

        $this->initAPlayerPiece($orangePlayer);
        $this->initAPlayerPiece($bluePlayer);
    }

    private function initAPlayerPiece(int $playerId) : void
    {
        $playerSide = $this->getPlayerSide($playerId);
        $goaliePos = $playerSide === PlayerSide::Left ? BoardInfo::$LEFT_PLAYER_GOALIE_POSITION : BoardInfo::$RIGHT_PLAYER_GOALIE_POSITION;

        $sqlQuery = "INSERT INTO player_pieces (owner_id, piece_role, is_fresh, hex_column, hex_row) VALUES ";

        $sqlValues[] = "($playerId," . PlayerRole::GoalKeeper->value . ", 1," . $goaliePos->getColumn() . ", " . $goaliePos->getRow() .")";
        $sqlValues[] = "($playerId," . PlayerRole::Captain->value . ", 1, -1, -1)";

        for ($i = 3; $i <= 6; $i++) 
        {
            $sqlValues[] = "($playerId, " . PlayerRole::Regular->value . ", 1, -1, -1)";
        }

        $sqlQuery .= implode(',', $sqlValues);

        $this->DbQuery($sqlQuery);
    }

    private function getPlayerSide(int $userId) : PlayerSide
    {
        $userInLeftID = intval($this->getGameStateValue('user_in_left_id'));

        if ($userInLeftID === $userId) 
        {
            return PlayerSide::Left;
        } 
        else 
        {
            assert(intval($this->getGameStateValue('user_in_right_id')) === $userId, 'Player side not found! A player must always have a side!');

            return PlayerSide::Right;
        }
    }

    private function getInactivePlayerId() : int
    {
        $userInLeftID = intval($this->getGameStateValue('user_in_left_id'));

        if ($userInLeftID == $this->getActivePlayerId())
        {
            return intval($this->getGameStateValue('user_in_right_id'));
        }
        else
        {
            return $userInLeftID;
        }
    }

    private function getBoard(): Board
    {
        $query = PlayerPiecesDBManager::getPiecesDataQuery();
        $piecesData = $this->getObjectListFromDB($query, false);
        $ballPosition = $this->getObjectFromDB("SELECT hex_column, hex_row FROM `ball`");
        $ballHex = new HexID(intval($ballPosition['hex_column']),intval( $ballPosition['hex_row']));

        return new Board($piecesData, $ballHex);
    }

    private function isCurrentPlayerFirstPlayer(): bool
    {
        $firstPlayerId = $this->getUniqueValueFromDB("SELECT `player_id` FROM `player` WHERE `player_no` = 1");

        return $firstPlayerId === $this->getActivePlayerId();
    }

    private function isPlayerSetupComplete(int $playerId) : bool
    {
        $isComplete = true;

        $playerPieces = $this->getBoard()->getPlayerPiecesByOwner($playerId);

        for ($i = 0; $i < count($playerPieces); $i++)
        {
            if (!$playerPieces[$i]->hasValidPosition())
            {
                $isComplete = false;
                break;
            }
        }

        return $isComplete;
    }

    private function updateAttackerAndDefender() : void
    {
        //Get the hex with the ball
        //If the hex has a player, then the owner of that player is the attacker.
        //otherwise do nothing.
    }
}
