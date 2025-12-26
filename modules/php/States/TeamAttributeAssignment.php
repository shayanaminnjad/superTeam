<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\TeamBoard;
use Bga\Games\SuperTeam\TeamAttribute;
use BgaUserException;

class TeamAttributeAssignment extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_TEAM_ATTRIBUTE_ASSIGNMENT,
            name: 'teamAttributeAssignment',
            type: StateType::MULTIPLE_ACTIVE_PLAYER,

            // optional
            description: clienttranslate('actplayer must assign coach markers'),
            descriptionMyTurn: clienttranslate('you must assign your coach markers'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        DBAdapter::getInstance()->setGameStateValue('isAttributesAssignmentFinished', 0);
    }

    public function selfGetArgs(): array
    {
        return [];
    }

    #[PossibleAction]
    public function actPlacedAttrCube(int $attributeId): void
    {
        $teamBoard = $this->game->getTeamBoard();
        $currentPlayer = \intval($this->game->getCurrentPlayerId());
        $totalCubesAssigned = $teamBoard->getTotalAttributesAssigned($currentPlayer);
        
        if ($totalCubesAssigned == TeamBoard::MAX_ALLOWED_ATTRIBUTES)
        {
            throw new BgaUserException("You cannot place more than 6 cubes!");
        }

        if (!$teamBoard->isValidAttributeId($attributeId))
        {
            throw new BgaUserException("Invalid skill Id passed! $attributeId");
        }
        
        if (!$teamBoard->tryIncreaseAttribute($currentPlayer, TeamAttribute::from($attributeId))) 
        {
            throw new BgaUserException("You cannot assign more than two cubes to an attribute!");
        }

        $this->game->notify->player(
            $currentPlayer,
            "cubePlaced",
            "",
            ["skill" => $attributeId]
        );
        
        $attributesRemaining = TeamBoard::MAX_ALLOWED_ATTRIBUTES - ($totalCubesAssigned + 1);

        if ($attributesRemaining == 0) 
        {
            $this->gamestate->setPlayerNonMultiactive($currentPlayer, TeamAttributesReveal::class);
        } 
    }

    #[PossibleAction]
    public function actDebugDefaultSetup()
    {
        $teamBoard = $this->game->getTeamBoard();
        $currentPlayer = \intval($this->game->getCurrentPlayerId());
        $attributesAssigned = $teamBoard->getTotalAttributesAssigned($currentPlayer);

        for($i = $attributesAssigned; $i < TeamBoard::MAX_ALLOWED_ATTRIBUTES; $i++)
        {
            $teamBoard->tryIncreaseAttribute($currentPlayer, TeamAttribute::from($i % TeamAttribute::Count->value));
        }

        $this->gamestate->setPlayerNonMultiactive($currentPlayer, TeamAttributesReveal::class);
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
