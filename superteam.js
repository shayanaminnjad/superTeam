/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * SuperTeam implementation : © <Shayan Aminnezhad> <shayanaminnjad.sa@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * superteam.js
 *
 * SuperTeam user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

//TODO: animations must account for auto replay (they should not play see reverse.js)

define([
    "dojo", "dojo/_base/declare",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/PieceManager.js",
    g_gamethemeurl + "modules/js/Piece.js",
    g_gamethemeurl + "modules/js/STUIHelpers.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    "ebg/core/gamegui",
    "ebg/counter",
],
    function (dojo, declare, STHexLib, PieceManager, Piece, STHUIHelpers, Board) {
        return declare("bgagame.superteam", ebg.core.gamegui, {
            constructor: function () {
                console.log('superteam constructor');

                this.setupDebugFeatures();
            },

            setup: function (gamedatas) {
                console.log('begin setup');

                console.log(gamedatas);

                this.myColor = gamedatas.players[this.player_id].color === 'FC5414' ? 'orange' : 'blue';
                this.opponentColor = this.myColor === 'orange' ? 'blue' : 'orange';

                for (const player of Object.keys(gamedatas.players)) {
                    if (player !== this.getActivePlayerId()) {
                        this.opponentId = player;
                        break;
                    }
                }

                this.setupPlayerArea(gamedatas);

                this.setupMainBoard(gamedatas);

                this.setupNotifications();

                console.log('end setup');
            },


            ///////////////////////////////////////////////////
            //// Game & client states

            // onEnteringState: this method is called each time we are entering into a new game state.
            //                  You can use this method to perform some user interface changes at this moment.
            //
            onEnteringState: function (stateName, args) {
                console.log('Entering state: ' + stateName, args);

                this.currentStateData = args;

                if (this.isCurrentPlayerActive() == false)
                    return;

                switch (stateName) {
                    case 'playerPieceSetup':
                        this.onPlayerPieceSetup(args);
                    case 'playerTurn':
                        console.log("in playerTurn");
                        break;
                    case 'pieceSelection':
                        this.pieceManager.makePieceListSelectable(args.args.selectablePieces);
                        this.pieceManager.makePieceListSelectable(args.args.selectableExhaustedPieces);
                        this.pieceManager.addMoraleTokenToPieces(args.args.selectableExhaustedPieces);
                        this.highlightPiecesHexes(args.args.selectablePieces.concat(args.args.selectableExhaustedPieces));
                        break;
                    case 'moveAction':
                        if (this.isCurrentPlayerActive()) {
                            document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
                        }
                        this.onMoveAction(args.args);
                        break;
                    case 'passDestinationSelection':
                        this.onPassDestinationSelection(args.args);
                        break;
                    case 'moraleAssignment':
                        this.onMoraleAssignment(args.args);
                        break;
                    case 'dummy':
                        break;
                }
            },

            // onLeavingState: this method is called each time we are leaving a game state.
            //                 You can use this method to perform some user interface changes at this moment.
            //
            onLeavingState: function (stateName) {
                console.log('Leaving state: ' + stateName);

                switch (stateName) {
                    case 'playerPieceSetup':
                        if (this.isCurrentPlayerActive()) {
                            document.querySelectorAll('.availableToPlacePiece').forEach(div => div.classList.remove('availableToPlacePiece'));
                        }
                        break;
                    case 'pieceSelection':
                        if (this.isCurrentPlayerActive()) {
                            this.pieceManager.makeAllPiecesNonSelectable();
                            this.pieceManager.removeAllMoraleTokens();
                            Board.removeHexHighlights();
                        }
                        break;
                    case 'moveAction':
                        if (this.isCurrentPlayerActive()) {
                            document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
                            document.querySelector('.selected').classList.remove('selected');
                        }
                        break;
                    case 'passDestinationSelection':
                        if (this.isCurrentPlayerActive()) {
                            document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
                            Board.removeRequiredPowerVisualization();
                        }
                        break;
                    case 'moraleAssignment':
                        if (this.isCurrentPlayerActive()) {
                            STHUIHelpers.removeCounter('moraleAssign');
                            Board.removeRequiredPowerVisualization();
                        }
                        break;
                    case 'dummy':
                        break;
                }
            },

            // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
            //                        action status bar (ie: the HTML links in the status bar).
            onUpdateActionButtons: function (stateName, args) {
                console.log('onUpdateActionButtons: ' + stateName, args);

                console.log("active player id " + this.getActivePlayerId());

                if (this.isCurrentPlayerActive()) {
                    if (stateName === 'moraleAssignment') {
                        this.statusBar.addActionButton('confirm', () => {
                            let counterValue = STHUIHelpers.getCounterValue('moraleAssign');
                            this.bgaPerformAction("actMoraleAssigned", { amount: counterValue })
                        }, { color: 'primary' });
                    }

                    let clientButtons = args['clientButtons'];

                    if (!clientButtons)
                        return;

                    for (let i = 0; i < clientButtons.length; i++) {
                        let clientButton = clientButtons[i];
                        this.statusBar.addActionButton(clientButton['name'], () => { this.bgaPerformAction(clientButton['action'])}, { color: 'primary' });
                    }
                }
            },

            ///////////////////////////////////////////////////
            //// Utility methods

            setupPlayerArea: function (gamedatas) {
                for (const playerId of Object.keys(gamedatas.players)) {
                    let currentMorale = gamedatas.players[playerId].morale;

                    this.getPlayerPanelElement(playerId).insertAdjacentHTML('beforeend', `
                    <div class ="resources">
                    <span class = "moraleIcon"></span>
                    <span id="playerMorale${playerId}" class= "moraleLabel">${currentMorale}</span>
                    </div>`);

                    this.setPlayerMorale(playerId, currentMorale);
                }
            },

            setPlayerMorale: function (playerId, amount) {
                let moraleEl = document.getElementById(`playerMorale${playerId}`);
                moraleEl.innerHTML = amount;
            },

            getPlayerMorale: function (playerId) {
                let moraleEl = document.getElementById(`playerMorale${playerId}`);
                return parseInt(moraleEl.innerHTML);
            },

            reducePlayerMorale: async function (amount, PlaceSpentOn) {
                let activePlayer = this.getActivePlayerId();
                let currentMorale = this.getPlayerMorale(activePlayer);
                this.setPlayerMorale(activePlayer, currentMorale - amount);

                for (let i = 0; i < amount; i++) {
                    let tempEl = document.createElement("div");
                    tempEl.innerHTML = `<div class="spentMorale"></div>`;
                    let moraleEl = tempEl.firstElementChild;
                    this.getGameAreaElement().appendChild(moraleEl);
                    let playerMoraleEl = document.getElementById(`playerMorale${activePlayer}`);

                    this.placeOnObject(moraleEl, playerMoraleEl);

                    const anim = this.slideToObject(moraleEl, PlaceSpentOn);

                    await this.bgaPlayDojoAnimation(anim);
                }

                document.querySelectorAll(".spentMorale").forEach(el => el.remove());
            },

            setupMainBoard: function (gamedatas) {

                Board.construct(this, gamedatas['ball']);

                this.setPiecesData(gamedatas);

                this.initHexClickListener();
            },

            updatePlayerMorale: function (playerId, newValue) {
                let el = this.document.getElementById(`playerMorale${playerId}`);
                el.innerHTML = `${_("Morale")}: ${newValue}`;
            },

            initHexClickListener: function () {
                document.querySelectorAll('.hex').forEach(hex => {
                    hex.addEventListener('click', e => this.onHexClicked(e));
                    hex.addEventListener('mouseenter', e => this.onHexMouseEnter(e));
                    hex.addEventListener('mouseleave', e => this.onHexMouseLeave(e));
                });
            },

            setPieceClickEvent: function (piece) {
                piece.addEventListener('click', e => this.onPieceClicked(e));
            },

            onHexClicked: async function (evt) {
                //TODO: I should have handlers for it, this design is not flexible. each state can register a handler for click event.

                if (this.isWaitingForServerResponse)
                    return;

                try {
                    if (this.currentStateData.name === 'playerPieceSetup') {
                        if (evt.currentTarget.classList.contains('availableToPlacePiece')) {
                            evt.preventDefault();
                            evt.stopPropagation();

                            var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                            this.isWaitingForServerResponse = true;
                            await this.bgaPerformAction("actPlacedPiece", { placedPiece: this.currentStateData.args.pieceToPlace, hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
                        }
                    }
                    else if (this.currentStateData.name === 'moveAction') {
                        if (evt.currentTarget.classList.contains('selectable')) {
                            evt.preventDefault();
                            evt.stopPropagation();

                            var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                            this.isWaitingForServerResponse = true;
                            await this.bgaPerformAction("actPieceMoved", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
                        }
                    }
                    else if (this.currentStateData.name === 'passDestinationSelection') {
                        if (evt.currentTarget.classList.contains('selectable')) {
                            evt.preventDefault();
                            evt.stopPropagation();

                            var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                            this.isWaitingForServerResponse = true;
                            await this.bgaPerformAction("actDestSelected", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
                        }
                    }
                }
                catch (error) {
                    console.error("Server action failed, exception caught:", error);
                }
                finally {
                    this.isWaitingForServerResponse = false;

                }
            },

            onHexMouseEnter: function (evt) {
                if (this.currentStateData.name === 'passDestinationSelection') {
                    if (evt.currentTarget.classList.contains('selectable')) {

                        this.visualizePowerRequired(this.hexWithBall, Board.convertHexIdToOffsetCoord(evt.currentTarget.id));
                    }
                }
            },

            onHexMouseLeave: function (evt) {
                if (this.currentStateData.name === 'passDestinationSelection') {
                    document.querySelectorAll('.inPath').forEach(el => el.classList.remove('inPath'));
                    document.querySelectorAll('.blocking').forEach(el => el.classList.remove('blocking'));
                    document.querySelectorAll('.powerIndication').forEach(el => el.remove());
                }
            },

            onPieceClicked: async function (evt) {
                if (!this.isWaitingForServerResponse) {
                    if (evt.currentTarget.classList.contains('clickable')) {
                        evt.preventDefault();
                        evt.stopPropagation();

                        try {
                            this.isWaitingForServerResponse = true;
                            let pieceDOMId = evt.currentTarget.id;
                            let numericId = parseInt(pieceDOMId.replace("piece_", ""));

                            await this.bgaPerformAction("actPieceSelected", { pieceId: numericId }, { lock: true, checkAction: true });
                        }
                        catch (error) {
                            console.error("Server action failed, exception caught:", error);
                        }
                        finally {
                            this.isWaitingForServerResponse = false;
                        }
                    }
                }
            },

            setPiecesData: async function (gameDatas) {
                this.pieceManager = PieceManager.getPieceManager();
                this.pieceManager.construct(this);

                let piecesData = gameDatas["player_pieces"];

                for (let i = 0; i < piecesData.length; ++i) {
                    let el = piecesData[i];

                    let color = parseInt(el.owner_id) === this.player_id ? this.myColor : this.opponentColor;
                    let piece = this.pieceManager.createNewPiece(parseInt(el.id), el, color);
                    this.setPieceClickEvent(piece.getDOMElement());

                    if (piece.getPosition() != null) {
                        let offsetCoord = Board.convertOffsetCoordToHexId(piece.getPosition());
                        this.pieceManager.animatePieceFromPlayerBoardToHex(piece.getId(), offsetCoord); 7
                    }
                }
            },

            onMoveAction: function (args) {
                let selectedPiece = args.selectedPiece;
                let moveableHexes = args.moveableHexes;

                let piece = this.pieceManager.getPiece(selectedPiece);
                piece.getDOMElement().classList.add('selected');

                for (let i = 0; i < moveableHexes.length; i++) {
                    let hex = moveableHexes[i];
                    let DOMHexId = Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(hex.hex_row, hex.hex_column));

                    document.getElementById(DOMHexId).classList.add('selectable');
                }
            },

            onPassDestinationSelection: function (args) {

                let allBlockedSpaces = [];

                for (const space of args.blockedSpaces) {
                    allBlockedSpaces.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                this.hexWithBall = STHexLib.createOffsetCoord(args.hexWithBall.hex_row, args.hexWithBall.hex_column)
                allBlockedSpaces.push(Board.convertOffsetCoordToHexId(this.hexWithBall));

                this.makeAllHexesSelectableExcept(allBlockedSpaces);
            },

            makeAllHexesSelectableExcept: function (hexesToExclude) {
                document.querySelectorAll('.hex').forEach(el => el.classList.add('selectable'));

                for (let i = 0; i < hexesToExclude.length; i++) {
                    document.getElementById(hexesToExclude[i]).classList.remove('selectable');
                }
            },

            getRequiredPower(distance, blockingPiecesCount) {
                return Math.ceil(distance / 2) + blockingPiecesCount;
            },

            onMoraleAssignment(args) {
                //args.requiredPower
                STHUIHelpers.createCounter('generalactions', 'moraleAssign', 0, args.maxMoraleAssignable);
                let selectedHex = STHexLib.createOffsetCoord(args.selectedDestination.selected_hex_row, args.selectedDestination.selected_hex_column);
                this.visualizePowerRequired(Board.getHexWithBall(), selectedHex);
            },

            visualizePowerRequired: function (start, end) {

                Board.removeRequiredPowerVisualization();

                let hexesInLine = STHexLib.getHexesInLine(start, end);

                let opponentHexes = this.pieceManager.getAllPiecesOwnedBy(this.opponentId);
                let blockingPieces = STHexLib.getHexsInPath(start, end, 1.2, opponentHexes)

                Board.visualizePowerRequired(hexesInLine, blockingPieces);
            },

            showRollResult: async function (greenDie, whiteDice, moraleSpent, requiredPower) {

                totalPower = greenDie + moraleSpent;

                document.getElementById('mainBoard').insertAdjacentHTML('beforeend', `<div id='diceRollArea'></div>`);

                let diceRollAreaEl = document.getElementById('diceRollArea');

                diceRollAreaEl.insertAdjacentHTML('beforeend', `<span class='greenDie'>${greenDie}</span>`);

                /*
                for (const whiteDie of whiteDice) {
                    diceRollAreaEl.insertAdjacentHTML('beforeend', `<span class='whiteDie'>${whiteDie}</span>`);
                    totalPower += whiteDie;
                }*/

                diceRollAreaEl.insertAdjacentHTML('beforeend', `<span id='moraleColumn'></span>`);
                let moraleColumnEl = document.getElementById('moraleColumn');

                for (let i = 0; i < moraleSpent; ++i)
                    moraleColumnEl.insertAdjacentHTML('beforeend', `<span class='moraleSpent'></span>`);

                if (totalPower >= requiredPower) {
                    diceRollAreaEl.insertAdjacentHTML('beforeend', `<span style='font-size: xx-large;'> >= ${requiredPower}</span>`);
                }
                else {
                    diceRollAreaEl.insertAdjacentHTML('beforeend', `<span style='font-size: xx-large;'> < ${requiredPower}</span>`);
                }

                const TIMEOUT = 3000;
                setTimeout(() => {
                    diceRollAreaEl.remove();
                }, TIMEOUT);

                return this.wait(TIMEOUT);
            },

            highlightPiecesHexes : function(piecesIds)
            {
                for(const pieceId of piecesIds)
                {
                    let piece = this.pieceManager.getPiece(pieceId);
                    Board.highlighHex(Board.convertOffsetCoordToHexId(piece.getPosition()));
                }
            },

            assert: function (condition, message = "Assertion failed") {
                if (!condition) {
                    throw new Error(message);
                }
            },

            ///////////////////////////////////////////////////
            //// state response function
            onPlayerPieceSetup: function (stateData) {
                document.querySelectorAll('.availableToPlacePiece').forEach(div => div.classList.remove('availableToPlacePiece'));

                let availableStartingPositions = stateData.args.availableStartingPositions;

                for (let i = 0; i < availableStartingPositions.length; ++i) {
                    let el = availableStartingPositions[i];

                    let hexColumn = el.hex_column;
                    let hexRow = el.hex_row;

                    let hexId = Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(hexRow, hexColumn));
                    let hexEl = document.getElementById(hexId);

                    hexEl.classList.add('availableToPlacePiece');

                    STHexLib.createOffsetCoord()
                }
            },

            ///////////////////////////////////////////////////
            //// Player's action

            /*
            
                Here, you are defining methods to handle player's action (ex: results of mouse click on 
                game objects).
                
                Most of the time, these methods:
                _ check the action is possible at this game state.
                _ make a call to the game server
            
            */

            ///////////////////////////////////////////////////
            //// Reaction to cometD notifications

            setupNotifications: function () {
                this.bgaSetupPromiseNotifications();
            },


            notif_placedPiece: async function (notifData) {
                let hex = STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column);
                this.pieceManager.getPiece(notifData.placed_piece).setPosition(hex);

                await this.pieceManager.animatePieceFromPlayerBoardToHex(notifData.placed_piece, Board.convertOffsetCoordToHexId(hex));
            },


            notif_movedPiece: async function (notifData) {
                let destHex = STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column);
                let piece = this.pieceManager.getPiece(notifData.movedPiece);

                if (parseInt(notifData.spentMorale) === 1) {
                    await this.reducePlayerMorale(1, piece.getDOMID());
                }

                piece.setPosition(destHex);

                let animations = [];

                const DEST_HEX_ID = Board.convertOffsetCoordToHexId(destHex);
                animations.push(this.slideToObject(piece.getDOMID(), DEST_HEX_ID));

                if (parseInt(notifData.ballMoved)) {
                    Board.setHexWithBall(STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column));
                    animations.push(Board.getMoveBallAnimation(DEST_HEX_ID));
                }

                await this.bgaPlayDojoAnimation(dojo.fx.combine(animations));
            },

            notif_pieceExhausted: function (notifData) {
                let pieceData = this.pieceManager.getPiece(parseInt(notifData.pieceId));
                pieceData.setFreshness(false);
            },

            notif_moraleChanged: function (notifData) {
                let moraleEl = document.getElementById(`playerMorale${notifData.playerId}`);
                moraleEl.innerHTML = notifData.amount;
            },

            notif_diceRolled: function (notifData) {
                return this.showRollResult(notifData.greenDie, notifData.whiteDice, notifData.assignedMorale, notifData.requiredPower);
            },

            notif_successfulPass: function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_failedPass: function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            ///////////////////////////////////////////////////
            //// Debugging features

            setupDebugFeatures: function () {
                let isDev = window.location.hostname === "studio.boardgamearena.com";
                if (!isDev)
                    return;

                console.log("Debug mode is active, to see options type: help");

                window.help = function () {
                    console.log("debug.showCoords: shows the coordinates of the hexes.")
                    console.log("debug.defaultSetup: sets up pieces for both sides.");
                }


                window.debug = {};
                Object.defineProperty(window.debug, 'defaultSetup', {
                    get: function () {
                        return this._val;
                    },
                    set: function (value) {
                        console.log('calling actDebugDefaultPlacePieces');
                        this.bgaPerformAction("actDebugDefaultPlacePieces");
                    }.bind(this)
                })

                Object.defineProperty(window.debug, 'showCoords', {
                    get: function () {
                        return this._val;
                    },
                    set: function (val) {
                        this._val = val;

                        let hexes = document.getElementsByClassName('hex');

                        Array.from(hexes).forEach(hex => {
                            let offsetCoord = Board.convertHexIdToOffsetCoord(hex.id);
                            let cubeCoord = STHexLib.oddqToCubic(offsetCoord);

                            hex.innerHTML = val ? offsetCoord + '<br>' + cubeCoord : "";
                        });
                    }.bind(this)
                })

                Object.defineProperty(window.debug, 'setMorale', {
                    get: function () {
                        return this._val;
                    },
                    set: function (val) {
                        this._val = val;

                        console.log('calling actDebugAddMorale');
                        this.bgaPerformAction("actDebugAddMorale", { amount: val });
                    }.bind(this)
                })

                Object.defineProperty(window.debug, 'moveBall', {
                    get: function () {
                        return this._val;
                    },
                    set: function (input) {
                        this._val = input;
                        console.log(`calling actDebugMoveBall with values (${input.column}, ${input.row})`);
                        this.bgaPerformAction("actDebugMoveBall", { column: input.column, row: input.row });
                    }.bind(this)
                })
            },

            ///////////////////////////////////////////////////
            //// Private data

            //------------------------ board info

            pieceManager: null,

            hexWithBall: null,

            opponentId: null,

            //----------------------- general variables

            currentStateData: null,

            myColor: null,

            opponentColor: null,

            isWaitingForServerResponse: false,
        });
    });
