<?php

namespace Bga\Games\SuperTeam\Math;

class RectangleShape
{
    private $topLeft;
    private $topRight;
    private $bottomRight;
    private $bottomLeft;
    private Vector2 $normalizedLineDir;
    private Vector2 $perp;

    public function __construct(Vector2 $lineStart, Vector2 $lineDir, float $halfLineThickness)
    {
        $this->normalizedLineDir = $lineDir->normalize();
        $this->perp = Math::rotateVector($this->normalizedLineDir, pi() * 0.5);

        $this->topLeft = $lineStart->add($this->perp->scale($halfLineThickness));
        $this->topRight = $this->topLeft->add($lineDir);
        $this->bottomLeft = $lineStart->add($this->perp->scale(-$halfLineThickness));
        $this->bottomRight = $this->bottomLeft->add($lineDir);
    }

    public function getSATAxis(): array
    {
        return [
            $this->normalizedLineDir,
            $this->perp
        ];
    }

    public function getVertices(): array
    {
        return [
            $this->topLeft,
            $this->topRight,
            $this->bottomRight,
            $this->bottomLeft
        ];
    }
}
