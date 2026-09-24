<?php

namespace Bga\Games\SuperTeam;
final class ClientButtonParams
{
    public function __construct(
        public readonly ClientButtonColor $color = ClientButtonColor::PRIMARY,
        public readonly ?string $confirmMessage = null,
        public readonly bool $disabled = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'color' => $this->color,
            'confirm' => $this->confirmMessage,
            'disabled' => $this->disabled,
        ];
    }

    public function needsConfirmation(): bool
    {
        return $this->confirmMessage !== null;
    }
}

enum ClientButtonColor: string
{
    case PRIMARY = 'primary';
    case ALERT = 'alert';
}