<?php

namespace Bga\Games\SuperTeam\Math;

class HexShape
{
    private Vector2 $right;
    private Vector2 $bottomRight;
    private Vector2 $bottomLeft;
    private Vector2 $left;
    private Vector2 $topLeft;
    private Vector2 $topRight;

    public function __construct(Vector2 $hexCenter, HexInfo $hexInfo)
    {
        $this->right = new Vector2($hexCenter->x + $hexInfo->size, $hexCenter->y);
        $this->left =  new Vector2($hexCenter->x - $hexInfo->size, $hexCenter->y);
        $this->topRight = new Vector2($hexCenter->x + $hexInfo->size * 0.5, $hexCenter->y - $hexInfo->inRadius);
        $this->topLeft = new Vector2($hexCenter->x - $hexInfo->size * 0.5, $hexCenter->y - $hexInfo->inRadius);
        $this->bottomRight = new Vector2($hexCenter->x + $hexInfo->size * 0.5, $hexCenter->y + $hexInfo->inRadius);
        $this->bottomLeft = new Vector2($hexCenter->x - $hexInfo->size * 0.5, $hexCenter->y + $hexInfo->inRadius);
    }

    public function getSATAxis(): array
    {
        $rotation = pi() * 0.5;


        return [
            Math::rotateVector($this->topRight->subtract($this->right), $rotation)->normalize(),
            Math::rotateVector($this->right->subtract($this->bottomRight), $rotation)->normalize(),
            Math::rotateVector($this->bottomLeft->subtract($this->bottomRight), $rotation)->normalize()
        ];
    }

    public function getVertices(): array
    {
        return [
            $this->right,
            $this->bottomRight,
            $this->bottomLeft,
            $this->left,
            $this->topLeft,
            $this->topRight
        ];
    }
}
