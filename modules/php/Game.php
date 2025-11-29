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
use Bga\Games\SuperTeam\Math\Math;


use BgaUserException;
use Exception;

require_once(APP_GAMEMODULE_PATH . "module/table/table.game.php");
require_once(__DIR__ . '/StateMachineConstants.inc.php');

enum GameActions : int
{
    case Unknown = 0;
    case Move = 1;
    case FinishMove = 2;
    case PassBall = 3;
    case Cancel = 4;
    case Confirm = 5;
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

    private const MAX_MORALE_PER_ACTION = 3;

    public function __construct()
    {
        parent::__construct();

        BoardInfo::init();
        DBAdapter::getInstance()->init(
     fn($sql) => $this->getObjectListFromDB($sql),
      fn($sql)=> $this->getObjectFromDB($sql),
      fn($sql)=>$this->getUniqueValueFromDB($sql),
        fn($sql) => $this->DbQuery($sql));

        $this->initGameStateLabels([
            "game_half" => 10,
            "user_in_left_id" => 11,
            "user_in_right_id" => 12,
            "selected_piece" => 13,
            "number_of_moves" => 14,
            "required_power" => 15,
            "assigned_morale" => 16
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
            $this->gamestate->nextState(TR_PLAYER_PIECE_SETUP);
        }
        else
        {
            $this->gamestate->nextState(TR_SETUP_COMPLETE);
        }
    }

    public function actActionSelected(int $actionId) : void
    {
        // TODO: a list of handlers is more proper, however it is ok right now.

        switch($actionId)
        {
            case GameActions::Move->value:
                $this->gamestate->nextState(TR_MOVE_ACTION);
                break;
            case GameActions::FinishMove->value:
                $this->FinishedMove();
                break;
            case GameActions::PassBall->value:
                $this->gamestate->nextState(TR_PASS_BALL_ACTION);
                break;
            case GameActions::Cancel->value:
                $this->gamestate->nextState(TR_CANCEL);
                break;
        }

        //TODO: Probably I get the board here, so updateAttacker and defender can work on it.
        //$this->updateAttackerAndDefender();
    }

    public function argActionSelection() : array
    {
        //TODO: each of these actions must be an object, with predicate, and name and id.

        $possibleActions = [];

        $pieceSelectableForMove = $this->argPieceSelection();

        if(count($pieceSelectableForMove['selectablePieces']) > 0 || count($pieceSelectableForMove['selectableExhaustedPieces']) > 0)
        {
            $possibleActions[] = ['name' => 'move', 'id' => GameActions::Move];
        }

        $board = $this->getBoard();
        $playerPieceWithBall = null;
        if ($board->tryGetPlayerPieceInHex($board->getBallHex(), $playerPieceWithBall))
        {
            if ($playerPieceWithBall->getOwner() === intval($this->getActivePlayerId()))
            {
                $possibleActions[] = ['name' => 'pass ball', 'id' => GameActions::PassBall];
            }
        }

        return ['possibleActions' => $possibleActions];
    }

    public function argPieceSelection() : array
    {
        //TODO: if we are selecting pieces for movement we must show a morale token beside them, if they are exhusted also we should check if the player has morale at all!
        //TODO: we can have a factory, which takes function names, and return arbitrary criteria for pieces, and also we should have functions that can add elements to the returned array!

        $board = $this->getBoard();

        $activePlayerPieces = $board->getPlayerPiecesByOwner(intval($this->getActivePlayerId()));

        $currentPlayerMorale = $this->getCurrentPlayerMorale();

        $freshPieceIDs = [];
        $exhaustedSelectablePieceIds = [];

        for ($i = 0; $i < count($activePlayerPieces); $i++)
        {
            $currentPiece = $activePlayerPieces[$i];
            if( $currentPiece->getRole() != PlayerRole::GoalKeeper->value)
            {
                if ($currentPiece->isFresh())
                {
                    $freshPieceIDs[] = $currentPiece->getId();
                }
                else if (!$currentPiece->isFresh() && $currentPlayerMorale > 0)
                {
                    $exhaustedSelectablePieceIds[] = $currentPiece->getId();
                }
            }
        }

        return [
            'selectablePieces' => $freshPieceIDs,
            'selectableExhaustedPieces' => $exhaustedSelectablePieceIds
        ];
    }

    public function actPieceSelected(int $pieceId) : void
    {
        $selectablePieces = $this->argPieceSelection();

        if (!in_array($pieceId, $selectablePieces['selectablePieces'], true) &&
            !in_array($pieceId, $selectablePieces['selectableExhaustedPieces'], true))
        {
            throw new BgaUserException("Piece Id {$pieceId} is not selectable.");
        }

        $this->setGameStateValue('selected_piece', $pieceId);

        $this->gamestate->nextState(TR_MOVE_ACTION);
    }

    public function argMoveAction() : array
    {
        $selectedPiece = intval($this->getGameStateValue('selected_piece'));

        assert($selectedPiece != -1, "move action must have a valid piece selected, before it gets called!");

        $moveableHexes = $this->getBoard()->getMoveableHexesByPiece($selectedPiece);

        $possibleActions = [];
        $numberOfMoves = intval($this->getGameStateValue('number_of_moves'));

        if ($numberOfMoves > 0)
            $possibleActions[] = ['name' => 'Finish Movement', 'id' => GameActions::FinishMove];

        return ["moveableHexes" => $moveableHexes,
                "selectedPiece" => $selectedPiece,
                "possibleActions" => $possibleActions
            ];
    }

    public function actPieceMoved(int $hexColumn, int $hexRow) : void
    {
        //TODO: check if the moved location is valid.

        $selectedPiece = intval($this->getGameStateValue('selected_piece'));

        $ballLocation = $this->getObjectFromDB("SELECT `hex_column`, `hex_row` FROM `ball`");
        $pieceOldData = $this->getObjectFromDB("SELECT `hex_column`, `hex_row`, is_fresh FROM `player_pieces` WHERE `id` = $selectedPiece");
        $oldPieceHexRow = intval($pieceOldData['hex_row']);
        $oldPieceHexCol = intval($pieceOldData['hex_column']);

        $ballMoved = 0;

        if (intval($ballLocation['hex_column']) === $oldPieceHexCol && intval($ballLocation['hex_row']) === $oldPieceHexRow)
        {
            $ballMoved = 1;
            $this->DbQuery("UPDATE ball SET `hex_column` = $hexColumn, `hex_row` = $hexRow ");
            //TODO: this will probably change attacker and defender (I am not sure however if it happens right now?)
        }

        $isFresh = boolval($pieceOldData['is_fresh']);
        $numberOfMoves = intval($this->getGameStateValue('number_of_moves'));

        $shouldEndMovement = false;

        if ($numberOfMoves === 1)
        {
            $shouldEndMovement = true;
        }

        $spentMorale = 0;
        if(!$isFresh && $numberOfMoves === 0) //player has moved an exhusted piece.
        {
            $currentMorale = $this->getCurrentPlayerMorale();
            $this->setCurrentPlayerMorale($currentMorale - 1, false);

            $spentMorale = 1;
            $shouldEndMovement = true;
        }

        $board = $this->getBoard();

        if ($ballMoved === 1 &&
            ($board->doesOpponentExistInNeighbours(new HexID($oldPieceHexCol, $oldPieceHexRow), intval($this->getActivePlayerId())) ||
            $board->doesOpponentExistInNeighbours(new HexID($hexColumn, $hexRow), intval($this->getActivePlayerId()))))
        {
            $shouldEndMovement = true;
        }

        $this->DbQuery("UPDATE `player_pieces` SET `hex_column` = $hexColumn, `hex_row` = $hexRow WHERE `id` = $selectedPiece ");
        $this->setGameStateValue('number_of_moves', $numberOfMoves + 1);

        $this->notify->all("movedPiece", \clienttranslate('${player_name} moves a piece'), array(
            "player_name" => $this->getActivePlayerName(),
            "movedPiece" => $selectedPiece,
            "hex_column" => $hexColumn,
            "hex_row" => $hexRow,
            "ballMoved" => $ballMoved,
            "spentMorale"=> $spentMorale
        ));

        if ($shouldEndMovement)
        {
            $this->gamestate->nextState(TR_MOVE_DONE);
        }
        else
        {
            $this->gamestate->nextState(TR_MOVE_ACTION);
        }
    }

    private function FinishedMove() : void
    {
        $this->gamestate->nextState(TR_MOVE_DONE);
    }

    public function argPassDestSelection() : array
    {
        $board = $this->getBoard();
        
        $allPieces = $board->getPieces();
        
        $blockedSpaces = [];
        
        $currentPlayerId = intval($this->getActivePlayerId());

        foreach ($allPieces as $piece)
        {
            if ($piece->getOwner() !== $currentPlayerId && $piece->getRole() !== PlayerRole::GoalKeeper->value)
            {
                $piecePos = $piece->getPosition();
                $blockedSpaces[] = $piecePos->convertToArray();
            }
        }

        $playerGoaliePos = $board->getPlayerGoalie($currentPlayerId)->getPosition();

        foreach(BoardInfo::$GOALIE_SPECIFIC_LOCATIONS as $goalieLocation)
        {
            if (!$goalieLocation->equals($playerGoaliePos))
            {
                $blockedSpaces[] = $goalieLocation->convertToArray();
            }
        }

        $hexWithBall = $board->getBallHex();
        
        return [
            "hexWithBall" => $hexWithBall->convertToArray(),
            "blockedSpaces" => $blockedSpaces,
            "possibleActions" => [['name' => 'cancel', 'id' => GameActions::Cancel]]];
    }

    public function actDestSelected(int $hexColumn, int $hexRow) : void
    {
        $selectedHex = new HexID($hexColumn, $hexRow);

        $passDestArgs = $this->argPassDestSelection();

        $blockedSpaces = $passDestArgs["blockedSpaces"];
        $blockedSpaces[] = $passDestArgs["hexWithBall"];

        foreach($blockedSpaces as $blockedSpace)
        {
            $hex = HexID::fromArray($blockedSpace);

            if ($selectedHex->equals($hex))
                throw new BgaUserException('blocked space selected as pass destination!');
        }

        $board = $this->getBoard();
        
        $hexWithBall = HexID::fromArray($passDestArgs["hexWithBall"]);
        $dummyinlineHexes = [];
        $dummyblockingHexes = [];
        $requiredPower = $board->getRequiredPower($hexWithBall, $selectedHex, $this->getInactivePlayerId(), $dummyinlineHexes, $dummyblockingHexes);

        $this->setGameStateValue('required_power', $requiredPower);

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->storeGlobalString('morale_assign_transition', TR_DICE_ROLL);
        $dbAdapter->storeSelectedHex($hexColumn, $hexRow);

        $this->gamestate->nextState(TR_MORALE_ASSIGN);
    }

    public function argMoraleAssignment() : array
    {
        $currentPlayerMorale = $this->getCurrentPlayerMorale();

        $maxMoraleAssignable = Math::clamp($currentPlayerMorale, 0, self::MAX_MORALE_PER_ACTION);

        $requiredPower = $this->getGameStateValue('required_power');

        $selectedHexArray = DBAdapter::getInstance()->getSelectedHex();

        return ['maxMoraleAssignable' => $maxMoraleAssignable,
                'requiredPower' => $requiredPower,
                'selectedDestination' => $selectedHexArray]; //TODO: this probably must be injected here, as not all actions have a destination!
    }

    public function actMoraleAssigned(int $amount) : void
    {
        $maxMoraleAssignable = $this->argMoraleAssignment()['maxMoraleAssignable'];

        if ($amount > $maxMoraleAssignable || $amount < 0)
        {
            throw new BgaUserException("$amount morale assigned, max morale assignable is $maxMoraleAssignable");
        }

        $this->setCurrentPlayerMorale($this->getCurrentPlayerMorale() - $amount, true);

        $this->setGameStateValue('assigned_morale', $amount);
        
        $transition = DBAdapter::getInstance()->getGlobalString('morale_assign_transition');
        
        assert(strlen($transition) > 0, "no transition is stored for morale assign finish");

        $this->gamestate->nextState($transition);
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
    
        $this->gamestate->nextState(TR_SETUP_COMPLETE);
    }

    public function actDebugAddMorale(int $amount)  
    {
        if($amount >= 0)
        {
            $this->setCurrentPlayerMorale($amount, true);
        }
    }

    //=======================================

    public function stSetupComplete() : void
    {
        $previousPlayerId = (int)$this->getActivePlayerId();
        $this->giveExtraTime($previousPlayerId);
               
        $playerId = intval($this->activeNextPlayer());

        if (!$this->isPlayerSetupComplete($playerId)) 
        {
            $this->gamestate->nextState(TR_PLAYER_PIECE_SETUP);
        } 
        else 
        {
            $this->gamestate->nextState(TR_PLAYER_TURN);
        }
    }

    public function stMoveFinished() : void
    {    
        $selectedPiece = $this->getGameStateValue('selected_piece');
        $this->DbQuery("UPDATE player_pieces SET is_fresh = FALSE  WHERE `id` = $selectedPiece");

        $this->setGameStateValue('selected_piece', -1);
        $this->setGameStateValue('number_of_moves', 0);

        $this->notify->all("pieceExhausted", \clienttranslate('piece was exhausted'), array(
            "pieceId" => $selectedPiece,
        ));

        $this->gamestate->nextState(TR_MOVE_DONE);
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
            "SELECT `player_id` `id`, `player_score` `score`, `resource_morale` `morale` FROM `player`"
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

        DBAdapter::getInstance()->initGlobals();

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

        $this->setGameStateInitialValue('number_of_moves', 0);
        $this->setGameStateInitialValue('selected_piece', -1);
        $this->setGameStateInitialValue('required_power', -1);
        $this->setGameStateInitialValue('assigned_morale', 0);
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

    private function getCurrentPlayerMorale() : int
    {
        $current_player_id = $this->getActivePlayerId();

        $playersMorale = $this->getCollectionFromDb(
            "SELECT `player_id` `id`, `resource_morale` `morale` FROM `player`"
        );

        $result = -1;

        foreach($playersMorale as $playerMorale)
        {
            if ($playerMorale['id'] == $current_player_id)
            {
                $result = intval($playerMorale['morale']);
                break;
            }
        }

        assert($result >= 0, "morale must be always equal or greater than 0!");

        return $result;
    }

    private function setCurrentPlayerMorale(int $amount, bool $notify) : void
    {
        assert($amount >= 0, "morale should always be greater or equal to zero!");
        
        $current_player_id = (int) $this->getActivePlayerId();

        $this->DbQuery(
            "UPDATE `player` SET resource_morale = {$amount} WHERE player_id = {$current_player_id}"
        );

        if ($notify)
        {
            $this->notify->all("moraleChanged", "morale changed to {$amount}", 
            ["amount" => $amount, 
                   "playerId" => $current_player_id]);
        }
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
