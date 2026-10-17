<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\Card;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_14;
use Bga\Games\SuperTeam\PlayerResources;
use Bga\Games\SuperTeam\PlayerRole;

class Captain14_1 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 2"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play when having the ball.
**Effect:** Pass the ball and move.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_14::staticId(), 1);
    }

    public function getId(): string
    {
        return self::staticId();
    }
    public function isExecutionPossible(): bool
    {
        return BoardEvaluator::doesPlayerHasTheBall($this->board, intval($this->game->getActivePlayerId()));
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->goToPassState(...),
            $this->makeNextActionForfeitable(...),
            $this->enforcePasser(...),
            $this->goToFreeMoveWithSelectedPiece(...),
            $this->giveMoraleIfCaptainPassed(...)
        ];
    }

    private function enforcePasser() : ExecutionResult
    {
        $playerId = intval($this->game->getActivePlayerId());
        $passActionContext = new PassActionContext($playerId);

        $piecesPassedIds = $passActionContext->getPiecesPassedIds();
        assert(count($piecesPassedIds) == 1);

        $pieceSelectionContext = new PieceSelectionContext();
        $pieceSelectionContext->storeSelectablePiecesIds($piecesPassedIds);

        return new ExecutionResult(ExecutionAction::Continue);
    }

    protected function cleanup(): void
    {
    }
}