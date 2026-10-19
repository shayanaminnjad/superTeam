<?php

namespace Bga\Games\SuperTeam\Cards\GameCards;

use Bga\Games\SuperTeam\ActionTypes;
use Bga\Games\SuperTeam\BoardEvaluator;
use Bga\Games\SuperTeam\Cards\CaptainCard;
use Bga\Games\SuperTeam\Cards\CardDefinition;
use Bga\Games\SuperTeam\Cards\CardType;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\Cards\ExecutionAction;
use Bga\Games\SuperTeam\Cards\ExecutionResult;
use Bga\Games\SuperTeam\Cards\PieceSelectionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_14;
use Bga\Games\SuperTeam\States\PushTargetSelection;

class Captain14_0 extends CaptainCard
{
    public function __construct(Game $game)
    {
        parent::__construct(new CardDefinition(
            \clienttranslate("Captain 1"),
            CardType::Captain,
            \clienttranslate("**Requirement:** Play when adjacent to any opponent. 
**Effect:** Swap position with an opponent and push an opponent.")), $game);
    }

    public static function staticId() : string
    {
        return CardTypeHelper::getCaptainCardType(CaptainPower_14::staticId(), 0);
    }

    public function getId(): string
    {
        return self::staticId();
    }
    public function isExecutionPossible(): bool
    {
        return count($this->getPiecesThatCanSwap()) > 0;
    }

    /**
     * @inheritDoc
     */
    protected function getCommands(): array
    {
        return [
            $this->enforcePiecesThatCanSwap(...),
            $this->goToCaptainCardPerformerSelectionState(...),
            $this->goToSwapPieceState(...),
            $this->makeNextActionForfeitable(...),
            $this->goToPushTargetSelectionStateIfPossible(...),
            $this->ifCaptainSelectedGiveMorale(...)
        ];
    }

    private function goToPushTargetSelectionStateIfPossible(): ExecutionResult
    {
        $pieceSelectionContext = new PieceSelectionContext();
        $selectablePiece = $this->board->getPiece($pieceSelectionContext->getSelectablePieceIds()[0]);

        $playerId = \intval($this->game->getActivePlayerId());
        if (count(BoardEvaluator::getPushableOpponentPieces($this->board, $selectablePiece, $playerId)) == 0)
            return new ExecutionResult(ExecutionAction::Continue);

        DBAdapter::getInstance()->storeSelectedHex($selectablePiece->getPosition());
        return new ExecutionResult(ExecutionAction::GoToState, PushTargetSelection::class);
    }

    protected function cleanup(): void
    {
    }
}