<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\TeamBoard;
use Bga\Games\SuperTeam\TeamAttribute;
use Bga\Games\SuperTeam\TimeTrack;
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
            description: clienttranslate('${actplayer} must assign coach markers'),
            descriptionMyTurn: clienttranslate('${you} must assign your coach markers (${markers_remained} left)'),
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
        return [
            "IsGKAssignable" => $this->isGKAssignable(),
            "markers_remained" => '[markers_remained]'
        ];
    }

    #[PossibleAction]
    public function actPlacedAttrCube(int $attributeId): void
    {
        $teamBoard = $this->game->getTeamBoard();
        $currentPlayer = \intval($this->game->getCurrentPlayerId());
        $totalCubesAssigned = $teamBoard->getTotalAttributesAssigned($currentPlayer);
        
        if ($totalCubesAssigned == TeamBoard::MAX_ALLOWED_ATTRIBUTES)
        {
            throw new BgaUserException(\clienttranslate("You cannot place more than 6 cubes!"));
        }

        if (!$teamBoard->isValidAttributeId($attributeId))
        {
            throw new BgaUserException(\clienttranslate('Invalid skill Id passed! ${attributeId}'), 100, ['attributeId' => $attributeId]);
        }

        if ($this->isGKAssignable() == false && $attributeId == TeamAttribute::GK->value)
        {
            throw new BgaUserException(\clienttranslate("You cannot re-arrange GK attributes in half time!"));
        }
        
        if (!$teamBoard->tryIncreaseAttribute($currentPlayer, TeamAttribute::from($attributeId),true))
        {
            throw new BgaUserException(\clienttranslate("You cannot assign more than two cubes to an attribute!"));
        }
    }

    #[PossibleAction]
    public function actConfirm() : void
    {
        $currentPlayer = \intval($this->game->getCurrentPlayerId());
        $teamBoard = $this->game->getTeamBoard();
        $totalCubesAssigned = $teamBoard->getTotalAttributesAssigned($currentPlayer);
        $attributesRemaining = TeamBoard::MAX_ALLOWED_ATTRIBUTES - $totalCubesAssigned;

        if ($attributesRemaining == 0)
        {
            $this->gamestate->setPlayerNonMultiactive($currentPlayer, TeamAttributesReveal::class);
        }
        else
            throw new BgaUserException(\clienttranslate("You haven't assigned all your cubes! You cannot confirm."));
    }

    #[PossibleAction]
    public function actRestart() : void
    {
        $currentPlayerId = \intval($this->game->getCurrentPlayerId());
        $exception = $this->isGKAssignable() ? TeamAttribute::None : TeamAttribute::GK;
        $this->game->getTeamBoard()->resetPlayerAttributes($currentPlayerId, $exception, true);
    }

    function zombie(int $playerId): void
    {
        $teamBoard = $this->game->getTeamBoard();
        $attributes = [TeamAttribute::Pass, TeamAttribute::Shoot, TeamAttribute::Dribble, TeamAttribute::Tackle];
        if ($this->isGKAssignable())
            $attributes[] = TeamAttribute::GK;

        while ($teamBoard->getTotalAttributesAssigned($playerId) < TeamBoard::MAX_ALLOWED_ATTRIBUTES)
        {
            $choices = array_values(array_filter(
                $attributes,
                fn(TeamAttribute $attr) => $teamBoard->getAttribute($playerId, $attr) < TeamBoard::MAX_PER_ATTRIBUTE_VALUE
            ));

            if (empty($choices))
                break;

            $teamBoard->tryIncreaseAttribute($playerId, $this->getRandomZombieChoice($choices), false);
        }

        $this->gamestate->setPlayerNonMultiactive($playerId, TeamAttributesReveal::class);
    }

    private function isGKAssignable(): bool
    {
        return TimeTrack::GetTimeTrackPosition() == 0;
    }
}
