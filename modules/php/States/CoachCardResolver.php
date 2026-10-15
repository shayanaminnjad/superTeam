<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\Cards\TacticContext;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\States\PlayerBaseState;

class CoachCardResolver extends PlayerBaseState
{
    function __construct(
        Game $game,
    ) {
        parent::__construct(
            game: $game,
            name: 'coachCardResolver',
            id: ST_COACH_CARD_RESOLVER,
            type: StateType::ACTIVE_PLAYER,

            // optional
            description: clienttranslate('${actplayer} should resolve coach card'),
            descriptionMyTurn: clienttranslate('${actplayer} resolve coach card'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $this->addClientButton(\clienttranslate("Do One Move"), [$this, "actMove"]);
        $this->addClientButton(\clienttranslate("Pass"), [$this, "actPass"], [],
            new ClientButtonParams(ClientButtonColor::ALERT));

        return [];
    }

    #[PossibleAction]
    public function actMove(): string
    {
        return MovePieceSelection::class;
    }

    #[PossibleAction]
    public function actPass(): string
    {
        return BeforeCardSelectionReset::class;
    }

    function zombie(int $playerId): string
    {
        return $this->actPass();
    }
}