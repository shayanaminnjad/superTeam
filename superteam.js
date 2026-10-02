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
    g_gamethemeurl + "modules/js/STCardsManager.js",
    g_gamethemeurl + "modules/js/TrackerBoard.js",
    g_gamethemeurl + "modules/js/GameEventProcessor.js",
    getLibUrl('bga-animations', '1.x'),
    "ebg/core/gamegui",
    "ebg/counter"
],
    function (dojo, declare, topic, STHexLib, PieceManager, STHUIHelpers, Board, DiceUI, PlayerPanelManager, StatesRegistry, PlayerArea, STCardsManager, TrackerBoard, GameEventProcessor, BgaAnimations) {
        return declare("bgagame.superteam", ebg.core.gamegui, {
            constructor: function () {
                console.log('superteam constructor');

                this.statesRegistry = new StatesRegistry();
            },

            setup: function (gamedatas) {
                console.log('begin setup');
                console.log(gamedatas);

                this.gameDatas = gamedatas;

                this.cardDefinitions = gamedatas.card_definitions;

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

                this.initAnimationsManager();

                PlayerPanelManager.construct(this, gamedatas);

                let orangeGoals = gamedatas.players[this.player_id].score;
                let blueGoals = gamedatas.players[this.opponentId].score;
                if (this.userInLeftId !== this.player_id)
                {
                    let temp = orangeGoals;
                    orangeGoals = blueGoals;
                    blueGoals = temp;
                }

                TrackerBoard.construct(this, gamedatas.time_index, orangeGoals, blueGoals);

                this.pieceManager = PieceManager.getPieceManager();
                this.pieceManager.construct(this);

                GameEventProcessor.construct(this, this.pieceManager);

                this.setupMainBoard(gamedatas);

                this.myPlayerArea = new PlayerArea(this.player_id, gamedatas.players[this.player_id], this);
                this.opponentPlayerArea = new PlayerArea(this.opponentId, gamedatas.players[this.opponentId], this);

                DiceUI.construct(this);

                this.cardsManager = new STCardsManager(this,
                    Object.values(gamedatas.player_hand).
                    concat(Object.values(gamedatas.player_discard)).
                    concat(gamedatas.player_deck).concat(Object.values(gamedatas.supply)).concat(Object.values(gamedatas.other_discard)));

                this.cardsManager.setHandVisibility(this.cardsManager.getCardsInHand() > 0);
                
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

                if (this.gameDatas.gamestate.type === 'multipleactiveplayer' && !this.getActivePlayers().includes(this.player_id)) {
                    if (this.currentState != null)
                        this.currentState.onMultiActiveStateFinished();
                }

                if (this.isCurrentPlayerActive() || this.getActivePlayers().includes(this.player_id)) {
                    if (stateName === 'moraleAssignment') {
                        this.statusBar.addActionButton(_('Confirm'), () => {
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

                //Left seat sits on the low-column end, which projects to the top in
                //portrait mode. Flipping for that seat puts the viewing player's own
                //side at the bottom instead, near their own player panel.
                const flipForViewer = this.player_id === this.userInLeftId;

                Board.construct(this, gamedatas['ball'], flipForViewer);

                this.setPiecesData(gamedatas);

                this.initHexClickListener();

                this.initBoardRotateButton();
            },

            initBoardRotateButton: function () {
                const rotateButton = document.createElement('button');
                rotateButton.id = 'boardRotateToggle';
                rotateButton.innerHTML = '&#8635;';
                rotateButton.setAttribute('aria-label', 'Rotate board to portrait');

                Board.getMainBoardElement().insertAdjacentElement('afterend', rotateButton);

                rotateButton.addEventListener('click', () => {
                    const nextOrientation = Board.getOrientation() === 'landscape' ? 'portrait' : 'landscape';
                    Board.setOrientation(nextOrientation);
                    rotateButton.setAttribute('aria-label', nextOrientation === 'landscape' ? 'Rotate board to portrait' : 'Rotate board to landscape');
                });
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

                const iconMap = {
                    forward: '<div class="moveForward buttonIcon"></div>',
                    backward: '<div class="moveBackward buttonIcon"></div>',
                    forwardNearOpponent: '<div class="moveForwardNearOpponent buttonIcon"></div>',
                    backwardNearOpponent: '<div class="moveBackNearOpponent buttonIcon"></div>',
                    free: '<div class="moveFree buttonIcon"></div>',
                    action: '<div class="action buttonIcon"></div>',
                    move: '<div class="move buttonIcon"></div>'
                };

                const parseIcons = (text) => {
                    return text.replace(/\[([^\]]+)\]/g, (match, key) => {
                        return iconMap[key] ?? match;
                    });
                };

                for (let i = 0; i < clientButtons.length; i++) {
                    const clientButton = clientButtons[i];
                    const params = clientButton['buttonParams'] || {};

                    let label = params['label'] ?? clientButton['name'];
                    label = parseIcons(label);

                    const buttonOptions = {
                        color: params['color'] ?? 'primary',
                    };

                    if (params['disabled'] !== undefined)
                        buttonOptions.disabled = params['disabled'];

                    if (params['confirm'] !== undefined && params['confirm'] !== null)
                        buttonOptions.confirm = params['confirm'];

                    this.statusBar.addActionButton(
                        label,
                        async () => {
                            topic.publish('serverActionRequested', clientButton['name']);

                            this.performServerAction(
                                clientButton['action'],
                                clientButton['paramsToSendBack']
                            );
                        },
                        buttonOptions
                    );
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

             initAnimationsManager: function () {
                 console.log('bgaAnimations:', BgaAnimations);
                 console.log('Manager:', BgaAnimations.Manager);
                 console.log('Manager type:', typeof BgaAnimations.Manager);

                this.animationManager = new BgaAnimations.Manager({
                    animationsActive: () => this.bga.gameui.bgaAnimationsActive(),
                });
            },

            assert: function (condition, message = "Assertion failed") {
                if (!condition) {
                    throw new Error(message);
                }
            },

            ///////////////////////////////////////////////////
            //// Reaction to cometD notifications

            setupNotifications: function () {
                this.bgaSetupPromiseNotifications();

                dojo.connect(this.notifqueue, 'addToLog', () => this.addTooltipsToLog());
                this.addTooltipsToLog();
            },

            addTooltipsToLog: function () {
                this._nextCardLogId = this._nextCardLogId || 0;

                for (const el of document.querySelectorAll('.cardLogItem:not(.tt_processed)')) {
                    el.classList.add('tt_processed');
                    el.id = `cardLogItem_${this._nextCardLogId++}`;

                    const definition = (this.cardDefinitions || {})[el.dataset.cardType];
                    if (definition) {
                        el.querySelector('.cardLogName').textContent = definition.name;
                    }

                    this.addTooltipHtml(el.id, this.cardsManager.getCardTooltipHtml(el.dataset.cardType), 500);
                }
            },

            bgaFormatText: function (log, args) {
                if (log && args && !args.processed) {
                    args.processed = true;

                    if (args.cardType !== undefined && args.cardLog !== undefined) {
                        args.cardLog = STCardsManager.getCardLogHtml(args.cardType, this.cardDefinitions);
                    }

                    if (args.rollResult !== undefined && args.diceLog !== undefined) {
                        args.diceLog = DiceUI.formatRollLog(
                            args.rollResult,
                            this.gameDatas.players,
                            parseInt(args.requiredPower),
                            parseInt(args.distance),
                            parseInt(args.blockingPieces),
                            args.actionName
                        );
                    }
                }

                return { log, args };
            },

            notif_placedPiece: function (notifData) {
                return this.forwardNotification(this.notif_placedPiece.name, notifData);
            },

            notif_cubePlaced: async function (notifData) {
                return this.forwardNotification(this.notif_cubePlaced.name, notifData);
            },

            notif_attributeAssignmentFinished: function (notifData) {
                return this.forwardNotification(this.notif_attributeAssignmentFinished.name, notifData);
            },

            notif_movedPiece: async function (notifData) {
                let destHex = STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column);
                let piece = this.pieceManager.getPiece(notifData.movedPiece);
                let moraleSpent = parseInt(notifData.spentMorale) === 1;
                let ballMoved = parseInt(notifData.ballMoved) === 1;

                let animation = GameEventProcessor.MovePieceWithAnimation(piece, destHex, moraleSpent, ballMoved);

                await this.bgaPlayDojoAnimation(animation);
            },

            notif_dribblePerformed: function (notifData) {
                return this.forwardNotification(this.notif_dribblePerformed.name, notifData);
            },

            notif_tacklePerformed: function (notifData) {
                return this.forwardNotification(this.notif_tacklePerformed.name, notifData);
            },

            notif_piecesRefreshed: function (notifData) {
                this.pieceManager.refreshAllPieces(parseInt(notifData.playerId));
            },

            notif_cardPlayed: function (notifData) {
                PlayerPanelManager.setPlayerCards(notifData.activePlayer, notifData.cardsInDeckCount);
                return this.forwardNotification(this.notif_cardPlayed.name, notifData);
            },

            notif_cardUndo: function(notifData) {
                // the undoing player's hand is already restored by the hand refill notification
                if (parseInt(notifData.player_id) === parseInt(this.player_id)) {
                    return;
                }

                PlayerPanelManager.setPlayerCards(this.opponentId, notifData.cardsInDeckCount);
                this.cardsManager.removeCard(notifData.undoCard);
            },

            notif_deckCountChanged: function(notifData) {
                notifData.forEach(([playerId, deckCount]) => {
                    PlayerPanelManager.setPlayerCards(playerId, deckCount);
                });
            },

            notif_resetPlayerPieces: async function (notifData) {
                return this.forwardNotification(this.notif_resetPlayerPieces.name, notifData);
            },

            notif_handRefill: function (notifData) {
                this.cardsManager.setHandVisibility(true);

                if (notifData.wasDiscardShuffled)
                {
                    return this.cardsManager.shuffleDeck().then(()=> {
                        this.cardsManager.placeCards(notifData.playerHand)});
                }
                else
                {
                    this.cardsManager.placeCards(notifData.playerHand);
                }

            },

            notif_opponentDiscardShuffle(notifData) {
                this.cardsManager.removeOpponentDiscard();
            },

            notif_fatigueRemoved: function(notifData) {
                this.cardsManager.placeCards(Object.values(notifData.cardsInSupply));
            },

            notif_pieceExhausted: function (notifData) {
                let pieceData = this.pieceManager.getPiece(parseInt(notifData.pieceId));
                pieceData.setFreshness(false);
            },

            notif_moraleChanged: async function (notifData) {
                let playerMorale = PlayerPanelManager.getPlayerMorale(notifData.playerId);

                if (notifData.amount > playerMorale)
                {
                    const startEl = notifData.playerId === this.opponentId ? document.getElementById('opponentDiscard') : document.getElementById('myDiscard');
                    return this.bgaPlayDojoAnimation(PlayerPanelManager.increasePlayerMoraleAnimated(notifData.playerId, notifData.amount, startEl));
                }
                else {
                    return this.bgaPlayDojoAnimation(PlayerPanelManager.setPlayerMorale(notifData.playerId, notifData.amount, true));
                }
            },

            notif_moraleAssigned : async function (notifData) {
                return this.forwardNotification(this.notif_moraleAssigned.name, notifData);
            },

            notif_diceRolled: function (notifData) {
                let rollResults = [];
                for (const player of Object.keys(notifData["rollResult"])) {
                    rollResults.push(DiceUI.createRollResult(parseInt(player), notifData["rollResult"][player]));
                }

                console.log(rollResults);

                const passDest = notifData.passDest ? STHexLib.createOffsetCoord(notifData.passDest.hex_row, notifData.passDest.hex_column) : null;

                return DiceUI.showRollAndWait(rollResults, notifData.attacker, notifData.requiredPower, notifData.blockingPieces, notifData.distance, notifData.skipEnabledPlayerId, notifData.actionName, passDest);
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
                this.bga.playerPanels.getScoreCounter(notifData.playerId).toValue(notifData.score);

                if (notifData.playerId === this.userInLeftId) {
                    TrackerBoard.setOrangeGoals(notifData.score);
                }
                else {
                    TrackerBoard.setBlueGoals(notifData.score);
                }

                return PlayerPanelManager.setPlayerScoredGoals(notifData.playerId, notifData.score);
            },

            notif_resetBoard: async function (notifData) {
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
                return this.pieceManager.animateResetPieces(parseInt(notifData.goalieRow));
            },

            notif_undoMove: async function (notifData) {
                Board.passBall(
                    STHexLib.createOffsetCoord(
                        notifData.ballHex.hex_row,
                        notifData.ballHex.hex_column
                    )
                );

                let piecesToHexes = [];

                for (let piecesTohex of notifData.piecesToHexes)
                {
                    piecesToHexes.push({
                        pieceId: piecesTohex.pieceId,
                        hex: STHexLib.createOffsetCoord(
                            piecesTohex.position.hex_row,
                            piecesTohex.position.hex_column
                        )
                    });
                }

                let undoAnimation = GameEventProcessor.UndoMoveAnimated(piecesToHexes, notifData.freshPieces);

                await this.bgaPlayDojoAnimation(undoAnimation);
            },

            notif_timeTrackAdvance: async function(notifData) {
                return TrackerBoard.animateTrackerTo(notifData.currentPosition);
            },

            notif_cardReset: async function(notifData) {
                this.cardsManager.removeOpponentDiscard();
                await this.cardsManager.placeAllToDeck();
                this.cardsManager.setHandVisibility(false);
            },

            notif_attributesReset: async function(notifData) {
                const myAttributes = notifData[this.player_id];
                const myCoachBoard = this.myPlayerArea.getCoachBoard();
                myCoachBoard.updateOwnerDataAnimated(myAttributes);

                const opponentAttributes = notifData[this.opponentId];

                const opponentCoachBoard = this.opponentPlayerArea.getCoachBoard();
                return opponentCoachBoard.updateOwnerDataAnimated(opponentAttributes);
            },

            notif_playerAttributesReset: async function(notifData) {
                return this.forwardNotification(this.notif_playerAttributesReset.name, notifData);
            },

            notif_swapPerformed: async function(notifData) {
                let lhsHex = STHexLib.createOffsetCoord(notifData.swappedHexes[0].hex_row, notifData.swappedHexes[0].hex_column);
                let rhsHex = STHexLib.createOffsetCoord(notifData.swappedHexes[1].hex_row, notifData.swappedHexes[1].hex_column);
                let ballHex = STHexLib.createOffsetCoord(notifData.ballPosition.hex_row, notifData.ballPosition.hex_column);

                await GameEventProcessor.SwapAnimated(lhsHex, rhsHex, ballHex);
            },

            notif_attackerDefenderChanged: function(notifData) {
                PlayerPanelManager.setAttacker(notifData.attacker);
            },

            forwardNotification(funcName, notifData) {
                this.assert(this.currentState && typeof this.currentState[funcName] === "function", `Server call "${funcName}" not handled in current state`);

                return this.currentState[funcName](notifData);
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

            cardDefinitions: null,

            serverCallInProgress: false,

            gameDatas: null,

            cardsManager: null,

            animationManager: null
        });
    });
