<?php

namespace Bga\Games\SuperTeam;

use Bga\Games\SuperTeam\Cards\CardFactory;
use Bga\Games\SuperTeam\Cards\GameCards\BasicA;
use Bga\Games\SuperTeam\Cards\GameCards\BasicB;
use Bga\Games\SuperTeam\Cards\GameCards\BasicC;
use Bga\Games\SuperTeam\Cards\GameCards\BasicD;
use Bga\Games\SuperTeam\Cards\GameCards\BasicE;
use Bga\Games\SuperTeam\Cards\GameCards\Captain14_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain14_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain14_2;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_4_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_4_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_4_2;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_6_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_6_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_6_2;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_A_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_A_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_A_2;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_C_0;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_C_1;
use Bga\Games\SuperTeam\Cards\GameCards\Captain_C_2;
use Bga\Games\SuperTeam\Cards\GameCards\CoachA;
use Bga\Games\SuperTeam\Cards\GameCards\CoachB;
use Bga\Games\SuperTeam\Cards\GameCards\CoachC;
use Bga\Games\SuperTeam\Cards\GameCards\CoachD;
use Bga\Games\SuperTeam\Cards\GameCards\Fatigue;
use Bga\Games\SuperTeam\Cards\GameCards\TeamATactic1;
use Bga\Games\SuperTeam\Cards\GameCards\TeamATactic2;
use Bga\Games\SuperTeam\Cards\GameCards\TeamATactic3;
use Bga\Games\SuperTeam\Cards\GameCards\TeamBTactic1;
use Bga\Games\SuperTeam\Cards\GameCards\TeamBTactic2;
use Bga\Games\SuperTeam\Cards\GameCards\TeamBTactic3;
use Bga\Games\SuperTeam\Cards\GameCards\TeamCTactic1;
use Bga\Games\SuperTeam\Cards\GameCards\TeamCTactic2;
use Bga\Games\SuperTeam\Cards\GameCards\TeamCTactic3;
use Bga\Games\SuperTeam\Cards\GameCards\TeamDTactic1;
use Bga\Games\SuperTeam\Cards\GameCards\TeamDTactic2;
use Bga\Games\SuperTeam\Cards\GameCards\TeamDTactic3;
use Bga\Games\SuperTeam\PermanentPowers\Captains\CaptainPower_14;

class CardRegistrar
{
    public function registerCards(CardFactory $cardFactory, Game $game) : void
    {
        //fatigue
        $cardFactory->registerCard(Fatigue::staticId(), fn() => new Fatigue($game));

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

        //team 3
        $cardFactory->registerCard(TeamCTactic1::staticId(), fn() => new TeamCTactic1($game));
        $cardFactory->registerCard(TeamCTactic2::staticId(), fn() => new TeamCTactic2($game));
        $cardFactory->registerCard(TeamCTactic3::staticId(), fn() => new TeamCTactic3($game));

        //team 4
        $cardFactory->registerCard(TeamDTactic1::staticId(), fn() => new TeamDTactic1($game));
        $cardFactory->registerCard(TeamDTactic2::staticId(), fn() => new TeamDTactic2($game));
        $cardFactory->registerCard(TeamDTactic3::staticId(), fn() => new TeamDTactic3($game));

        //coach
        $cardFactory->registerCard(CoachA::staticId(), fn() => new CoachA($game));
        $cardFactory->registerCard(CoachB::staticId(), fn() => new CoachB($game));
        $cardFactory->registerCard(CoachC::staticId(), fn() => new CoachC($game));
        $cardFactory->registerCard(CoachD::staticId(), fn() => new CoachD($game));

        //captains
        //captain 1
        $cardFactory->registerCard(Captain_A_0::staticId(), fn() => new Captain_A_0($game));
        $cardFactory->registerCard(Captain_A_1::staticId(), fn() => new Captain_A_1($game));
        $cardFactory->registerCard(Captain_A_2::staticId(), fn() => new Captain_A_2($game));

        //captain 3
        $cardFactory->registerCard(Captain_C_0::staticId(), fn() => new Captain_C_0($game));
        $cardFactory->registerCard(Captain_C_1::staticId(), fn() => new Captain_C_1($game));
        $cardFactory->registerCard(Captain_C_2::staticId(), fn() => new Captain_C_2($game));

        //captain 4
        $cardFactory->registerCard(Captain_4_0::staticId(), fn()=> new Captain_4_0($game));
        $cardFactory->registerCard(Captain_4_1::staticId(), fn()=> new Captain_4_1($game));
        $cardFactory->registerCard(Captain_4_2::staticId(), fn()=> new Captain_4_2($game));

        //captain 6
        $cardFactory->registerCard(Captain_6_0::staticId(), fn()=> new Captain_6_0($game));
        $cardFactory->registerCard(Captain_6_1::staticId(), fn()=> new Captain_6_1($game));
        $cardFactory->registerCard(Captain_6_2::staticId(), fn()=> new Captain_6_2($game));

        //captain 14
        $cardFactory->registerCard(Captain14_0::staticId(), fn()=> new Captain14_0($game));
        $cardFactory->registerCard(Captain14_1::staticId(), fn()=> new Captain14_1($game));
        $cardFactory->registerCard(Captain14_2::staticId(), fn()=> new Captain14_2($game));
    }
}