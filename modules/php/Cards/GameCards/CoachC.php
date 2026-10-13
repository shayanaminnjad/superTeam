<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;

class CoachC extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Coach"),
            CardType::Coach,
            \clienttranslate("**Requirement:** Pay 1 Morale 
**Effect:** Ready an Exhausted piece.")), $game);
    }
    public function isExecutionPossible(): bool
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerResources = new PlayerResources($playerId);

        return $playerResources->getMorale() > 0 && count(BoardEvaluator::getExhaustedPlayerPieces($this->board, $playerId, false)) > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCoachCardType(2);
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
            $this->spendOneMorale(...),
            $this->goToCoachCardState(...),
            $this->finishOrJumpToMove(...)
        ];
    }

    private function spendOneMorale() : ExecutionResult
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerResources = new PlayerResources($playerId);
        $playerResources->addMorale(-1, true);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function cleanup(): void
    {
    }
}