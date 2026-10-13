<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MovePieceSelectionLogic;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\States\MovePieceSelection;

class Fatigue extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Fatigue"),
            CardType::Fatigue,
            \clienttranslate("One move")), $game);
    }
    public function isExecutionPossible(): bool
    {
        $playerId = intval($this->game->getActivePlayerId());
        return count(MovePieceSelectionLogic::extractFreeMoveMoveablePieces($this->board,$playerId)) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getFatigueCardType();
    }

    public function getId(): string
    {
        return self::staticId();
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->goToFreeMovementState(...),
            $this->gainMoraleIfHavePower(...)
            ];
    }

    protected function cleanup(): void
    {
    }

    private function goToFreeMovementState() : ExecutionResult
    {
        $playerId = intval($this->game->getActivePlayerId());

        MovePieceSelectionLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $playerId, MoveTypes::FREE);

        $moveActionContext = new MoveActionContext();
        $moveActionContext->setCurrentMoveType(MoveTypes::FREE);

        return new ExecutionResult(ExecutionAction::GoToState, MovePieceSelection::class);
    }

    private function gainMoraleIfHavePower() : ExecutionResult
    {
        $playerId = intval($this->game->getActivePlayerId());

        if (PlayerPowers::forPlayer($playerId)->gainsMoraleOnFatiguePlay())
        {
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Success);
    }
}