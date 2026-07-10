<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;


use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\Moves\CardDefensiveMoves;
use Bga\Games\SuperTeam\Cards\Moves\DefensiveMovesContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveTypes;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MoveLogic;
use Bga\Games\SuperTeam\Utility\Logger;

class MultiMove extends PlayerBaseState
{
    function __construct(
        Game $game,
    ) {
        parent::__construct(
            game: $game,
            name: 'multiMove',
            id: ST_MULTI_MOVE,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} should choose a move'),
            descriptionMyTurn: clienttranslate('${actplayer} chooses a move'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState()
    {
        if ($this->getRemainingMoves()->isEmpty())
            return TacticalTurnStateManager::class;
    }

    public function selfGetArgs(): array
    {
        $movesRemainingArr = $this->getRemainingMoves()->toArray();

        foreach($movesRemainingArr as $key => $value)
        {
            if ($value > 0)
            {
                //TOOD: moves that cannot be performed anymore must be flagged.
                $this->addClientButton("{$value}X [$key]", [$this, 'actMoveChosen'], ['key' => $key]);
            }
        }

        return [];
    }

    #[PossibleAction]
    public function actMoveChosen(string $key)
    {
        $moveType = MoveTypes::tryFrom($key);
        if ($moveType === null)
            throw new \BgaUserException("invalid key sent `$key`");

        $movesRemaining = $this->getRemainingMoves();

        if ($movesRemaining->getMoves($moveType) <= 0)
            throw new \BgaUserException("invalid move selected `$key`");

        $defensiveMoveContext = new DefensiveMovesContext();

        $movesPerformed = $defensiveMoveContext->getMovesPerformed();
        $movesPerformed->increaseMoveByOne($moveType);
        $defensiveMoveContext->storeMovesPerformed($movesPerformed);

        $playerId = \intval($this->game->getActivePlayerId());

        $anyPieceEnforced = MoveLogic::enforceMovePieceSelectionConstraints($this->game->getBoard(), $playerId, $moveType, true);

        if (!$anyPieceEnforced)
            throw new \BgaUserException("Invalid move selected! no piece can be moved with that criteria!");

        DBAdapter::getInstance()->storeActionReturnState($playerId, self::class);

        return MovePieceSelection::class;
    }

    private function getRemainingMoves() : CardDefensiveMoves
    {
        $defensiveMoveContext = new DefensiveMovesContext();
        $tacticContext = new TacticContext();

        $cardInPlay = $tacticContext->getTacticInPlay();
        $card = $this->game->getCardFactory()->getCard($cardInPlay);

        $cardDefensiveMoves = $card->getDefinition()->cardDefensiveMoves;

        $movesPerformedSoFar = $defensiveMoveContext->getMovesPerformed();

        return $cardDefensiveMoves->diff($movesPerformedSoFar);
    }

    function zombie(int $playerId): string
    {
        return $this->actCancel();
    }
}