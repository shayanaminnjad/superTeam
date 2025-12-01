<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\Games\SuperTeam\Game;
use Bga\GameFramework\States\GameState;
use ReflectionMethod;

class ActionDescriptor
{
    private string $name;
    private string $action;
    private array $params;

    public function __construct(string $name, string $action, array $params)
    {
        $this->name = $name;
        $this->action = $action;
        $this->params = $params;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getParams() : array
    {
        return $this->params;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'action' => $this->action,
            'params' => $this->params
        ];
    }
}

abstract class PlayerBaseState extends GameState
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

        $res['clientButtons'] = $this->serializePossibleActions();

        return $res;
    }

    abstract public function selfGetArgs(): array;

    protected function addClientButton(string $name, array $func): void
    {
        $ref = new ReflectionMethod($func[0], $func[1]);

        $params = $ref->getParameters();
        $paramNames = [];

        foreach($params as $param)
            $paramNames[] = $param->getName();

        $this->possibleActions[] = new ActionDescriptor($name, $ref->getName(), $paramNames);
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
