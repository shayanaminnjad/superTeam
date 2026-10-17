<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Teams\TeamId;
use Bga\Games\SuperTeam\PlayerResources;

class CoachE extends Card
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Coach"),
            CardType::Coach,
            \clienttranslate("**Requirement:** Pay 1 Morale 
**Effect:** For the whole turn roll an extra yellow die for your actions.")), $game);
    }

    public function isExecutionPossible(): bool
    {
        $playerId = intval($this->game->GetActivePlayerId());
        $playerResources = new PlayerResources($playerId);

        return $playerResources->getMorale() > 0;
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCoachCardType(TeamId::OpponentNeedsExtraOneOnBlock->value);
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
            $this->enableExtraDieForWholeTurn(...)
        ];
    }

    private function enableExtraDieForWholeTurn() : ExecutionResult
    {
        DBAdapter::getInstance()->storeGlobalBool('has_extra_die_for_whole_turn', true);
        return new ExecutionResult(ExecutionAction::Success);
    }

    protected function cleanup(): void
    {
    }
}