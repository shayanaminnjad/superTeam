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

                this.trackDojoAnimations();

                this.cardDefinitions = gamedatas.card_definitions;
                this.captainDefinitions = gamedatas.captain_definitions;
                this.coachDefinitions = gamedatas.coach_definitions;

                // the player whose side this screen shows; a spectator gets the left seat from the server
                this.viewerId = this.isSpectator ? parseInt(gamedatas.viewer_id) : this.player_id;

                this.myColor = gamedatas.players[this.viewerId].color === 'F86126' ? 'red' : 'blue';
                this.opponentColor = this.myColor === 'red' ? 'blue' : 'red';

                for (const player of Object.keys(gamedatas.players)) {
                    if (parseInt(player) !== this.viewerId) {
                        this.opponentId = parseInt(player);
                        break;
                    }
                }

                this.userInLeftId = parseInt(gamedatas['user_in_left_id']);
                this.userInRightId = parseInt(gamedatas['user_in_right_id']);

                this.initAnimationsManager();

                PlayerPanelManager.construct(this, gamedatas);

                let orangeGoals = gamedatas.players[this.viewerId].score;
                let blueGoals = gamedatas.players[this.opponentId].score;
                if (this.userInLeftId !== this.viewerId)
                {
                    let temp = orangeGoals;
                    orangeGoals = blueGoals;
                    blueGoals = temp;
                }

                TrackerBoard.construct(this, gamedatas.time_index, orangeGoals, blueGoals, gamedatas.is_final_turn);

                this.pieceManager = PieceManager.getPieceManager();
                this.pieceManager.construct(this);

                GameEventProcessor.construct(this, this.pieceManager);

                this.setupMainBoard(gamedatas);

                this.myPlayerArea = new PlayerArea(this.viewerId, gamedatas.players[this.viewerId], this);
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

            getPlayerActualColor : function(playerId)
            {
                const  colorToMap = playerId === this.opponentId ? this.opponentColor : this.myColor;
                return colorToMap === 'red' ? '#F86126' : '#00BBF7';
            },

            setupMainBoard: function (gamedatas) {

                //Left seat sits on the low-column end, which projects to the top in
                //portrait mode. Flipping for that seat puts the viewing player's own
                //side at the bottom instead, near their own player panel.
                const flipForViewer = this.viewerId === this.userInLeftId;

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
                    const currentOrientation = this._pendingOrientation ?? Board.getOrientation();
                    this._pendingOrientation = currentOrientation === 'landscape' ? 'portrait' : 'landscape';
                    this.applyPendingOrientation();
                });
            },

            // Dojo slide animations aim at pixel positions computed when they start, so the board
            // must not rotate while one is running. A rotation requested meanwhile waits for idle.
            applyPendingOrientation: function () {
                if (this._pendingOrientation === null || this._runningDojoAnimations > 0)
                    return;

                const orientation = this._pendingOrientation;
                this._pendingOrientation = null;

                Board.setOrientation(orientation);

                document.getElementById('boardRotateToggle').setAttribute('aria-label',
                    orientation === 'landscape' ? 'Rotate board to portrait' : 'Rotate board to landscape');
            },

            trackDojoAnimations: function () {
                const playAnimation = this.bgaPlayDojoAnimation.bind(this);

                this.bgaPlayDojoAnimation = (animation) => {
                    this._runningDojoAnimations++;

                    const promise = Promise.resolve(playAnimation(animation));

                    const onDone = () => {
                        this._runningDojoAnimations--;
                        this.applyPendingOrientation();
                    };
                    promise.then(onDone, onDone);

                    return promise;
                };
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
                if (this.serverCallInProgress) {
                    console.warn('hex click ignored, a server call is still in progress');
                    return;
                }

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
                    const clientButton = clientButtons[i];
                    const params = clientButton['buttonParams'] || {};

                    let label = params['label'] ?? clientButton['name'];
                    label = STHUIHelpers.parseIcons(label);

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

                    const isMine = parseInt(el.owner_id) === this.viewerId;
                    let color = isMine ? this.myColor : this.opponentColor;
                    let piece = this.pieceManager.createNewPiece(parseInt(el.id), el, color);
                    this.setPieceClickEvent(piece.getDOMElement());

                    if (!isMine)
                        piece.getDOMElement().classList.add('opponent');

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

            buildDiceParams: function (notifData) {
                const rollResults = [];
                for (const player of Object.keys(notifData["rollResult"])) {
                    rollResults.push(DiceUI.createRollResult(parseInt(player), notifData["rollResult"][player]));
                }

                const passDest = notifData.passDest
                    ? STHexLib.createOffsetCoord(notifData.passDest.hex_row, notifData.passDest.hex_column)
                    : null;

                return {
                    rollResults,
                    attacker: notifData.attacker,
                    requiredPower: notifData.requiredPower,
                    blockingPieces: notifData.blockingPieces,
                    distance: notifData.distance,
                    skipEnabledPlayerId: notifData.skipEnabledPlayerId,
                    actionName: notifData.actionName,
                    passDest,
                };
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

            playBgaSound: function(soundName){
                this.bga.gameui.disableNextMoveSound();
                this.bga.sounds.play(soundName);
            },

            bgaFormatText: function (log, args) {
                if (log && args && !args.processed) {
                    args.processed = true;

                    if (args.moraleIcon !== undefined) {
                        args.moraleIcon = STHUIHelpers.iconMap.morale;
                    }

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
                            args.actionName,
                            parseInt(args.skipEnabledPlayerId)
                        );
                    }
                }

                return { log, args };
            },

            notif_draftRevealed: function (notifData) {
                return this.forwardNotification(this.notif_draftRevealed.name, notifData);
            },

            notif_deckCreated: function (notifData) {
                return this.forwardNotification(this.notif_deckCreated.name, notifData);
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

            notif_bypassPerformed: async function(notifData) {
                let piece = this.pieceManager.getPiece(notifData.movedPiece);
                let destHex = STHexLib.createOffsetCoord(notifData.destHex.hex_row, notifData.destHex.hex_column);

                let animation = GameEventProcessor.MovePieceWithAnimation(piece, destHex, 0, notifData.movedBall);

                await this.bgaPlayDojoAnimation(animation);
            },

            notif_cardPlayed: function (notifData) {
                PlayerPanelManager.setPlayerCards(notifData.activePlayer, notifData.cardsInDeckCount);
                return this.forwardNotification(this.notif_cardPlayed.name, notifData);
            },

            notif_cardsDiscarded: function(notifData) {
                this.cardsManager.placeCards(Object.values(notifData.discards));
            },

            notif_cardUndo: function(notifData) {
                const playerId = parseInt(notifData.player_id);

                // the undoing player's hand is already restored by the hand refill notification
                if (playerId === this.player_id) {
                    return;
                }

                PlayerPanelManager.setPlayerCards(playerId, notifData.cardsInDeckCount);
                this.cardsManager.removeCard(notifData.undoCard);
            },

            notif_deckCountChanged: function(notifData) {
                notifData.forEach(([playerId, deckCount]) => {
                    PlayerPanelManager.setPlayerCards(playerId, deckCount);
                });
            },

            notif_cardPutOnTopOfDeck: function(notifData) {
                this.cardsManager.placeCardFromHandToDeck(notifData.movedCard);
            },

            notif_resetPlayerPieces: async function (notifData) {
                return this.forwardNotification(this.notif_resetPlayerPieces.name, notifData);
            },

            notif_handRefill: function (notifData) {
                if (this.isSpectator)
                    return;

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

            notif_discardShuffled: function (notifData) {
                // a player's own discard shuffle is animated by the private hand refill notification
                if (parseInt(notifData.playerId) === this.opponentId) {
                    this.cardsManager.removeOpponentDiscard();
                }
                else if (this.isSpectator) {
                    this.cardsManager.removeMyDiscard();
                }
            },

            notif_fatigueRemoved: function(notifData) {
                this.cardsManager.placeCards(Object.values(notifData.cardsInSupply));
            },

            notif_pieceExhausted: function (notifData) {
                let pieceData = this.pieceManager.getPiece(parseInt(notifData.pieceId));
                pieceData.setFreshness(false);
            },

            notif_pieceFreshed: function (notifData) {
                let pieceData = this.pieceManager.getPiece(parseInt(notifData.pieceId));
                pieceData.setFreshness(true);
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

            notif_diceRolled: async function (notifData) {
                await DiceUI.showRoll(this.buildDiceParams(notifData), false);
                const DISPLAY_TIME = 5 * 1000;
                await this.wait(DISPLAY_TIME);

                if(!notifData.hasReroll) {
                    DiceUI.dismiss();
                    DiceUI.clearRollVisuals();
                }
            },

            notif_reroll: async function(notifData) {
                return this.forwardNotification(this.notif_reroll.name, notifData);
            },

            notif_successfulPass: function (notifData) {
                this.playBgaSound('kick');
                Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_failedPass: function (notifData) {
                this.playBgaSound('kick');
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
                this.playBgaSound('kick');
                return Board.passBall(STHexLib.createOffsetCoord(notifData.ballHex.hex_row, notifData.ballHex.hex_column));
            },

            notif_goalScored: async function (notifData) {
                this.playBgaSound('stadium_cheer');
                this.bga.playerPanels.getScoreCounter(notifData.playerId).toValue(notifData.score);

                if (notifData.playerId === this.userInLeftId) {
                    TrackerBoard.setOrangeGoals(notifData.score);
                }
                else {
                    TrackerBoard.setBlueGoals(notifData.score);
                }

                return this.showGoalText();
            },

            notif_halfTime: async function () {
                return this.showBannerText(_('HALF TIME'), 'timeAdvanceText');
            },

            showGoalText: function () {
                return this.showBannerText(_('GOAL!'), 'goalText');
            },

            // resolves when the text starts to fade out, the element removes itself when the animation ends
            showBannerText: function (text, animationClass) {
                if (!this.bga.gameui.bgaAnimationsActive()) {
                    return Promise.resolve();
                }

                const FADE_START_RATIO = 0.85;

                return new Promise(resolve => {
                    const el = document.createElement('div');
                    el.className = `readonlyText bannerText ${animationClass}`;
                    el.textContent = text;
                    el.style.setProperty('--color', `#E5DFCB`);
                    el.addEventListener('animationend', () => el.remove());
                    document.body.appendChild(el);

                    const animation = el.getAnimations()[0];
                    const duration = animation.effect.getComputedTiming().duration;
                    setTimeout(resolve, duration * FADE_START_RATIO);
                });
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
                if (parseInt(notifData.minute) !== 45)
                    this.showBannerText(notifData.minute, 'timeAdvanceText');

                return TrackerBoard.animateTrackerTo(notifData.currentPosition);
            },

            notif_finalTurnStarted: async function(notifData) {
                TrackerBoard.setFinalTurnBannerVisible(true);
            },

            notif_cardReset: async function(notifData) {
                this.cardsManager.removeOpponentDiscard();

                // no deck for a spectator, so the discard just empties instead of animating into the deck
                if (this.isSpectator) {
                    this.cardsManager.removeMyDiscard();
                    return;
                }

                await this.cardsManager.placeAllToDeck();
                this.cardsManager.setHandVisibility(false);
            },

            notif_attributesReset: async function(notifData) {
                const myAttributes = notifData[this.viewerId];
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

            viewerId: null,

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

            captainDefinitions: null,

            coachDefinitions: null,

            serverCallInProgress: false,

            _runningDojoAnimations: 0,

            _pendingOrientation: null,

            gameDatas: null,

            cardsManager: null,

            animationManager: null
        });
    });
