<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\Game;
use Bga\GameFramework\States\GameState;
use ReflectionMethod;

readonly class ActionDescriptor
{
    private string $name;
    private string $action;
    private array $paramsToSendBack;
    private array $buttonParams;

    public function __construct(string $name, string $action, array $paramsToSendBack = [], ClientButtonParams $buttonParams = new ClientButtonParams())
    {
        $this->name = $name;
        $this->action = $action;
        $this->paramsToSendBack = $paramsToSendBack;
        $this->buttonParams = $buttonParams->toArray();
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
            'paramsToSendBack' => $this->paramsToSendBack,
            'buttonParams' => $this->buttonParams
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
        $this->possibleActions = [];

        $res = $this->selfGetArgs();

        $res['clientButtons'] = $this->serializePossibleActions();

        return $res;
    }

    abstract public function selfGetArgs(): array;

    protected function addClientButton(string $name, array $func, array $paramsToSendBack = [], ClientButtonParams $buttonParams = new ClientButtonParams()): void
    {
        $ref = new ReflectionMethod($func[0], $func[1]);

        $this->possibleActions[] = new ActionDescriptor($name, $ref->getName(), $paramsToSendBack, $buttonParams);
    }

    protected function getClientButtons() : array
    {
        return $this->possibleActions;
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
