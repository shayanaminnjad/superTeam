<?php

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\StateType;
use Bga\Games\SuperTeam\ClientButtonColor;
use Bga\Games\SuperTeam\ClientButtonParams;
use Bga\Games\SuperTeam\Dice\GameDice;
use Bga\Games\SuperTeam\Game;
use Bga\Games\SuperTeam\NotificationAdapter;

class DiceReRollState extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_DICE_REROLL,
            name: "diceReRollState",
            type: StateType::ACTIVE_PLAYER,
            description: clienttranslate('${actplayer} must reroll or pass'),
            descriptionMyTurn: clienttranslate('${you} must reroll or pass'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function selfGetArgs(): array
    {
        $activePlayerId = intval($this->game->getActivePlayerId());
        $args = DiceRollServices::getClientData($this->game, $activePlayerId);

        $this->addClientButton(clienttranslate("Reroll"), [$this, 'actReroll']);
        $this->addClientButton(clienttranslate("Pass"), [$this, 'actPass'], [],
            new ClientButtonParams(ClientButtonColor::ALERT));

        return $args;
    }

    #[PossibleAction]
    public function actPass(int $activePlayerId) : string
    {
        $rollData = DiceRollServices::getRollData($activePlayerId);

        assert(strlen($rollData->finishTransition) > 0, "no transition is stored for dice roll finish");

        return $rollData->finishTransition;
    }

    #[PossibleAction]
    public function actReroll(int $activePlayerId) : string
    {
        DiceRollServices::storeDiceRerolledThisTurn(true);
        $rollData = DiceRollServices::getRollData($activePlayerId);

        $playerRollResult = DiceRollServices::getRollResult($activePlayerId);
        $whiteDice = $playerRollResult->getWhiteDice();

        for($i = 0; $i < count($whiteDice); $i++)
        {
            if ($whiteDice[$i] === -1)
                $whiteDice[$i] =  GameDice::whiteDie()->roll();
        }

        $playerRollResult->setWhiteDice($whiteDice);

        DiceRollServices::storeRollResult($activePlayerId, $playerRollResult);

        $notifData = DiceRollServices::getClientData($this->game, $activePlayerId);
        $notifData['player_name'] = NotificationAdapter::getInstance()->getPlayerNameById($activePlayerId);
        $notifData['diceLog'] = "";
        $notifData['i18n'] = ["actionName"];
        $notifData['preserve'] = ["rollResult", "distance", "blockingPieces", "requiredPower", "actionName", "skipEnabledPlayerId"];

        NotificationAdapter::getInstance()->notifyAll(
            "reroll",
            clienttranslate('${player_name} rerolled negative dice<br/>${diceLog}'),
            $notifData
        );

        assert(strlen($rollData->finishTransition) > 0, "no transition is stored for dice roll finish");

        return $rollData->finishTransition;
    }

    function zombie(int $playerId): string
    {
        return $this->actPass($playerId);
    }
}