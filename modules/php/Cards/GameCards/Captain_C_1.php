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
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;

class Captain_C_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            "Captain 2",
            CardType::Captain,
            \clienttranslate("**Requirement:** Play when having the ball. 
**Effect:** Pass the ball and skip a block.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(2, 1);
    }

    public function getId(): string
    {
        return self::staticId();
    }

    public function isExecutionPossible(): bool
    {
        $result = false;

        $playerId = intval($this->game->getActivePlayerId());
        $passActionContext = new PassActionContext($playerId);

        $pieceId = $passActionContext->getLastPassReceiverIdThisTurn();

        if (PlayerPiece::getInvalidPieceId() != $pieceId)
            $result = true;

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->enableSkip(...),
            $this->goToPassState(...),
            $this->giveMoraleIfCaptainPassed(...)
        ];
    }

    protected function cleanup() : void
    {
    }

    private function giveMoraleIfCaptainPassed() : ExecutionResult
    {
        $playerId = intval($this->game->getActivePlayerId());
        $passActionContext = new PassActionContext($playerId);

        $piecesPassedIds = $passActionContext->getPiecesPassedIds();
        assert(count($piecesPassedIds) ==1);

        if ($this->board->getPiece($piecesPassedIds[0])->getRole() == PlayerRole::Captain->value)
        {
            $playerId = \intval($this->game->GetActivePlayerId());
            $playerResources = new PlayerResources($playerId);
            $playerResources->addMorale(1, true);
        }

        return new ExecutionResult(ExecutionAction::Success);
    }
}