<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\Utility\Logger;
use Bga\GameFramework\Components\Deck;
use ErrorException;

class PlayerHand
{
    private static int $MAX_CARDS_IN_HAND = 4;

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

    public function removeCard(int $cardId) : void
    {
        //TODO: where can I remove cards from?
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
        return $this->cards->countCardInLocation($this->deckName, $this->ownerId);
    }

    public function shuffleDeck() 
    {
        return $this->cards->shuffle($this->deckName);
    }

    public function onReshuffle()
    {
        $this->wasShuffled = true;
    }

    //TODO: this part deserves it's own class. 
    public static function createPlayerHand(Deck $deck, int $playerId, int $captainBoardId, int $teamBoardId)
    {
        $cardDefinitions = [];

        $BASIC_CARDS_PER_HAND = 5;
        for ($i = 0; $i < $BASIC_CARDS_PER_HAND; $i++) 
        {
            $basicCardType = self::getBasicCardType($i);
            $cardDefinitions[] = self::getCardDefinition($basicCardType);
        }

        $CAPTAIN_CARDS_PER_BOARD = 3;

        for ($i = 0; $i < $CAPTAIN_CARDS_PER_BOARD; $i++) 
        {
            $captainCardType = self::getCaptainCardType($captainBoardId, $i);
            $cardDefinitions[] = self::getCardDefinition($captainCardType);
        }

        $TEAM_CARDS_PER_BOARD = 3;
        for ($i = 0; $i < $TEAM_CARDS_PER_BOARD; $i++)
        {
            $teamCardType = self::getTeamCardType($teamBoardId, $i);
            $cardDefinitions[] =  self::getCardDefinition($teamCardType);
        }

        $coachCardType = self::getCoachCardType($teamBoardId);
        $cardDefinitions[] =  self::getCardDefinition($coachCardType);

        $deckName = self::getPlayerDeck($playerId);
        $deck->createCards($cardDefinitions, $deckName);
        $deck->createCards([self::getCardDefinition("fatigue", 16)], 'supply');
        $deck->shuffle($deckName);
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
        return ["type" => $cardType, 'type_arg' => '', 'nbr' => $count];
    }

    public static function getBasicCardType(int $num): string
    {
        $basicCategory = self::CardTypeToString(CardCategory::Basic);
        $letter = \chr(\ord("A") + $num);

        return "{$basicCategory}_{$letter}";
    }

    public static function getCaptainCardType($captainBoardId, $num): string 
    {
        $captainCategory = self::CardTypeToString(CardCategory::Captain);
        
        return "{$captainCategory}_{$captainBoardId}_{$num}";
    }

    public static function getCoachCardType($teamBoardId): string 
    {
        $coachCategory = self::CardTypeToString(CardCategory::Coach);

        return "{$coachCategory}_{$teamBoardId}";
    }

    public static function getTeamCardType($teamBoardId, $num): string 
    {
        $teamCategory = self::CardTypeToString(CardCategory::Team);

        return "{$teamCategory}_{$teamBoardId}_{$num}";
    }

    public static function CardTypeToString(CardCategory $type):string
    {
        switch ($type) {
            case CardCategory::Basic:
                return "Basic";
            case CardCategory::Captain:
                return "Captain";
            case CardCategory::Coach:
                return "Coach";
            case CardCategory::Team:
                return "Team";
            default:
                throw new ErrorException("Invalid Card category passed!");
        }
    }
}

enum CardCategory: int
{
    case Unknown = 0;

    case Basic = 1;

    case Captain = 2;

    case Coach = 3;

    case Team = 4;

    case Fatigue = 5;
}