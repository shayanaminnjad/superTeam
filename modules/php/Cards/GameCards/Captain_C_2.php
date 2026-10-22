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
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_3;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;

class Captain_C_2 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 3"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play after receiving the ball inside the Penalty Area.
**Effect:** Shoot and skip a block.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_3::staticId(), 2);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        return $this->isBallReceiverInsidePenaltyArea();
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->enforceBallHolderAsPerformer(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->enableSkip(...),
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