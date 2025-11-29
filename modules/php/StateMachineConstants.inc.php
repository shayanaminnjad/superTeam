<?php

//State names
const ST_BGA_GAME_SETUP = 1;

const ST_PLAYER_PIECE_SETUP = 2;
const ST_SETUP_COMPLETE = 3;
const ST_BEFORE_PLAYER_TURN = 4;
const ST_PLAYER_TURN = 5;
const ST_NEXT_PLAYER_TURN = 6;
const ST_PIECE_SELECTION = 7;
const ST_MOVE_ACTION = 8;
const ST_MOVE_FINISHED = 9;
const ST_PASS_DEST_SELECTION = 10;
const ST_PASS_CONFIRMATION = 11;
const ST_PASS_PERFORM = 12;
const ST_MORALE_ASSIGMENT = 13;
const ST_DICE_ROLL = 14;

const ST_END_GAME = 99; 

//transition signals
const TR_AUTO_NEXT = '';
const TR_MOVE_ACTION = 'moveAction';
const TR_MOVE_DONE = 'moveDone';
const TR_PASS_BALL_ACTION = 'passBallAction';
const TR_SETUP_COMPLETE = 'setupComplete';
const TR_MORALE_ASSIGN = 'moraleAssign';
const TR_DICE_ROLL = 'rollDice';
const TR_PLAYER_PIECE_SETUP = 'playerPieceSetup';
const TR_PLAYER_TURN = 'playerTurn';
const TR_CANCEL = 'cancel';
