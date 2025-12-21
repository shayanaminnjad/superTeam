<?php

namespace Bga\Games\SuperTeam\HexLib;

use Bga\Games\SuperTeam\HexID;
use Bga\Games\SuperTeam\Math\HexInfo;
use Bga\Games\SuperTeam\Math\HexShape;
use Bga\Games\SuperTeam\Math\RectangleShape;
use Bga\Games\SuperTeam\Math\Vector2;
use Bga\Games\SuperTeam\Math\Math;
use Bga\Games\SuperTeam\Utility\Logger;

enum Direction: int
{
    case NORTHEAST = 0;
    case NORTH = 1;
    case NORTHWEST = 2;
    case SOUTHWEST = 3;
    case SOUTH = 4;
    case SOUTHEAST = 5;
    case COUNT = 6;
}


class HexLib
{
    //TODO: This should be really set from outside, but for now it is ok.
    private static ?OffsetExtent $offsetExtent = null;
    private static HexInfo $hexInfo;

    public static function getOffsetExtent(): OffsetExtent
    {
        return self::$offsetExtent ??= new OffsetExtent(0, 16, 0, 9, 0, 10);
    }

    public static function SetHexInfo(HexInfo $hexInfo)
    {
        HexLib::$hexInfo = $hexInfo;
    }

    public static function GetHexInfo(): HexInfo
    {
        return HexLib::$hexInfo;
    }

    private static ?array $CUBIC_DIRECTIONS = null;

    public static function getCubicDirections(): array
    {
        return self::$CUBIC_DIRECTIONS ??= [
            new CubicCoordinate(1, -1, 0),
            new CubicCoordinate(0, -1, 1),
            new CubicCoordinate(-1, 0, 1),
            new CubicCoordinate(-1, 1, 0),
            new CubicCoordinate(0, 1, -1),
            new CubicCoordinate(1, 0, -1),
        ];
    }

    public static function offsetToCubic(HexID $hexID): CubicCoordinate
    {
        $q = $hexID->getColumn();
        $r = $hexID->getRow() - floor(($q - ($q & 1)) * 0.5);
        $s = -$q - $r;

        return new CubicCoordinate($q, $r, $s);
    }

    public static function cubicToHexID(CubicCoordinate $cubic): HexID
    {
        $c = $cubic->getQ();
        $r = $cubic->getR() + floor(($c - ($c & 1)) * 0.5);

        return new HexID($c, $r);
    }

    public static function cubicLerp(CubicCoordinate $a, CubicCoordinate $b, float $t): CubicCoordinate
    {
        return new CubicCoordinate(
            Math::lerp($a->getQ(), $b->getQ(), $t),
            Math::lerp($a->getR(), $b->getR(), $t),
            Math::lerp($a->getS(), $b->getS(), $t)
        );
    }

    public static function cubicRound(CubicCoordinate $coordinate): CubicCoordinate
    {
        $qRound = round($coordinate->getQ());
        $rRound = round($coordinate->getR());
        $sRound = round($coordinate->getS());

        $qDiff = abs($qRound - $coordinate->getQ());
        $rDiff = abs($rRound - $coordinate->getR());
        $sDiff = abs($sRound - $coordinate->getS());

        if ($qDiff > $rDiff && $qDiff > $sDiff) {
            $qRound = -$rRound - $sRound;
        } else if ($rDiff > $sDiff) {
            $rRound = -$qRound - $sRound;
        } else {
            $sRound = -$qRound - $rRound;
        }

        return new CubicCoordinate($qRound, $rRound, $sRound);
    }

