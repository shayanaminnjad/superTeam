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
    "dojo", "dojo/_base/declare", "dojo/topic",
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
    function (dojo, declare, topic, STHexLib, PieceManager, Piece, STHUIHelpers, Board, DiceUI, PlayerAreaManager, StatesRegistry) {
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
                    if (parseInt(player) !== this.player_id) {
                        this.opponentId = parseInt(player);
                        break;
                    }
                }

                this.userInLeftId = parseInt(gamedatas['user_in_left_id']);
                this.userInRightId = parseInt(gamedatas['user_in_right_id']);

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

            onUpdateActionButtons: function (stateName, args) {
                console.log('onUpdateActionButtons: ' + stateName, args);

                console.log("active players are " + this.getActivePlayers());

                this.clientButtonNeedsConfirm = false;

                if (this.isCurrentPlayerActive() || this.getActivePlayers().includes(this.player_id)) {
                    if (stateName === 'moraleAssignment') {
                        this.statusBar.addActionButton('confirm', () => {
                            let counterValue = STHUIHelpers.getCounterValue('moraleAssign');
                            this.performServerAction("actMoraleAssigned", { amount: counterValue })
                        }, { color: 'primary' });
                    }

                    this.configureClientButtons(args['clientButtons']);
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
                if (this.serverCallInProgress)
                    return;

                await this.currentState.onHexClicked(evt);
            },

            onHexMouseEnter: function (evt) {
                if (this.serverCallInProgress)
                    return;

                if (this.currentStateData.name === 'passDestinationSelection') {
                    if (evt.currentTarget.classList.contains('selectable')) {
                        Board.visualizePowerRequired(Board.getHexWithBall(), Board.convertHexIdToOffsetCoord(evt.currentTarget.id));
                    }
                }
            },

            onHexMouseLeave: function (evt) {
                if (this.serverCallInProgress)
                    return;

                if (this.currentStateData.name === 'passDestinationSelection') {
                    document.querySelectorAll('.inPath').forEach(el => el.classList.remove('inPath'));
                    document.querySelectorAll('.blocking').forEach(el => el.classList.remove('blocking'));
                    document.querySelectorAll('.powerIndication').forEach(el => el.remove());
                }
            },

            configureClientButtons(clientButtons) {
                if (!clientButtons)
                    return;

                for (let i = 0; i < clientButtons.length; i++) {
                    let clientButton = clientButtons[i];
                    this.statusBar.addActionButton(clientButton['name'], async () => {
                        if (!this.clientButtonNeedsConfirm) {
                            this.performServerAction(clientButton['action']);
                        }
                        else {
                            this.statusBar.removeActionButtons();
                            topic.publish('serverActionRequested', clientButton['name']);
                            this.statusBar.addActionButton('confirm', async () => {
                                this.performServerAction(clientButton['action']);
                            });

                            this.statusBar.addActionButton('cancel', () => {
                                this.statusBar.removeActionButtons();
                                topic.publish('requestedActionCanceled', clientButton['name']);
                                this.configureClientButtons(clientButtons);
                            });
                        }
                    }, { color: 'primary' });
                }
            },

            performServerAction: async function (name, args) {
                this.assert(this.serverCallInProgress === false, 'user must not be able to call server action while another is in progress!');

                try {
                    this.serverCallInProgress = true;
                    await this.bgaPerformAction(name, args);
                }
                catch (error) {
                    console.error("Server action failed, exception caught:", error);
                }
                finally {
                    this.serverCallInProgress = false;
                }
            },

            addConfirmationStepToActionButtons: function () {
                this.clientButtonNeedsConfirm = true;
            },

            onPieceClicked: async function (evt) {
                if (!this.serverCallInProgress) {
                    if (evt.currentTarget.classList.contains('clickable')) {
                        evt.preventDefault();
                        evt.stopPropagation();

                        let pieceDOMId = evt.currentTarget.id;
                        let numericId = parseInt(pieceDOMId.replace("piece_", ""));

                        await this.performServerAction("actPieceSelected", { pieceId: numericId });
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

            opponentId: null,

            userInLeftId: null,

            userInRightId: null,

            currentStateData: null,

            currentState: null,

            myColor: null,

            opponentColor: null,

            clientButtonNeedsConfirm: false,

            serverCallInProgress: false,
        });
    });
