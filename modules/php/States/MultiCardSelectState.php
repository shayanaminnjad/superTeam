<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\Actions\Types\IntArrayParam;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\MultiCardSelectionContext;
use Bga\Games\SuperTeam\States\PlayerBaseState;

class MultiCardSelectState extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_MULTI_CARD_SELECTION_STATE,
            name: 'multiCardSelection',
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} must ${selectionTitle}'),
            descriptionMyTurn: clienttranslate('${you} must ${selectionTitle}'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $activePlayerId = intval($this->game->getActivePlayerId());
        $multicardSelectionContext = new MultiCardSelectionContext($activePlayerId);
        $range = $multicardSelectionContext->getSelectableCardsRange();

        $title = $multicardSelectionContext->getTitle();
        if ($title === '')
            $title = clienttranslate('select cards');

        return [
            'minCards' => $range[0],
            'maxCards' => $range[1],
            'selectionTitle' => $title,
            'i18n' => ['selectionTitle'],
        ];
    }

    #[PossibleAction]
    public function actCardsSelected(#[IntArrayParam()] array $cardsSelectedIds) : string
    {
        $minCards = $this->selfGetArgs()['minCards'];
        $maxCards = $this->selfGetArgs()['maxCards'];

        $cardsSelectedCount = count($cardsSelectedIds);

        if ($cardsSelectedCount < $minCards || $cardsSelectedCount > $maxCards)
            throw new \BgaUserException(clienttranslate('You have to select [$(minCards), $(maxCards)] cards!'));

        $activePlayerId = intval($this->game->getActivePlayerId());

        if (!$this->game->getPlayerHand($activePlayerId)->areAllCardsInHand($cardsSelectedIds))
            throw new \BgaUserException(clienttranslate('You must select different cards from your hand!'));

        $multicardSelectionContext = new MultiCardSelectionContext($activePlayerId);

        $multicardSelectionContext->storeCardsSelected(array_map('intval', $cardsSelectedIds));
        $nextState = $multicardSelectionContext->getNextState();
        if (empty($nextState))
        {
            return DBAdapter::getInstance()->getPlayerActionReturnState($activePlayerId);
        }
        else
        {
            return $nextState;
        }
    }

    function zombie(int $playerId): string
    {
        $minCards = $this->selfGetArgs()['minCards'];

        $playerHand = $this->game->getPlayerHand($playerId);
        $cardsInHand = $playerHand->getCardsInHand();

        $cardsSelected = [];
        foreach($cardsInHand as $card)
        {
            $cardsSelected[] = intval($card['id']);

            if (count($cardsSelected) === $minCards)
                break;
        }

        return $this->actCardsSelected($cardsSelected);
    }
}