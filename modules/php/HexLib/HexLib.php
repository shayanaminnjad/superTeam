<?php

namespace Bga\Games\SuperTeam\HexLib;

use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\Math\Vector2;
use Bga\Games\SuperTeam\Math\Math;

class HexLib
{
    public static function offsetToCubic(HexID $hexID) : CubicCoordinate
    {
        $q = $hexID->getColumn();
        $r = $hexID->getRow() - floor(($q - ($q & 1)) * 0.5);
        $s = -$q - $r;

        return new CubicCoordinate($q, $r, $s);
    }

    public static function cubicToHexID(CubicCoordinate $cubic) : HexID
    {
        $c = $cubic->getQ();
        $r = $cubic->getR() + floor(($c - ($c & 1)) * 0.5);

        return new HexID($c, $r);
    }

    public static function HexIDToCartesian(HexID $hexID) : Vector2
    {
        //sha: Since I don't want pixel perfect results, and only care about some relatively correct coordinates, I assume hex size is 1.
        $horizontalSpacing = 1.5; // hex size * 1.5
        $verticalSpacing = 1.73205; //sqrt(3) * hexSize

        $isEvenColumn = ($hexID->getColumn() & 1) == 0;

        if ($isEvenColumn)
        {
            return new Vector2($hexID->getColumn() * $horizontalSpacing, $hexID->getRow() * $verticalSpacing);
        }
        else
        {
            $oddOffset = $verticalSpacing * 0.5;

            return new Vector2($hexID->getColumn() * $horizontalSpacing, $oddOffset + $hexID->getRow() * $verticalSpacing);
        }
    }

    public static function getHexesInPath(HexID $start, HexID $end, float $distanceTreshold, array $hexesToCheck) : array
    {
        $result = [];

        $lineDir = Vector2::subtract($end->asCartesian(), $start->asCartesian());

        for ($i = 0; $i < count($hexesToCheck); $i++)
        {
            $currentHex = $hexesToCheck[$i];

            $projectionSize = Math::pointOntoLineProjectionSize($currentHex->asCartesian(), $start->asCartesian(), $lineDir);

            if ($projectionSize > 0 && $projectionSize < 1)
            {
                $distance = Math::pointFromLineDistance($currentHex->asCartesian(), $start->asCartesian(), $lineDir);

                if ($distance < $distanceTreshold)
                {
                    $result[] = $currentHex;
                }
            }
        }

        return $result;
    }
}