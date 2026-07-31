<?php

namespace Bga\Games\SuperTeam\Cards;

use ErrorException;

class CardTypeHelper
{    
    public static function getBasicCardType(int $num): string
    {
        $basicCategory = self::CardTypeToString(CardType::Basic);
        $letter = \chr(\ord("A") + $num);

        return "{$basicCategory}_{$letter}";
    }

    public static function getCaptainCardType(int $captainBoardId, int $num): string
    {
        $captainCategory = self::CardTypeToString(CardType::Captain);
        
        return "{$captainCategory}_{$captainBoardId}_{$num}";
    }

    public static function getCoachCardType($teamBoardId): string 
    {
        $coachCategory = self::CardTypeToString(CardType::Coach);

        return "{$coachCategory}_{$teamBoardId}";
    }

    public static function getTeamCardType($teamBoardId, $num): string 
    {
        $teamCategory = self::CardTypeToString(CardType::Team);

        return "{$teamCategory}_{$teamBoardId}_{$num}";
    }

    public static function getFatigueCardType() : string
    {
        return "Fatigue";
    }

    public static function CardTypeToString(CardType $type):string
    {
        switch ($type) {
            case CardType::Basic:
                return "Basic";
            case CardType::Captain:
                return "Captain";
            case CardType::Coach:
                return "Coach";
            case CardType::Team:
                return "Team";
            case CardType::Fatigue:
                return "Fatigue";
            default:
                throw new ErrorException("Invalid Card category passed!");
        }
    }
}