    public static function HexIDToCartesian(HexID $hexID): Vector2
    {
        $horizontalSpacing = self::$hexInfo->horizontalSpacing;
        $verticalSpacing = 2 * self::$hexInfo->inRadius;

        $isEvenColumn = ($hexID->getColumn() + 1) % 2 == 1;

        if ($isEvenColumn) 
        {
            return new Vector2($hexID->getColumn() * $horizontalSpacing, $hexID->getRow() * ($verticalSpacing + self::$hexInfo->verticalGap));
        } 
        else 
        {
            $oddOffset = self::$hexInfo->oddOffset;
            return new Vector2($hexID->getColumn() * $horizontalSpacing, $oddOffset + $hexID->getRow() * ($verticalSpacing + self::$hexInfo->verticalGap));
        }
    }

    public static function getHexesInPath(HexID $start, HexID $end, float $halfLineThickness, array $hexesToCheck): array
    {
        $result = [];

        $startCartesian = $start->asCartesian();
        $endCartesian = $end->asCartesian();

        $lineDir = $endCartesian->subtract($startCartesian);

        $rec = new RectangleShape($startCartesian, $lineDir, $halfLineThickness);

        foreach ($hexesToCheck as $hex) {
            $hexShape = new HexShape($hex->asCartesian(), HexLib::$hexInfo);

            if (HexLib::satIntersect($hexShape, $rec)) {
                $result[] = $hex;
            }
        }


        return $result;
    }

    public static function satIntersect(HexShape $hexShape, RectangleShape $rectShape): bool
    {
        $testingAxis = [...$hexShape->getSATAxis(), ...$rectShape->getSATAxis()];

        $hexVerticesArray = $hexShape->getVertices();
        $rectVerticesArray = $rectShape->getVertices();

        foreach ($testingAxis as $axis) {
            $hexMinMaxProj = Math::getMinMaxProjections($hexVerticesArray, $axis);
            $rectMinMaxProj = Math::getMinMaxProjections($rectVerticesArray, $axis);

            if ($hexMinMaxProj->max < $rectMinMaxProj->min || $hexMinMaxProj->min > $rectMinMaxProj->max)
                return false;
        }

        return true;
    }

    public static function getMinMaxProjections(array $verticesArray, array $axis) {}

    public static function getOffsetDistance(HexID $start, HexID $end)
    {
        $startCubic = self::offsetToCubic($start);
        $endCubic = self::offsetToCubic($end);

        $diff = $endCubic->subtract($startCubic);

        return (abs($diff->getQ()) + abs($diff->getR()) + abs($diff->getS())) * 0.5;
    }

    public static function getHexesInLine(HexID $start, HexID $end): array
    {
        $result = [];

        if ($start->equals($end)) {
            $result[] = $end;

            return $result;
        }

        $startCubic = self::offsetToCubic($start);
        $endCubic = self::offsetToCubic($end);

        $distance = self::getOffsetDistance($start, $end);

        for ($i = 0; $i <= $distance; $i++) {
            $cubicCoordinateOnLine = self::cubicRound(self::cubicLerp($startCubic, $endCubic, 1.0 / $distance * $i));

            $result[] = self::cubicToHexID($cubicCoordinateOnLine);
        }

        return $result;
    }

    public static function getNeighbour(CubicCoordinate $cubicCoordinate, CubicCoordinate $direction): CubicCoordinate
    {
        return $cubicCoordinate->add($direction);
    }

    public static function getNeighbours(CubicCoordinate $cubicCoord, int $dist): array
    {
        $result = [];

        $directions = self::getCubicDirections();
        $offsetExtent = self::getOffsetExtent();

        for ($currDist = 1; $currDist <= $dist; $currDist++) {
            $currentHex = self::getNeighbour($cubicCoord, $directions[Direction::SOUTH->value]->scale($currDist));

            for ($currDir = 0; $currDir < Direction::COUNT->value; $currDir++) {
                for ($i = 0; $i < $currDist; $i++) {
                    $currentHex = self::getNeighbour($currentHex, $directions[$currDir]);
                    if ($offsetExtent->isInsideExtent(self::cubicToHexID($currentHex))) {
                        $result[] = $currentHex;
                    }
                }
            }
        }

        return $result;
    }
}
