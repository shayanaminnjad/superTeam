<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Game;
use Bga\GameFramework\Components\Deck;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;

class PlayerHand
{
    public static int $MAX_CARDS_IN_HAND = 4;

    private $cards;

    private $ownerId;

    private $deckName;

    private $discardName;

    private $wasShuffled;

    public function __construct(int $playerId, Game $game) 
    {
        $this->ownerId = $playerId;
        $this->cards = $game->deckFactory->createDeck('player_cards');
        $this->cards->autoreshuffle = true;
        $this->deckName = self::getPlayerDeck($playerId);
        $this->discardName = self::getPlayerDiscard($playerId);
    }
    
    public function getCardsInHand() : array
    {
        return $this->cards->getCardsInLocation('hand', $this->ownerId);
    }

    public function getDiscardedCards() : array
    {
        return $this->cards->getCardsInLocation($this->discardName);
    }

    public function getCardsInDeckRemovePos() : array
    {
        $cards = $this->cards->getCardsInLocation($this->deckName);

        foreach ($cards as &$card) {
            unset($card['location_arg']);
        }
        unset($card); // break reference

        return $cards;
    }

    public function drawToFour() : bool
    {
        $this->wasShuffled = false;

        $diffToFour = self::$MAX_CARDS_IN_HAND - $this->CountCardsInHand();

        if ($diffToFour < 0)
            throw new \BgaSystemException("Cards in hand can never be more than 4!");

        $this->cards->autoreshuffle_custom = [$this->deckName => $this->discardName];
        $this->cards->autoreshuffle_trigger = ['obj' => $this, 'method' => 'onReshuffle'];

        $this->cards->pickCards($diffToFour, $this->deckName, $this->ownerId);

        return $this->wasShuffled;
    }

    public function discardCard(int $cardId) :void
    {
        $this->cards->moveCard($cardId, $this->discardName);
    }

    public function CountCardsInHand(): int 
    {
        return $this->cards->countCardInLocation('hand', $this->ownerId);
    }

    public function CountCardsDiscarded(): int 
    {
        return $this->cards->countCardInLocation($this->discardName, $this->ownerId);
    }

    public function CountCardsInDeck(): int 
    {
        return $this->cards->countCardInLocation($this->deckName);
    }

    public function GetCardsInDeckSecret() : array
    {
        $cards = $this->cards->getCardsInLocation($this->deckName);

        $secretCards = [];
        foreach($cards as &$card)
        {
            $card['location_arg'] = -1;
            $secretCards[] = $card;
        }

        return $secretCards;
    }

    public function MoveAllCardsToDeck() : void
    {
        $this->cards->moveAllCardsInLocation($this->discardName, $this->deckName);
        $this->cards->moveAllCardsInLocation('hand', $this->deckName, $this->getOwner());
    }

    public function shuffleDeck() 
    {
        return $this->cards->shuffle($this->deckName);
    }

    public function onReshuffle()
    {
        $this->wasShuffled = true;
    }

    public function getOwner() : int
    {
        return $this->ownerId;
    }

    //TODO: this part deserves it's own class. 
    public static function createPlayerHand(Deck $deck, int $playerId, int $captainBoardId, int $teamBoardId)
    {
        $cardDefinitions = [];

        $BASIC_CARDS_PER_HAND = 5;
        for ($i = 0; $i < $BASIC_CARDS_PER_HAND; $i++) 
        {
            $basicCardType = CardTypeHelper::getBasicCardType($i);
            $cardDefinitions[] = self::getCardDefinition($basicCardType);
        }

        $CAPTAIN_CARDS_PER_BOARD = 3;

        for ($i = 0; $i < $CAPTAIN_CARDS_PER_BOARD; $i++) 
        {
            $captainCardType = CardTypeHelper::getCaptainCardType($captainBoardId, $i);
            $cardDefinitions[] = self::getCardDefinition($captainCardType);
        }

        $TEAM_CARDS_PER_BOARD = 3;
        for ($i = 0; $i < $TEAM_CARDS_PER_BOARD; $i++)
        {
            $teamCardType = CardTypeHelper::getTeamCardType($teamBoardId, $i);
            $cardDefinitions[] =  self::getCardDefinition($teamCardType);
        }

        $coachCardType = CardTypeHelper::getCoachCardType($teamBoardId);
        $cardDefinitions[] =  self::getCardDefinition($coachCardType);

        $deckName = self::getPlayerDeck($playerId);
        $deck->createCards($cardDefinitions, $deckName);
        $deck->createCards([self::getCardDefinition("fatigue", 16)], 'supply');
    }

    public function getCard(int $cardId)
    {
        return $this->cards->getCard($cardId);
    }

    public static function getPlayerDeck(int $playerId) : string
    {
        return "{$playerId}_deck";
    }

    public static function getPlayerDiscard(int $playerId) :string
    {
        return "{$playerId}_discard";
    }

    public static function getCardDefinition(string $cardType, int $count = 1): array 
    {
        return ["type" => $cardType, 'type_arg' => 0, 'nbr' => $count];
    }
}
