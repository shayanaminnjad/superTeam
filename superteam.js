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
    g_gamethemeurl + "modules/js/DiceUI.js",
    g_gamethemeurl + "modules/js/PlayerAreaManager.js",
    g_gamethemeurl + "modules/js/States/StatesRegistry.js",
    "ebg/core/gamegui",
    "ebg/counter",
],
    function (dojo, declare, STHexLib, PieceManager, Piece, STHUIHelpers, Board, DiceUI, PlayerAreaManager, StatesRegistry) {
        return declare("bgagame.superteam", ebg.core.gamegui, {
            constructor: function () {
                console.log('superteam constructor');

                this.statesRegistry = new StatesRegistry();

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

                PlayerAreaManager.construct(this, gamedatas);

                this.setupMainBoard(gamedatas);

                DiceUI.construct(this);

                this.setupNotifications();

                console.log('end setup');
            },


            ///////////////////////////////////////////////////
            //// Game & client states

            onEnteringState: function (stateName, args) {
                console.log('Entering state: ' + stateName, args);

                this.currentStateData = args;
                this.currentState = this.statesRegistry.getState(stateName, this, args);

                this.currentState.onEnter(args.args);
            },

            onLeavingState: function (stateName) {
                console.log('Leaving state: ' + stateName);

                this.currentState.onLeave();
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
                        this.statusBar.addActionButton(clientButton['name'], () => { this.bgaPerformAction(clientButton['action']) }, { color: 'primary' });
                    }
                }
            },

            ///////////////////////////////////////////////////
            //// Utility methods

            setupMainBoard: function (gamedatas) {

                Board.construct(this, gamedatas['ball']);

                this.setPiecesData(gamedatas);

                this.initHexClickListener();
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

                        this.visualizePowerRequired(Board.getHexWithBall(), Board.convertHexIdToOffsetCoord(evt.currentTarget.id));
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

            getRequiredPower(distance, blockingPiecesCount) {
                return Math.ceil(distance / 2) + blockingPiecesCount;
            },

            //(sha) TODO: this function must be inside a file accessible to all the states.
            visualizePowerRequired: function (start, end) {

                Board.removeRequiredPowerVisualization();

                let hexesInLine = STHexLib.getHexesInLine(start, end);

                let opponentHexes = this.pieceManager.getAllPiecesOwnedBy(this.opponentId);
                let blockingPieces = STHexLib.getHexsInPath(start, end, 1.2, opponentHexes)

                Board.visualizePowerRequired(hexesInLine, blockingPieces);
            },

            highlightPiecesHexes: function (piecesIds) {
                for (const pieceId of piecesIds) {
                    let piece = this.pieceManager.getPiece(pieceId);
                    Board.highlighHex(Board.convertOffsetCoordToHexId(piece.getPosition()));
                }
            },

            assert: function (condition, message = "Assertion failed") {
                if (!condition) {
                    throw new Error(message);
                }
            },

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
                return this.forwardNotification(this.notif_placedPiece.name, notifData);
            },

            notif_movedPiece: async function (notifData) {
                return this.forwardNotification(this.notif_movedPiece.name, notifData);
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
                return DiceUI.showRollAndWait(notifData.greenDie, notifData.whiteDice, notifData.assignedMorale, notifData.requiredPower);
            },

            notif_successfulPass: function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_failedPass: function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            forwardNotification(funcName, notifData) {
                this.assert(this.currentState && typeof this.currentState[funcName] === "function", `Server call "${funcName}" not handled in current state`);

                return this.currentState[funcName](notifData);
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

            statesRegistry: null,

            pieceManager: null,

            hexWithBall: null,

            opponentId: null,

            currentStateData: null,

            currentState: null,

            myColor: null,

            opponentColor: null,

            isWaitingForServerResponse: false,
        });
    });
