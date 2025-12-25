<?php

declare(strict_types=1);

namespace Bga\Games\SuperTeam\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\PossibleAction;
use Bga\Games\SuperTeam\DBAdapter;
use Bga\Games\SuperTeam\Game;
use BgaUserException;

class TeamAttributeAssignment extends PlayerBaseState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct(
            $game,
            id: ST_TEAM_ATTRIBUTE_ASSIGNMENT,
            name: 'teamAttributeAssignment',
            type: StateType::MULTIPLE_ACTIVE_PLAYER,

            // optional
            description: clienttranslate('actplayer must assign coach markers'),
            descriptionMyTurn: clienttranslate('you must assign your coach markers'),
            transitions: [],
            updateGameProgression: false,
            initialPrivate: null,
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        DBAdapter::getInstance()->setGameStateValue('isAttributesAssignmentFinished', 0);
    }

    public function selfGetArgs(): array
    {
        $db = DBAdapter::getInstance();

        $playersAttributes = $db->getObjectListFromDB("SELECT `player_id`, `team_attr_pass`, `team_attr_shoot`,`team_attr_dribble`, `team_attr_tackle` , `team_attr_gk` FROM `player`");
        
        $TOTAL_CUBES = 6;

        $result= [];
        for ($i = 0; $i < \count($playersAttributes); $i++) 
        {
            $currentPlayerAttributes = $playersAttributes[$i];
            $id = $currentPlayerAttributes['player_id'];
            unset($currentPlayerAttributes['player_id']);

            $placedCubes = 0;

            foreach($currentPlayerAttributes as $attr)
            {
                $placedCubes += (int)$attr;
            }

            $result[$id]= $TOTAL_CUBES - $placedCubes;
        }

        return $result;
    }

    #[PossibleAction]
    public function actPlacedAttrCube(int $skillId): void
    {
        $TOTAL_CUBES = 6;

        $currentPlayer = \intval($this->game->getCurrentPlayerId());

        $db = DBAdapter::getInstance();
        $playersAttributes = $db->getObjectFromDB("SELECT `team_attr_pass`, `team_attr_shoot`,`team_attr_dribble`, `team_attr_tackle` , `team_attr_gk` FROM `player` WHERE `player_id` = $currentPlayer");

        $placedCubes = 0;

        foreach ($playersAttributes as $attr) 
        {
            $placedCubes += (int)$attr;
        }

        if ($placedCubes == $TOTAL_CUBES) 
        {
            throw new BgaUserException("You cannot place more than 6 cubes!");
        }

        //0 = pass
        //1 = shoot
        //2 = dribble
        //3 = tackle
        //4 = gk

        $attrName = "team_attr_";
        switch ($skillId) {
            case 0:
                $attrName .= "pass";
                break;
            case 1:
                $attrName .= "shoot";
                break;
            case 2:
                $attrName .= "dribble";
                break;
            case 3:
                $attrName .= "tackle";
                break;
            case 4:
                $attrName .= "gk";
                break;
            default:
                throw new BgaUserException("Invalid skill Id passed! $skillId");
        }

        $attrValue = $playersAttributes[$attrName] + 1;

        if ($attrValue > 2) {
            throw new BgaUserException("You cannot assign more than two cubes to an attribute!");
        }

        $db->DbQuery("UPDATE player SET `$attrName` = $attrValue WHERE `player_id` = $currentPlayer");

        $this->game->notify->player(
            $currentPlayer,
            "cubePlaced",
            "",
            ["skill" => $skillId]
        );
        
        $cubesRemaining = $TOTAL_CUBES - ($placedCubes + 1);

        if ($cubesRemaining == 0) 
        {
            $this->gamestate->setPlayerNonMultiactive($currentPlayer, TeamAttributesReveal::class);
        } 
    }

    function zombie(int $playerId): string
    {
        // the code to run when the player is a Zombie
        return '';
    }
}
