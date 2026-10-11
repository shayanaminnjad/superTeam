<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Board;
use Bga\Games\SuperTeam\Cards\BallPass\PassActionContext;
use Bga\Games\SuperTeam\Cards\Moves\MoveActionContext;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\PermanentPowers\PlayerPowers;
use Bga\Games\SuperTeam\PlayerPiece;

readonly class TacticContext
{
    private DBAdapter $dbAdapter;

    public function __construct()
    {
        $this->dbAdapter = DBAdapter::getInstance();
    }

    public function setTacticInPlay(string $id) : void
    {
        $this->dbAdapter->storeGlobalString("tactic_in_play", $id);
    }

    public function getTacticInPlay(): string
    {
        return $this->dbAdapter->getGlobalString("tactic_in_play");
    }

    public function setTacticProgress(int $progress): void
    {
        $this->dbAdapter->storeGlobalInt("tactic_progress", $progress);
    }

    public function getTacticProgress(): int
    {
        return $this->dbAdapter->getGlobalInt("tactic_progress");
    }

    public function setTacticCommited(bool $commited): void
    {
        $this->dbAdapter->storeGlobalBool("tactic_commited", $commited);
    }

    public function GetTacticCommited(): bool
    {
        return $this->dbAdapter->getGlobalBool("tactic_commited");
    }

    public function setRewardUsed(CardReward $reward) : void
    {
        $this->dbAdapter->storeGlobalString("rewards_used", $reward->toJson());
    }

    public function getRewardUsed(): CardReward
    {
        $rewardJson = $this->dbAdapter->getGlobalString("rewards_used");

        if ($rewardJson == null || $rewardJson == '')
            return CardReward::empty();
        else
            return CardReward::fromJson($rewardJson);
    }

    public function hasExtraReward(Board $board, int $activePlayerId) : bool
    {
        $playerPowers = PlayerPowers::forPlayer($activePlayerId);

        return $playerPowers->extraMoraleOrMoveForSuccessfulTactic($this->getPlayersInvolvedInTactic($board, $activePlayerId));
    }

    /**
     * @return PlayerPiece[]
     */
    public function getPlayersInvolvedInTactic(Board $board, int $activePlayerId) : array
    {
        $res = [];

        $tacticType = $this->getTacticType();

        if ($tacticType === TacticType::Defensive)
        {
            $moveContext = new MoveActionContext();
            $playersInvolvedIds = $moveContext->getPiecesMoved();
        }
        else
        {
            $passContext = new PassActionContext($activePlayerId);
            $playersInvolvedIds = $passContext->getPiecesPassedIds();
        }

        foreach ($playersInvolvedIds as $playerId)
            $res[] = $board->getPiece($playerId);

        return $res;
    }

    public function isExtraRewardClaimed() : bool
    {
        return $this->dbAdapter->getGlobalBool("extra_morale_move_used");
    }

    public function setIsExtraMoveMoraleRewardUsed(bool $value) : void
    {
        $this->dbAdapter->storeGlobalBool("extra_morale_move_used", $value);
    }

    public function setTacticType(TacticType $type) : void
    {
        $this->dbAdapter->storeGlobalInt("tactic_type", $type->value);
    }

    public function getTacticType(): TacticType
    {
        $typeVal = $this->dbAdapter->getGlobalInt("tactic_type");
        assert($typeVal >= 0 && $typeVal <=1, "tactic type should be either 0 or 1");

        return TacticType::from($typeVal);
    }

    public function getCardInPlayReward(Game $game) : CardReward
    {
        $cardInPlay = $this->getTacticInPlay();
        assert($cardInPlay >= 0);

        $tacticType = $this->getTacticType();

        $card = $game->getCardFactory()->getCard($cardInPlay);
        $cardDef = $card->getDefinition();

        return $tacticType == TacticType::Offensive ? $cardDef->offensiveReward : $cardDef->defensiveReward;
    }

    public function reset(): void
    {
        //TOOD: do all this in a single query
        $this->setTacticInPlay('');
        $this->setTacticProgress(0);
        $this->setRewardUsed(CardReward::empty());
        $this->setTacticType(TacticType::Unknown);
        $this->setTacticCommited(false);
        $this->setIsExtraMoveMoraleRewardUsed(false);
    }
}