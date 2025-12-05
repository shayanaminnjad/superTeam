<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;

class ShootTargetSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_SHOOT_TARGET_SELECTION,
            name: 'shootTargetSelection',
            type: StateType::MULTIPLE_ACTIVE_PLAYER,

            // optional
            description: clienttranslate('your opponent is choosing a target tile'),
            descriptionMyTurn: clienttranslate('you must choose a target tile'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $this->addClientButton('left', [$this, 'actLeft']);
        $this->addClientButton('middle', [$this, 'actMiddle']);
        $this->addClientButton('right', [$this, 'actRight']);

        //(TODO: probably this is too expensive here!)
        $board = $this->game->getBoard();

        return [
            'attacker' => $board->getAttacker(),
            'defender' => $board->getDefender()
        ];
    }

    #[PossibleAction]
    public function actLeft(): void
    {
        $this->onTargetSelected(-1);
    }

    #[PossibleAction]
    public function actMiddle(): void
    {
        $this->onTargetSelected(0);
    }

    #[PossibleAction]
    public function actRight(): void
    {
        $this->onTargetSelected(1);
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }

    private function onTargetSelected(int $target)
    {
        if ($target < -1 || $target > 1)
            throw new \BgaUserException("Invalid target value sent!");

        $player_id = intval($this->game->getCurrentPlayerId());
        $board = $this->game->getBoard();

        $label = $board->getAttacker() === $player_id ? "attackerShootTarget" : "defenderShootTarget";

        DBAdapter::getInstance()->setGameStateValue($label, $target);

        $this->gamestate->setPlayerNonMultiactive($player_id, ShootTargetReveal::class);
    }
}
