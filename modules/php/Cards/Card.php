<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\HexID;

enum CardType : int
{
    case None = 0;
    case Basic = 1;
    case Captain = 2;
    case Team = 3;
    case Coach = 4;
    case Fatigue = 5;
}

interface ICardCommand
{
    //WAITING: Amir needs to think about this, whether a sequence of actions should be evaluatd altogether, or it is up
    //to player?
    public function canBePlayed() : bool;

    public function getCommandArgs() : array;

    public function validateAct(array $actData) : bool;

    // TODO: In combined commands, this perform act might need to be evaluated multiple times. 
    // maybe keeping basic one time steps and also combining them is the solution.
    public function performAct(array $actData) : void;

    //This also can help with getting the next step.
    public function isActComplete(bool &$wasSuccess) : bool;

    public function finishCommand() : void;
}

enum PassType : int
{
    case Unknown = 0;

    case Forward = 1;

    case Backward = 2;

    case Cross = 3;

    case Through = 4; // one or two spaces around someone, it has no direction, anywhere around a player

    case PressPass = 5; //When you pass you should be adjacent to an opponent piece.
}


class PassForwardCommand
{
    private Board $board;
    private $piecesData;
    private $currentPlayer;

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

        $targetHex = new HexID($passTarget['column'], $passTarget['row']);

        HexLib::getHexesInPath($board->getBallHex(), )
        //TODO: This logic for pass must be encapsulated inside another class, it doesn't relate to here.
        //calculate distance
        //determine blocking pieces
        //get required dice number
        //roll a die to see whether it was successful or not
        //calculate new ball location.
        //update ball locatoin.
        //notify all about all these changes.
    }
}

class PassCommand implements ICardCommand
{
    private readonly PassType $Type;

    public function canBePlayed() : bool
    {
        return false;
    }

    public function getCommandArgs() : array
    {
        return [];
    }

    public function validateAct(array $actData) : bool
    {
        return false;
    }

    public function performAct(array $actData) : void
    {

    }

    public function isActComplete(bool &$wasSuccess): bool
    {
        $wasSuccess = false;
        return true;
    }

    public function finishCommand() : void
    {}
}

enum MoveType : int
{
    case Unknown = 0;
    case Forward = 1;
    case ForwardMarking = 2;
    case Backward = 3;
    case BackwardMarking = 4;
    case Free = 5;

    case ByPass = 6; //if adjacent, move in the direction you have with the opponent piece
}

class MoveCommand implements ICardCommand
{
    //If multiple players must be moved, then they must be unique players.
    //same player cannot be targeted by the same moveCommand twice.
    private readonly MoveType $Type;

    public function canBePlayed() : bool
    {
        return false;    
    }

    public function getCommandArgs(): array
    {
        return [];
    }

    public function validateAct(array $actData) : bool
    {
        return false;
    }

    public function performAct(array $actData) : void
    {

    }

    public function isActComplete(bool &$wasSuccess): bool
    {
        $wasSuccess = false;
        return true;
    }

    public function finishCommand() : void
    {}
}

class CardReward
{
    private readonly int $morale;
    private readonly int $move; //optional
    private readonly int $action; //optional
}

/* coach and captain has only instruction 
captian has a requirement and a reward if it is satisfied

coach has a cost and a reward, which might be moving, reward is optional*/

//there is a coach, that allows you to move all exhuasted players
//Some of the rewards are depenendt on how you satisfy the condition
//Some coach effects give you a bonus for your entire turn

//Probably it is best to have captain conditions as a list of function pointer,
//then you get reward.
//If you do captain card with the captain you get a morale.
//seems captain rewards are a bit different than normal rewards, for example swap.
//if captain cards do something to opponent pieces, they can target the same or different pieces.

//Passes can have a modifier, which makes a pass stronger.

//TODO: another command is push, move an adjacent opponent token, once space in any direction

//there is a pass that you pass back, then pass forward.

//There are or and and in rewards

//This is actually Team and basic cards

//Move rewrads are optional*/
class Card
{
    private readonly int $id;
    private readonly string $name;
    private readonly CardType $type;
    private readonly string $toolTip;

    //TODO: ordered commands canBePlayed() function must be combined, so doing one of them is not enough, 
    //So probably we need a single command, not an array for some of the commands, that seems a combination of basic
    //steps.
    private readonly array $defensiveCommands; // array of ICardCommand
    private readonly CardReward $defensiveReward;
    private readonly bool $areDefensiveCommandsOrderes;
    private readonly bool $defenseHasFatigue;

    private readonly array $offsensiveCommands; // array of ICardCommand
    private readonly bool $areOffensiveCommandsOrderes;
    private readonly CardReward $offsensiveReward;
    private readonly bool $offenseHasFatigue;
}

/*
require_once('behaviors.php');

class Card {
    public string $name;
    public $condition;
    public $reward;

    public function __construct($name, callable $condition, callable $reward) {
        $this->name = $name;
        $this->condition = $condition;
        $this->reward = $reward;
    }

    public function canActivate($game, $playerId) {
        return call_user_func($this->condition, $game, $playerId);
    }

    public function activate($game, $playerId) {
        call_user_func($this->reward, $game, $playerId);
    }
}
*/