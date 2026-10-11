<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Game;
use Bga\GameFramework\Components\Deck;
use Bga\Games\SuperTeam\Cards\CardTypeHelper;
use Bga\Games\SuperTeam\NotificationAdapter;

class PlayerHand
{
    public static int $MAX_CARDS_IN_HAND = 4;

    private $cards;

    private $ownerId;

    private $deckName;

    private $discardName;

    private static string $handName = 'hand';

    public static string $supplyName = 'supply';

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

    public function getLastDiscardedCard() : array
    {
        $discardedCards = $this->getDiscardedCards();

        if (count($discardedCards) == 0)
            return [];

        $lastCard = null;

        foreach($discardedCards as $card)
        {
            if ($lastCard == null)
                $lastCard = $card;
            else if (intval($card['location_arg']) > intval($lastCard['location_arg']))
                $lastCard = $card;
        }

        return $lastCard;
    }

    public function moveLastDiscardedCardToHand() : array
    {
        $lastDiscardedCard = $this->getLastDiscardedCard();
        $this->cards->moveCard($lastDiscardedCard["id"], self::$handName, $this->ownerId);
        $this->notifyPlayerOfHandRefill();

        return $lastDiscardedCard;
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

    public function drawFatigue() : void
    {
        $this->cards->pickCard(self::$supplyName, $this->ownerId);
    }

    public function getCardsInSupply() : array
    {
        return $this->cards->getCardsInLocation(self::$supplyName);
    }

    public function drawCard(string $cardType) : void
    {
        $cards = $this->cards->getCardsOfTypeInLocation($cardType, null, $this->deckName, null);

        if (count($cards) == 0)
            $cards = $this->cards->getCardsOfTypeInLocation($cardType, null, $this->discardName, null);

        if (count($cards) == 0)
            $cards = $this->cards->getCardsOfTypeInLocation($cardType, null, self::$supplyName, null);

        if (count($cards) == 0)
            throw new \BgaUserException(\clienttranslate('Card type ${cardType} does not exist, or is already inside your hand!'), 100, ['cardType' => $cardType]);

        $card = array_values($cards)[0];

        $this->cards->moveCard($card["id"], self::$handName, $this->ownerId);
    }

    public function discardCard(int $cardId) :void
    {
        $cardsInDiscard = $this->CountCardsDiscarded();
        $this->cards->moveCard($cardId, $this->discardName, $cardsInDiscard);
    }

    public function CountCardsInHand(): int 
    {
        return $this->cards->countCardInLocation(self::$handName, $this->ownerId);
    }

    public function CountCardsDiscarded(): int 
    {
        return $this->cards->countCardInLocation($this->discardName);
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

    public function removeUpToTwoFatiguesFromDeck(bool $withNotify) : void
    {
        $cards = $this->cards->getCardsOfTypeInLocation(CardTypeHelper::getFatigueCardType(), null, $this->deckName, null);

        $fatiguesRemoved = 0;
        foreach (array_slice($cards, 0, 2) as $card) {
            $this->cards->moveCard($card['id'], self::$supplyName);
            $fatiguesRemoved++;
        }

        if ($fatiguesRemoved > 0 && $withNotify)
        {
            NotificationAdapter::getInstance()->notifyAll("fatigueRemoved", '',
                ['cardsInSupply' => $this->getCardsInSupply()]);
        }
    }

    public function notifyPlayerOfHandRefill() : void
    {
        NotificationAdapter::getInstance()->notifyPlayer($this->ownerId, 'handRefill', "",
            [
                'playerHand' => $this->getCardsInHand(),
                'wasDiscardShuffled' => false,
            ]);
    }

    public function MoveAllCardsToDeck() : void
    {
        $this->cards->moveAllCardsInLocation($this->discardName, $this->deckName);
        $this->cards->moveAllCardsInLocation(self::$handName, $this->deckName, $this->getOwner());
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
}
