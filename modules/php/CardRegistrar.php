<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\CardFactory;
use Bga\Games\SuperTeam\Cards\GameCards\BasicA;
use Bga\Games\SuperTeam\Cards\GameCards\BasicB;
use Bga\Games\SuperTeam\Cards\GameCards\BasicC;
use Bga\Games\SuperTeam\Cards\GameCards\BasicD;
use Bga\Games\SuperTeam\Cards\GameCards\BasicE;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_A_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_A_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_A_2;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_C_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_C_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_C_2;
use Bga\Games\SuperTeam\Cards\GameCards\CoachA;
use Bga\Games\SuperTeam\Cards\GameCards\CoachB;
use Bga\Games\SuperTeam\Cards\GameCards\TeamATactic1;
use Bga\Games\SuperTeam\Cards\GameCards\TeamATactic2;
use Bga\Games\SuperTeam\Cards\GameCards\TeamATactic3;
use Bga\Games\SuperTeam\Cards\GameCards\TeamBTactic1;
use Bga\Games\SuperTeam\Cards\GameCards\TeamBTactic2;
use Bga\Games\SuperTeam\Cards\GameCards\TeamBTactic3;

class CardRegistrar
{
    public function registerCards(CardFactory $cardFactory, Game $game) : void
    {
        //basics
        $cardFactory->registerCard(BasicA::staticId(), fn() => new BasicA($game));
        $cardFactory->registerCard(BasicB::staticId(), fn() => new BasicB($game));
        $cardFactory->registerCard(BasicC::staticId(), fn() => new BasicC($game));
        $cardFactory->registerCard(BasicD::staticId(), fn() => new BasicD($game));
        $cardFactory->registerCard(BasicE::staticId(), fn() => new BasicE($game));

        //team 1
        $cardFactory->registerCard(TeamATactic1::staticId(), fn() => new TeamATactic1($game));
        $cardFactory->registerCard(TeamATactic2::staticId(), fn() => new TeamATactic2($game));
        $cardFactory->registerCard(TeamATactic3::staticId(), fn() => new TeamATactic3($game));

        //team 2
        $cardFactory->registerCard(TeamBTactic1::staticId(), fn() => new TeamBTactic1($game));
        $cardFactory->registerCard(TeamBTactic2::staticId(), fn() => new TeamBTactic2($game));
        $cardFactory->registerCard(TeamBTactic3::staticId(), fn() => new TeamBTactic3($game));

        //coach
        $cardFactory->registerCard(CoachA::staticId(), fn() => new CoachA($game));
        $cardFactory->registerCard(CoachB::staticId(), fn() => new CoachB($game));

        //captain
        //team 1
        $cardFactory->registerCard(Captain_A_0::staticId(), fn() => new Captain_A_0($game));
        $cardFactory->registerCard(Captain_A_1::staticId(), fn() => new Captain_A_1($game));
        $cardFactory->registerCard(Captain_A_2::staticId(), fn() => new Captain_A_2($game));

        //team 3
        $cardFactory->registerCard(Captain_C_0::staticId(), fn() => new Captain_C_0($game));
        $cardFactory->registerCard(Captain_C_1::staticId(), fn() => new Captain_C_1($game));
        $cardFactory->registerCard(Captain_C_2::staticId(), fn() => new Captain_C_2($game));
    }
}