<?php

namespace Bga\Games\SuperTeam\Cards;

use Bga\GameFramework\Components\Deck;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_1;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_3;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPowerBase;

class PlayerHandCreator
{
    public static function createHand(Deck $deck, int $playerId, int $captainBoardId, int $teamBoardId) : void
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
        $deck->createCards([self::getCardDefinition(CardTypeHelper::getFatigueCardType(), 8)], PlayerHand::$supplyName);
    }

    public static function getCardDefinition(string $cardType, int $count = 1): array
    {
        return ["type" => $cardType, 'type_arg' => 0, 'nbr' => $count];
    }

    public static function getPlayerDeck(int $playerId) : string
    {
        return PlayerHand::getPlayerDeck($playerId);
    }
}