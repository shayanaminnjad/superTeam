<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;

use Bga\Games\SuperTeam\Math\Math;
use Bga\Games\SuperTeam\DBAdapter;

class MoraleAssignment extends GameState
{
    private const MAX_MORALE_PER_ACTION = 3;

    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_MORALE_ASSIGMENT,
            name: 'moraleAssignment',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is assigning morale'),
            descriptionMyTurn: clienttranslate('assign morale (Max ${maxMoraleAssignable})'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function getArgs(): array
    {
            $currentPlayerMorale = $this->game->getCurrentPlayerMorale();

        $maxMoraleAssignable = Math::clamp($currentPlayerMorale, 0, self::MAX_MORALE_PER_ACTION);

        $requiredPower = $this->game->getGameStateValue('required_power');

        $selectedHexArray = DBAdapter::getInstance()->getSelectedHex();

        return ['maxMoraleAssignable' => $maxMoraleAssignable,
                'requiredPower' => $requiredPower,
                'selectedDestination' => $selectedHexArray]; //TODO: this probably must be injected here, as not all actions have a destination!
    }

    #[PossibleAction]
    public function actMoraleAssigned(int $amount) : string
    {
        $maxMoraleAssignable = $this->getArgs()['maxMoraleAssignable'];

        if ($amount > $maxMoraleAssignable || $amount < 0)
        {
            throw new \BgaUserException("$amount morale assigned, max morale assignable is $maxMoraleAssignable");
        }

        $this->game->setCurrentPlayerMorale($this->game->getCurrentPlayerMorale() - $amount, true);

        $this->game->setGameStateValue('assigned_morale', $amount);
        
        $transition = DBAdapter::getInstance()->getGlobalString('morale_assign_transition');
        
        assert(strlen($transition) > 0, "no transition is stored for morale assign finish");

        return DiceRoll::class;
    }

    function zombie(int $playerId): string {
        // the code to run when the player is a Zombie
        return '';
    }
}
