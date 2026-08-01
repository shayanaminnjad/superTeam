<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\RequiredPower;
use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\TeamAttribute;
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
        
        $forcedPositions = $passDestArgs["forcedPositions"];

        if (\count($forcedPositions) > 0)
        {
            $found = false;
            foreach($forcedPositions as $pos)
            {
                if(HexID::fromArray($pos)->equals($selectedHex))
                {
                    $found = true;
                    break;
                }
            }

            if (!$found)
                throw new BgaUserException('only given positions should be selected!'); 
        }
        else
        {
            $blockedSpaces = $passDestArgs["blockedSpaces"];
            $blockedSpaces[] = $passDestArgs["hexWithBall"];

            foreach ($blockedSpaces as $blockedSpace) 
            {
                $hex = HexID::fromArray($blockedSpace);

                if ($selectedHex->equals($hex))
                    throw new BgaUserException('blocked space selected as pass destination!');
            }
        }

        $board = $this->game->getBoard();
        
        $hexWithBall = HexID::fromArray($passDestArgs["hexWithBall"]);
        $dummyinlineHexes = [];
        $dummyblockingHexes = [];
        $requiredPower = $board->getRequiredPower($hexWithBall, $selectedHex, $this->game->getInactivePlayerId(), $dummyinlineHexes, $dummyblockingHexes);

        //TODO: goalie pass is different from regular pass, if it is within a player half where there is a piece, then 
        //it is free, otherwise, it is regular! so a goalie pass with the situation described must by pass dice roll and morale assignment,
        //and perform must also notice that. (it must skip reading dice roll and morale assign)

        DiceRollServices::storeRollInput(\intval($this->game->getActivePlayerId()), $requiredPower,PassPerform::class,TeamAttribute::Pass, TeamAttribute::None, false);
        MoraleAssignment::storeMoraleAssignData(DiceRoll::class, false);
        
        $dbAdapter = DBAdapter::getInstance();
        $dbAdapter->storeSelectedHex($hexColumn, $hexRow);

        return MoraleAssignment::class;
    }

    #[PossibleAction]
    public function actCancel(int $activePlayerId) : string
    {
        return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
    }

    function zombie(int $playerId): string
    {
        return $this->actCancel();
    }
}
