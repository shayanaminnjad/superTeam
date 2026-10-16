<?php

namespace Bga\Games\SuperTeam;

class MultiCardSelectionContext
{
    private const string MULTICARD_SELECTION_NEXT_STATE_KEY = 'multicard_selection_next_state';
    private const string SELECTABLE_CARDS_IN_MULTICARD_RANGE_KEY = 'selectable_cards_in_multicard_range';
    private const string CARDS_SELECTED_IN_MULTICARD_STATE_KEY = 'cards_selected_in_multicard_state';

    private readonly int $playerId;
    private readonly DBAdapter $dbAdapter;

    public function __construct(int $playerId)
    {
        $this->playerId = $playerId;
        $this->dbAdapter = DBAdapter::getInstance();
    }

    public function storeNextState(string $className) : void
    {
        $this->dbAdapter->storePlayerTempStr($this->playerId, self::MULTICARD_SELECTION_NEXT_STATE_KEY, $className);
    }

    public function getNextState() : string
    {
        return $this->dbAdapter->getPlayerTempStr($this->playerId, self::MULTICARD_SELECTION_NEXT_STATE_KEY);
    }

    /**
     * @return int[]
     */
    public function getSelectableCardsRange() : array
    {
        $jsonStr = $this->dbAdapter->getPlayerTempStr($this->playerId, self::SELECTABLE_CARDS_IN_MULTICARD_RANGE_KEY);

        return json_decode($jsonStr, true);
    }

    public function storeSelectableCardsRange(int $min, int $max) : void
    {
        $rangeArray = [$min, $max];

        $this->dbAdapter->storePlayerTempStr($this->playerId, self::SELECTABLE_CARDS_IN_MULTICARD_RANGE_KEY, json_encode($rangeArray));
    }

    public function storeCardsSelected(array $cardsIds) : void
    {
        $this->dbAdapter->storePlayerTempStr($this->playerId, self::CARDS_SELECTED_IN_MULTICARD_STATE_KEY, json_encode($cardsIds));
    }

    /**
     * @return int[]
     */
    public function getCardsSelected() : array
    {
        $jsonStr = $this->dbAdapter->getPlayerTempStr($this->playerId, self::CARDS_SELECTED_IN_MULTICARD_STATE_KEY);

        return json_decode($jsonStr, true);
    }

    public function reset() : void
    {
        $this->storeNextState("");
        $this->storeCardsSelected([]);
        $this->storeSelectableCardsRange(-1, -1);
    }
}