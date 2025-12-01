<?php

use Bga\Games\SuperTeam\Game;
use Bga\GameFramework\States\GameState;


class ActionDescriptor
{
    private string $name;
    private string $action;

    public function __construct(string $name, string $action)
    {
        $this->name = $name;
        $this->action = $action;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'action' => $this->action,
        ];
    }
}

abstract class STBaseState extends GameState
{
    /** @var ActionDescriptor[] */
    private array $possibleActions = [];

    function __construct(
        protected Game $game,
        $name,
        $id, 
        $type, 
        $description, 
        $descriptionMyTurn, 
        $transitions, 
        $updateGameProgression,
        $initialPrivate
    ) {
        parent::__construct(
            $game,
            name: $name,
            id: $id,
            type: $type,
            description: $description,
            descriptionMyTurn: $descriptionMyTurn,
            transitions: $transitions,
            updateGameProgression: $updateGameProgression,
            initialPrivate: $initialPrivate,
        );
    }

    final public function getArgs(): array
    {
        $res = $this->selfGetArgs();

        $res['possibleActions'] = $this->serializePossibleActions();

        return $res;
    }

    abstract public function selfGetArgs(): array;

    protected function addPossibleAction(string $name, string $action): void
    {
        $this->possibleActions[] = new ActionDescriptor($name, $action);
    }

    protected function serializePossibleActions(): array
    {
        $result = [];
        foreach ($this->possibleActions as $action) {
            $result[] = $action->toArray();
        }

        return $result;
    }
}
