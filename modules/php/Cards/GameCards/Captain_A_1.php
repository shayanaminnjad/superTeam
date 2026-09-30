<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;

class Captain_A_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Captain 2",
            CardType::Captain,
            \clienttranslate("**Requirement:** Play after receiving the ball inside the Penalty Area.
**Effect:** Gain 1 Morale and Shoot.")), $game);
    }

    public function isExecutionPossible(): bool
    {
        return $this->isBallReceiverInsidePenaltyArea();
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(0, 1);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    protected function getCommands(): array
    {
        return [
            $this->giveMorale(...),
            $this->goToShootState(...),
            $this->giveMoraleIfWasCaptain(...)
        ];
    }

    protected function cleanup() : void
    {
    }

    private function giveMoraleIfWasCaptain() : ExecutionResult
    {
        $playerId = intval($this->game->getActivePlayerId());

        $passActionContext = new PassActionContext($playerId);
        $pieceId = $passActionContext->getLastPassReceiverIdThisTurn();

        $playerPiece = $this->board->getPlayerPieceData($pieceId);

        if ($playerPiece->getRole() == PlayerRole::Captain->value)
        {
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Success);
    }
}