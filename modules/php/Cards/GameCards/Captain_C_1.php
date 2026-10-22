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
use Bga\Games\SuperTeam\PlayerPiece;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;

class Captain_C_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 2"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play when receiving the ball. 
**Effect:** Pass the ball and skip a block.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_3::staticId(), 1);
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
}