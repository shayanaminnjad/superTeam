<?php

namespace Bga\Games\SuperTeam;

enum CardType : int
{
    case None = 0;
    case Basic = 1;
    case Captain = 2;
    case Team = 3;
    case Coach = 4;
}

interface ICardCommand
{
    public function canBePlayed() : bool;

    public function getCommandArgs() : array;

    public function validateAct(array $actData) : bool;

    public function performAct(array $actData) : void;

    public function isActComplete(bool &$wasSuccess) : bool;

    public function finishCommand() : void;
}

enum PassType : int
{
    case Unknown = 0;

    case Forward = 1;

    case Backward = 2;

    case Cross = 3;

    case Through = 4;
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
}

class MoveCommand implements ICardCommand
{
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
    private readonly int $move;
    private readonly int $action;
}

class Card
{
    private readonly int $id;
    private readonly string $name;
    private readonly CardType $type;
    private readonly string $toolTip;

    private readonly array $defensiveCommands; // array of ICardCommand
    private readonly CardReward $defensiveReward;
    private readonly bool $areDefensiveCommandsOrderes;
    private readonly bool $defenseHasFatigue;

    private readonly array $offsensiveCommands; // array of ICardCommand
    private readonly bool $areOffensiveCommandsOrderes;
    private readonly CardReward $offsensiveReward;
    private readonly bool $offenseHasFatigue;
}