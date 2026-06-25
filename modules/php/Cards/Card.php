<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Cards\CardType;

final class CardReward
{
    public function __construct(
        public int $morale,
        public int $move,
        public int $action,
    ) {}

    public function toJson() : string
    {
        return json_encode($this->toArray());
    }

    public function toArray() : array
    {
        return [ 
            'morale' => $this->morale,
            'move' => $this->move,
            'action' => $this->action
        ];
    }

    public function isEmpty() : bool
    {
        return $this->morale == 0 && $this->move == 0 && $this->action == 0;
    }

    public function diff(CardReward $rhs) : CardReward
    {
        return new CardReward(
            $this->morale - $rhs->morale, 
            $this->move - $rhs->move, 
            $this->action - $rhs->action);
    }

    public static function fromJson(string $jsonInput) : CardReward
    {
        return CardReward::fromArray(json_decode($jsonInput, true));
    }

    public static function fromArray(array $input)
    {
        return new CardReward($input['morale'], $input['move'], $input['action']);
    }

    public static function empty(): CardReward
    {
        return new CardReward(0, 0, 0);
    }
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

    abstract public function isDefensivePossible() : bool;

    abstract public function getId() : string;

    public function execute(TacticType $type, int $index) : ExecutionResult
    {
        $steps = null;
        if ($type == TacticType::Defensive)
        {
            $steps = $this->getDeffensiveCommands();
        }
        else
        {
            $steps = $this->getOffensiveCommands();
        }

        assert($index < \count($steps) && $index >= 0);

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

    /**
     * @return array<callable(): ?ExecutionResult>
     */
    abstract protected function getDeffensiveCommands() : array;

    abstract protected function cleanup();
}

