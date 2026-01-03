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
    g_gamethemeurl + "modules/js/STUIHelpers.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/DiceUI.js",
    g_gamethemeurl + "modules/js/PlayerPanelManager.js",
    g_gamethemeurl + "modules/js/States/StatesRegistry.js",
    g_gamethemeurl + "modules/js/PlayerArea.js",
    getLibUrl('bga-animations', '1.x'), // the library uses bga-animations so this is required!
    getLibUrl('bga-cards', '1.x'),
    "ebg/core/gamegui",
    "ebg/counter"
],
    function (dojo, declare, topic, STHexLib, PieceManager, STHUIHelpers, Board, DiceUI, PlayerPanelManager, StatesRegistry, PlayerArea, BgaAnimations, BgaCards) {
        return declare("bgagame.superteam", ebg.core.gamegui, {
            constructor: function () {
                console.log('superteam constructor');

                this.statesRegistry = new StatesRegistry();

                this.setupDebugFeatures();
            },

            setup: function (gamedatas) {
                console.log('begin setup');
                console.log(gamedatas);

                this.gameDatas = gamedatas;

                this.myColor = gamedatas.players[this.player_id].color === 'DD4720' ? 'red' : 'blue';
                this.opponentColor = this.myColor === 'red' ? 'blue' : 'red';

                for (const player of Object.keys(gamedatas.players)) {
                    if (parseInt(player) !== this.player_id) {
                        this.opponentId = parseInt(player);
                        break;
                    }
                }

                this.userInLeftId = parseInt(gamedatas['user_in_left_id']);
                this.userInRightId = parseInt(gamedatas['user_in_right_id']);

                PlayerPanelManager.construct(this, gamedatas);

                this.setupMainBoard(gamedatas);

                this.myPlayerArea = new PlayerArea(this.player_id, gamedatas.players[this.player_id], this);
                this.opponentPlayerArea = new PlayerArea(this.opponentId, gamedatas.players[this.opponentId], this);

                DiceUI.construct(this);

                this.setupNotifications();


                //----------- THIS ARE NEW, NEED TO GET REFACTORED WHEN MAKING THEM WORK!
                // create the animation manager, and bind it to the `gameui.bgaAnimationsActive()` function
                this.animationManager = new BgaAnimations.Manager({
                    animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
                });

                const cardWidth = 200 * 0.7;
                const cardHeight = 280 * 0.7;
                // create the card manager
                this.cardsManager = new BgaCards.Manager({
                    animationManager: this.animationManager,
                    type: 'st-card', //css type
                    cardWidth: cardWidth,
                    cardHeight: cardHeight,
                    cardBorderRadius: '8px',
                    getId: (card) => card.id,
                    setupFrontDiv: (card, div) => {
                        //div.dataset.type = card.type;
                        //div.dataset.typeArg = card.type_arg;

                        //(sha) I need better row column sorting for the atlas
                        //however it is not final yet, since I don't have all of
                        //the cards, so lets make this work for now.
                        rowMultiple = 0; //Basic
                        colMultiple = 0;
                        const tokens = card.type.split('_');
                        const cardCategory = tokens[0];
                        if (cardCategory === 'Basic') {
                            colMultiple = Number(tokens[1].charCodeAt(0) - 'A'.charCodeAt(0));
                        }
                        else if (cardCategory === 'Team') {
                            const teamId = tokens[1];
                            const cardNum = Number(tokens[2]);
                            if (teamId === '0') {
                                if (cardNum <= 1) {
                                    rowMultiple = 0;
                                    colMultiple = 5 + cardNum;
                                }
                                else {
                                    rowMultiple = 1;
                                    colMultiple = cardNum - 2;
                                }
                            }
                            else if (TeamId === '1') {
                                rowMultiple = 1;
                                colMultiple = cardNum + 1;
                            }
                        }
                        else if (cardCategory === 'Captain') {
                            const teamId = tokens[1];
                            const cardNum = Number(tokens[2]);
                            if (teamId === '0') {
                                rowMultiple = 1;
                                colMultiple = 3 + cardNum;
                            }
                            else if (teamId === '2') {
                                rowMultiple = 2;
                                colMultiple = cardNum;
                            }
                        }
                        else if (cardCategory === 'Coach') {
                            const teamId = Number(tokens[1]);
                            rowMultiple = 2;
                            colMultiple = 3 + teamId;
                        }

                        div.style.backgroundPositionX = `calc(100% / 6 * ${colMultiple})`;
                        div.style.backgroundPositionY = `calc(100% / 2 * ${rowMultiple})`;
                        this.addTooltipHtml(div.id, `tooltip of ${card.type}`);
                    },
                    setupBackDiv: (card, div) => {

                        div.style.backgroundPositionX = `calc(100%)`;
                        div.style.backgroundPositionY = `calc(100%)`;
                    },
                    isCardVisible: card => Boolean(card.type) && !card.flipped,
                });

                this.discardStock = new BgaCards.DiscardDeck(this.cardsManager, document.getElementById('myDiscard'), {
                    autoPlace: card => card.location === `${this.player_id}_discard`,
                });

                this.handStock = new BgaCards.HandStock(this.cardsManager, document.getElementById('myHand'), {
                    sort: BgaCards.sort('type', 'type_arg'),
                    cardOverlap: 5,
                    fanShaped: true,
                    floatZIndex: 110,
                    selectableCardStyle: {
                        outlineSize: 2,
                        outlineColor: 'green',
                    },
                    autoPlace: card => card.location === 'hand' && parseInt(card.location_arg) === this.player_id,
                });

                this.handStock.setSelectionMode('single');
                this.handStock.onCardClick = (card) => {
                    console.log('clicked on card ' + card);
                    this.discardStock.addCard(card);
                };

                this.cardsManager.placeCards(Object.values(gamedatas.player_hand));

                //================================================
                //------------------------------------------------
                //================================================


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

                if (this.gameDatas.gamestate.type === 'multipleactiveplayer' && !this.getActivePlayers().includes(this.player_id)) {
                    if (this.currentState != null)
                        this.currentState.onMultiActiveStateFinished();
                }

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

            onHexMouseEnter: async function (evt) {
                if (this.serverCallInProgress)
                    return;

                await this.currentState.onHexEnter(evt);
            },

            onHexMouseLeave: async function (evt) {
                if (this.serverCallInProgress)
                    return;

                this.currentState.onHexLeave(evt);
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
                    //TODO: this is not efficient, however it works perfectly for now.
                    document.querySelectorAll('.selectable, .availableToPlacePiece').forEach((div) => div.classList.add('inputBlocked'));

                    await this.bgaPerformAction(name, args);
                }
                catch (error) {
                    console.error("Server action failed, exception caught:", error);
                    throw error;
                }
                finally {
                    this.serverCallInProgress = false;
                    //TODO: this is not efficient, however it works perfectly for now.
                    document.querySelectorAll('.inputBlocked').forEach((div) => div.classList.remove('inputBlocked'));
                }
            },

            addConfirmationStepToActionButtons: function () {
                this.clientButtonNeedsConfirm = true;
            },

            removeConfirmationStepFromActionButton: function () {
                this.clientButtonNeedsConfirm = false;
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
                    Board.highlightHex(Board.convertOffsetCoordToHexId(piece.getPosition()));
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

            notif_placedPiece: function (notifData) {
                return this.forwardNotification(this.notif_placedPiece.name, notifData);
            },

            notif_cubePlaced: function (notifData) {
                return this.forwardNotification(this.notif_cubePlaced.name, notifData);
            },

            notif_attributeAssignmentFinished: function (notifData) {
                return this.forwardNotification(this.notif_attributeAssignmentFinished.name, notifData);
            },

            notif_movedPiece: function (notifData) {
                return this.forwardNotification(this.notif_movedPiece.name, notifData);
            },

            notif_dribblePerformed: function (notifData) {
                return this.forwardNotification(this.notif_dribblePerformed.name, notifData);
            },

            notif_tacklePerformed: function (notifData) {
                return this.forwardNotification(this.notif_tacklePerformed.name, notifData);
            },

            notif_piecesRefreshed: function (notifData) {
                return this.forwardNotification(this.notif_piecesRefreshed.name, notifData);
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
                let rollResults = [];
                for (const player of Object.keys(notifData["rollResult"])) {
                    rollResults.push(DiceUI.createRollResult(parseInt(player), notifData["rollResult"][player]));
                }

                return DiceUI.showRollAndWait(rollResults, notifData.requiredPower, notifData.blockingPieces, notifData.distance);
            },

            notif_successfulPass: function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_failedPass: function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_shootTargetReveal: async function (notifData) {
                let destHex = STHexLib.createOffsetCoord(notifData.goalieHex.hex_row, notifData.goalieHex.hex_column);
                let piece = this.pieceManager.getPlayerGoalie(parseInt(notifData.defender));

                piece.setPosition(destHex);

                const DEST_HEX_ID = Board.convertOffsetCoordToHexId(destHex);
                await this.bgaPlayDojoAnimation(this.slideToObject(piece.getDOMID(), DEST_HEX_ID));
            },

            notif_shootFailed: async function (notifData) {
                return Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_goalScored: async function (notifData) {
                return PlayerPanelManager.setPlayerScoredGoals(notifData.playerId, notifData.goals);
            },

            notif_resetBoard: async function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
                return this.pieceManager.animateResetPieces(parseInt(notifData.goalieRow));
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
                    console.log("debug.showCoords = 1; shows the coordinates of the hexes.\n" +
                        "debug.defaultSetup= 1; sets up pieces and attributes for both sides. \n" +
                        "debug.setMorale= x; sets morale for current player\n" +
                        "debug.moveBall= {column: x, row:y}; sets ball position\n" +
                        "debug.defaultAttribute = 1; fast attribute setup");

                    return 'success';
                };


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


                Object.defineProperty(window.debug, 'defaultAttribute', {
                    get: function () {
                        return this._val;
                    },
                    set: function (input) {
                        this._val = input;
                        console.log(`calling actDebugDefaultSetup`);
                        this.bgaPerformAction("actDebugDefaultSetup");
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

            myPlayerArea: null,

            opponentPlayerArea: null,

            userInLeftId: null,

            userInRightId: null,

            currentStateData: null,

            currentState: null,

            myColor: null,

            opponentColor: null,

            clientButtonNeedsConfirm: false,

            serverCallInProgress: false,

            gameDatas: null,

            animationManager: null,

            cardsManager: null,

            handStock: null,

            discardStock: null
        });
    });
