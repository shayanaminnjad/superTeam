<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Board;

enum CardType : int
{
    case None = 0;
    case Basic = 1;
    case Captain = 2;
    case Team = 3;
    case Coach = 4;
    case Fatigue = 5;
}

final class CardReward
{
    public function __construct(
        public readonly int $morale,
        public readonly int $move,
        public readonly int $action,
    ) {}
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

enum ExecutionAction
{
    case Continue;
    case GoToState;
    case Success;
    case Failure;
}

enum TacticType : int
{
    case Offensive = 0;
    case Defensive = 1;
}

final class ExecutionResult
{
    public function __construct(
        public ExecutionAction $action,
        public ?string $requestedState = null,
        public int $index = -1,
    ) {}
}

class CardDefinition
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly CardType $type,
        public readonly string $toolTip,

        public readonly CardReward $defensiveReward,
        public readonly bool $defenseHasFatigue,

        public readonly CardReward $offensiveReward,
        public readonly bool $offenseHasFatigue,
    ) {}
}

abstract class Card
{
    protected readonly CardDefinition $definition;
    protected readonly Game $game;
    protected readonly Board $board;

    public function __construct(CardDefinition $definition, Board $board, Game $game)
    {
        $this->definition = $definition;
        $this->game = $game;
        $this->board = $board;
    }

    public function getDefinition() : CardDefinition
    {
        return $this->definition;
    }

    abstract public function isOffensivePossible() : bool;

    public function execute(TacticType $type, int $index) : ExecutionResult
    {
        //TODO: get steps based on the type!
        //TODO: check for index outside of the command list range.
        $steps = $this->getOffensiveCommands();

        for ($i = $index; $i < \count($steps); $i++)
        {
            $result = $steps[$i]();

            if ($result && $result->action != ExecutionAction::Continue)
            {
                if ($result->action == ExecutionAction::GoToState)
                {
                    $result->index = $i + 1;
                }
                else
                {
                    $this->cleanup();
                }

                return $result;
            }
        }

        $this->cleanup();

        return new ExecutionResult(ExecutionAction::Success);
    }

    /**
 * @return array<callable(): ?ExecutionResult>
 */
    abstract protected function getOffensiveCommands() : array;

    abstract protected function cleanup();
}

