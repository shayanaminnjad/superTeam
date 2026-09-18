<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Board;

class CardReward
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

    public static function fromArray(array $input) : CardReward
    {
        return new CardReward($input['morale'], $input['move'], $input['action']);
    }

    public static function empty(): CardReward
    {
        return new CardReward(0, 0, 0);
    }
}

class DefensiveReward extends CardReward
{}

class OffensiveReward extends CardReward
{}

enum ExecutionAction
{
    case Continue;
    case GoToState;
    case Success;
    case Failure;
    case Jump;
}

enum TacticType : int
{
    case Offensive = 0;
    case Defensive = 1;
    case Unknown = 2;
}

final class ExecutionResult
{
    public function __construct(
        public ExecutionAction $action,
        public ?string $requestedState = null,
        public int $index = -1,
    ) {}
}

readonly class CardDefinition
{
    public function __construct(
        public string $name,
        public CardType $type,
        public string $toolTip,
    ) {}
}

readonly class TacticCardDefinition extends CardDefinition
{
    public function __construct(
        string $name,
        CardType $type,
        string $toolTip,
        public CardDefensiveMoves $cardDefensiveMoves,
        public DefensiveReward $defensiveReward,
        public bool $defenseHasFatigue,
        public array $offensivePassSequence,
        public OffensiveReward $offensiveReward,
        public bool $offenseHasFatigue
    ) {
        parent::__construct($name, $type, $toolTip);
    }
}

abstract class Card
{
    protected readonly CardDefinition $definition;
    protected readonly Game $game;
    protected readonly Board $board;

    public function __construct(CardDefinition $definition, Game $game)
    {
        $this->definition = $definition;
        $this->game = $game;
        $this->board = $game->getBoard();
    }

    public function getDefinition() : CardDefinition
    {
        return $this->definition;
    }

    abstract public function isExecutionPossible() : bool;

    abstract public function getId() : string;

    public function execute(int $index) : ExecutionResult
    {
        $steps = $this->getCommands();

        assert($index < \count($steps) && $index >= 0);

        for ($i = $index; $i < \count($steps); $i++)
        {
            $result = $steps[$i]();

            if ($result && $result->action != ExecutionAction::Continue && $result->action != ExecutionAction::Jump)
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

            if ($result && $result->action == ExecutionAction::Jump)
                $i = $result->index - 1; // this is incremented at the beggining of the loop.
        }

        $this->cleanup();

        return new ExecutionResult(ExecutionAction::Success);
    }

    /**
 * @return array<callable(): ?ExecutionResult>
 */
    abstract protected function getCommands() : array;

    abstract protected function cleanup() : void;
}

