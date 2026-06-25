<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\DBAdapter;

class TacticContext
{
    private readonly DBAdapter $dbAdapter;

    public function __construct()
    {
        $this->dbAdapter = DBAdapter::getInstance();
    }

    public function setTacticInPlay(string $id)
    {
        $this->dbAdapter->storeGlobalString("tactic_in_play", $id);
    }

    public function getTacticInPlay(): string
    {
        return $this->dbAdapter->getGlobalString("tactic_in_play");
    }

    public function setTacticProgress(int $progress)
    {
        $this->dbAdapter->storeGlobalInt("tactic_progress", $progress);
    }

    public function getTacticProgress(): int
    {
        return $this->dbAdapter->getGlobalInt("tactic_progress");
    }

    public function setRewardUsed(CardReward $reward)
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

    public function reset()
    {
        //TOOD: do all this in a single query
        $this->setTacticInPlay('');
        $this->setTacticProgress(0);
        $this->setRewardUsed(CardReward::empty());
        $this->setTacticType(TacticType::Unknown);
    }
}