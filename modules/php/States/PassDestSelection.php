<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\States\Utils\DiceRollData;
use BgaUserException;

class PassDestSelection extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_PASS_DEST_SELECTION,
            name: 'passDestinationSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} is choosing hex to pass the ball'),
            descriptionMyTurn: clienttranslate('choose a hex to pass the ball'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $this->addClientButton('Cancel', [$this, 'actCancel']);
        return $this->game->getValidPassTargets(null);
    }

    #[PossibleAction]
    public function actDestSelected(int $hexColumn, int $hexRow) : string
    {
        $selectedHex = new HexID($hexColumn, $hexRow);

        $passDestArgs = $this->game->getValidPassTargets(null);

        $blockedSpaces = $passDestArgs["blockedSpaces"];
        $blockedSpaces[] = $passDestArgs["hexWithBall"];

        foreach($blockedSpaces as $blockedSpace)
        {
            $hex = HexID::fromArray($blockedSpace);

            if ($selectedHex->equals($hex))
                throw new BgaUserException('blocked space selected as pass destination!');
        }

        $board = $this->game->getBoard();
        
        $hexWithBall = HexID::fromArray($passDestArgs["hexWithBall"]);
        $dummyinlineHexes = [];
        $dummyblockingHexes = [];
        $requiredPower = $board->getRequiredPower($hexWithBall, $selectedHex, $this->game->getInactivePlayerId(), $dummyinlineHexes, $dummyblockingHexes);

        DiceRollData::store($requiredPower, addslashes(PassPerform::class));

        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->storeGlobalString('morale_assign_transition',  addslashes(DiceRoll::class));
        $dbAdapter->storeSelectedHex($hexColumn, $hexRow);

        return MoraleAssignment::class;
    }

    #[PossibleAction]
    public function actCancel() : string
    {
        return PlayerTurn::class;
    }

    function zombie(int $playerId): string {
        // the code to run when the player is a Zombie
        return '';
    }
}
