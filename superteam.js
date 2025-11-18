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
    "dojo","dojo/_base/declare",
    g_gamethemeurl + "modules/js/STUIConstants.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    "ebg/core/gamegui",
    "ebg/counter",
],
function (dojo, declare, STUIConstants, STHexLib) {
    return declare("bgagame.superteam", ebg.core.gamegui, {
        constructor: function(){
            console.log('superteam constructor');
    
            this.setupDebugFeatures();
        },
        
        setup: function( gamedatas )
        {
            console.log('begin setup');

            console.log(gamedatas);

            this.myColor = gamedatas.players[this.player_id].color === 'FC5414' ? 'orange' : 'blue';
            this.opponentColor = this.myColor === 'orange' ? 'blue' : 'orange';

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
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName, args );

            this.currentStateData = args;

            if (this.isCurrentPlayerActive() == false)
                return;
            
            switch( stateName )
            {
            case 'playerPieceSetup':
                this.onPlayerPieceSetup(args);
            case 'playerTurn':
                console.log("in playerTurn");
                break;
            case 'pieceSelection':
                this.makePiecesSelectable(args.args.selectablePieces);
                break;
            case 'moveAction':
                if (this.isCurrentPlayerActive()) 
                {
                    document.querySelectorAll('.freeInRange').forEach(div=> div.classList.remove('freeInRange'));      
                }
                this.onMoveAction(args.args);
            case 'dummy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );

            switch( stateName )
            {
                case 'playerPieceSetup':
                    if (this.isCurrentPlayerActive()) 
                    {
                        document.querySelectorAll('.availableToPlacePiece').forEach(div=> div.classList.remove('availableToPlacePiece'));      
                    }
                    break;
                case 'pieceSelection':
                    if (this.isCurrentPlayerActive()) 
                    {
                        document.querySelectorAll('.piece').forEach((e)=>e.classList.remove('clickable'));
                    }
                    break;
                case 'moveAction':
                    if (this.isCurrentPlayerActive()) 
                    {
                        document.querySelectorAll('.freeInRange').forEach(div=> div.classList.remove('freeInRange'));
                        document.querySelector('.selected').classList.remove('selected');
                    }
            case 'dummy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        onUpdateActionButtons: function( stateName, args )
        {
            console.log('onUpdateActionButtons: ' + stateName, args);

            console.log("active player id " + this.getActivePlayerId());

            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                    case 'playerTurn':
                        {
                            let possibleActions = args['possibleActions'];

                            for (let i = 0; i < possibleActions.length; i++) {
                                let action = possibleActions[i];
                                let id = parseInt(action['id'])
                                this.statusBar.addActionButton(action['name'], () => { console.log('action id was: ' + id); this.bgaPerformAction("actActionSelected", { actionId: id }) }, { color: 'primary' });
                            }
                        }
                        break;
                    case 'moveAction':
                        {
                            let possibleActions = args['possibleActions'];

                            for (let i = 0; i < possibleActions.length; i++) {
                                let action = possibleActions[i];
                                let id = parseInt(action['id'])
                                this.statusBar.addActionButton(action['name'], () => { console.log('action id was: ' + id); this.bgaPerformAction("actActionSelected", { actionId: id }) }, { color: 'primary' });
                            }
                        }
                        break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        setupPlayerArea: function(gamedatas)
        {
            for (const playerId of Object.keys(gamedatas.players))
            {
                let currentMorale = gamedatas.players[playerId].morale;
                
                this.getPlayerPanelElement(playerId).insertAdjacentHTML('beforeend', `
                    <div id="resources"></div> 
                    <span id="playerMorale${playerId}">${_("Morale")}: ${currentMorale}</span>`);
                }
        },

        setupMainBoard: function(gamedatas)
        {
            this.getGameAreaElement().insertAdjacentHTML('beforeend', `<div id="mainBoard"></div>`);

            const MAIN_BOARD_EL = document.getElementById('mainBoard');
            const BOARD_WIDTH = parseFloat(getComputedStyle(MAIN_BOARD_EL).width);
            const BOARD_HEIGHT = parseFloat(getComputedStyle(MAIN_BOARD_EL).height);

            this.LEFT_MARGIN = STUIConstants.HORIZONTAL_MARGIN_PERCENT * BOARD_WIDTH;
            this.TOP_MARGIN_ODD = STUIConstants.TOP_MARGIN_ODD_PERCENT * BOARD_HEIGHT;
            const TOP_MARGIN_EVEN = STUIConstants.TOP_MARGIN_EVEN_PERCENT * BOARD_HEIGHT;

            const HEX_WIDTH = BOARD_WIDTH * STUIConstants.HEX_WIDTH_PERCENT;
            const HEX_HEIGHT = BOARD_HEIGHT * STUIConstants.HEX_HEIGHT_PERCENT;
            const HEX_WIDTH_ROUND = Math.round(HEX_WIDTH);
            const HEX_HEIGHT_ROUND = Math.round(HEX_HEIGHT);
            const HEX_VERTICAL_SPACE = STUIConstants.HEX_VERTICAL_SPACE_PERCENT * BOARD_HEIGHT;
            this.HEX_HORIZONTAL_SPACE = STUIConstants.HEX_HORIZONTAL_SPACE_PERCENT * BOARD_WIDTH;

            const HEX_LOGICAL_WIDTH = STUIConstants.HEX_LOGICAL_WIDTH_PERCENT * BOARD_WIDTH;
            this.hexLogicalSize = HEX_LOGICAL_WIDTH * 0.5;

            const HEX_LOGICAL_HEIGHT = STUIConstants.HEX_LOGICAL_HEIGHT_PERCENT * BOARD_HEIGHT;
            this.hexInradius = HEX_LOGICAL_HEIGHT * 0.5;

            for (let x = 0; x < STUIConstants.COLUMNS; x++)
            {
                let hexInColumn = (x + 1) % 2 == 1 ? STUIConstants.EVEN_COLUMN_ROWS : STUIConstants.ODD_COLUMN_ROWS;
                let left = Math.round(this.LEFT_MARGIN + (this.HEX_HORIZONTAL_SPACE) * x);
                let currentTopMargin = (x + 1) % 2 == 1 ? this.TOP_MARGIN_ODD : TOP_MARGIN_EVEN;

                for (let y = 0; y < hexInColumn; y++) 
                {
                    let hexId = `hex_${y}_${x}`;
                    
                    let offsetCoord = this.convertHexIdToOffsetCoord(hexId);
                    
                    let top = Math.round(currentTopMargin + (y * (HEX_HEIGHT + HEX_VERTICAL_SPACE)));

                    this.allHexes.push({
                        cubic: STHexLib.oddqToCubic(offsetCoord),
                        cartesian: STHexLib.oddqToCartesian(offsetCoord, this.hexLogicalSize),
                        pixelPos: {left: left, top: top}
                    });

                    MAIN_BOARD_EL.insertAdjacentHTML('afterbegin', `<div id='${hexId}' class="hex" style='left:${left}px; top:${top}px; width: ${HEX_WIDTH_ROUND}px; height: ${HEX_HEIGHT_ROUND}px;'></div>`);
                }
            }

            MAIN_BOARD_EL.insertAdjacentHTML('beforeend', `<div id='pieces'></div>`);

            this.setPiecesData(gamedatas);

            this.addBallToBoard(gamedatas);

            this.initHexClickListener();
        },

        updatePlayerMorale: function(playerId, newValue)
        {
            let el = this.document.getElementById(`playerMorale${playerId}`);
            el.innerHTML = `${_("Morale")}: ${newValue}`;
        },

        initHexClickListener: function()
        {
            document.querySelectorAll('.hex').forEach(hex=> {
                hex.addEventListener('click', e=>this.onHexClicked(e));
                //hex.addEventListener('mouseenter', e=>this.onHexMouseEnter(e));
                //hex.addEventListener('mouseleave', e=>this.onHexMouseLeave(e));
            });
        },

        setPieceClickEvent: function(pieceId)
        {
            document.getElementById(pieceId).addEventListener('click', e=>this.onPieceClicked(e));
        },

        onHexClicked: async function(evt)
        {
            //TODO: I should have handlers for it, this design is not flexible. each state can register a handler for click event.

            if (this.isWaitingForServerResponse)
                return;

            try 
            {
                if (this.currentStateData.name === 'playerPieceSetup') 
                {
                    if (evt.currentTarget.classList.contains('availableToPlacePiece')) 
                    {
                        evt.preventDefault();
                        evt.stopPropagation();

                        var offsetCoord = this.convertHexIdToOffsetCoord(evt.currentTarget.id);

                        this.isWaitingForServerResponse = true;
                        await this.bgaPerformAction("actPlacedPiece", { placedPiece: this.currentStateData.args.pieceToPlace, hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
                    }
                }
                else if (this.currentStateData.name === 'moveAction')
                {
                    if (evt.currentTarget.classList.contains('freeInRange'))
                    {
                        evt.preventDefault();
                        evt.stopPropagation();

                        var offsetCoord = this.convertHexIdToOffsetCoord(evt.currentTarget.id);

                        this.isWaitingForServerResponse = true;
                        await this.bgaPerformAction("actPieceMoved", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
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
            
            onHexMouseEnter: function(evt)
        {
            
        },

        onHexMouseLeave: function(evt)
        {
            document.querySelectorAll('.nearPlayerInRange').forEach(div=> div.classList.remove('nearPlayerInRange'));
        },

        onPieceClicked: async function(evt)
        {
            if (!this.isWaitingForServerResponse)
            {
                if (evt.currentTarget.classList.contains('clickable'))
                {
                    evt.preventDefault();
                    evt.stopPropagation();

                    try
                    {
                        this.isWaitingForServerResponse = true;
                        let pieceDOMId = evt.currentTarget.id;
                        let numericId = parseInt(pieceDOMId.replace("piece_", ""));

                        await this.bgaPerformAction("actPieceSelected", {pieceId: numericId}, { lock: true, checkAction: true});
                    }
                    catch(error)
                    {
                        console.error( "Server action failed, exception caught:", error );
                    }
                    finally
                    {
                        this.isWaitingForServerResponse = false;
                    }
                }
            }
        },

        getPieceDOMId : function(integerId)
        {
            return "piece_" + integerId;
        },

        parseHexId: function(hexId)
        {
            let idEls = hexId.split("_");

            return [idEls[1], idEls[2]];
        },

        convertHexIdToOffsetCoord: function(hexId)
        {
            var parsedHexId = this.parseHexId(hexId);
            return STHexLib.createOffsetCoord(parsedHexId[0], parsedHexId[1]);
        },

        convertCubicCoordToHexId: function(cubicCoord)
        {
            let offsetHex = STHexLib.cubicToOddq(cubicCoord);

            return this.convertOffsetCoordToHexId(offsetHex);
        },

        convertOffsetCoordToHexId: function(offsetCoord)
        {
            return 'hex_' + offsetCoord.r + '_' + offsetCoord.c;   
        },

        getBoardOffsetExtent: function()
        {
            //(sha) TODO: This should be global, we need to calculate it only once.
            return STHexLib.createOffsetExtent(0, 16, 0, 9, 0, 10);
        },

        getHexPixelCoordinate: function(offsetCoord)
        {
            let hexData = this.getHexData(offsetCoord);
            return hexData.pixelPos;
        },

        getHexData: function(offsetCoord)
        {
            let evenColumnsCount = Math.floor((offsetCoord.c + 1) * 0.5);
            let oddColumnsCount = offsetCoord.c - evenColumnsCount;
            
            return this.allHexes[STUIConstants.EVEN_COLUMN_ROWS * evenColumnsCount + oddColumnsCount * STUIConstants.ODD_COLUMN_ROWS + offsetCoord.r];;
        },

        getHexesWithPlayer: function(teamId)
        {
            let result = [];

            if (teamId === this.player_id)
            {
                result = this.myPiecesData;
            }
            else if (teamId === this.opponentPiecesData)
            {
                result = this.opponentPiecesData;
            }
            else
            {
                this.assert(false, "Unknown team id: " + teamId);
            }

            return result;
        },

        setPiecesData: function(gameDatas)
        {
            this.piecesDataMap = new Map();

            let piecesData = gameDatas["player_pieces"];

            console.log(piecesData);

            for (let i = 0; i < piecesData.length; ++i)
            {
                let el = piecesData[i];
                let pieceData = this.createPlayerPiece(el.owner_id, parseInt(el.id), null, el.is_fresh === '1', parseInt(el.piece_role));
                this.piecesDataMap.set(
                    parseInt(el.id),
                    pieceData
                );

                let hexColumn = el.hex_column;
                let hexRow = el.hex_row;

                if (hexColumn != -1 && hexRow != -1)
                {
                    let offsetCoord = STHexLib.createOffsetCoord(hexRow, hexColumn);
                    this.addPieceToBoard(pieceData.getOwner(), offsetCoord, pieceData);
                }
            }
        },

        getHexWithBall: function()
        {
            return this.hexWithBall;
        },

        addPieceToBoard: async function(player, offsetCoord, pieceData)
        {
            const PIECE_DOM_ID = this.getPieceDOMId(pieceData.getId());
            
            let color = parseInt(player) === this.player_id ? this.myColor : this.opponentColor;
            let status = pieceData.isFresh() ? 'fresh' : 'exhuasted';
            let type = this.pieceRoleToString(pieceData.getRole());
            
            document.getElementById('pieces').insertAdjacentHTML('beforeend', `<div id="${PIECE_DOM_ID}" class="piece ${status} ${type} ${color}" >${type[0]}_${status[0]}</div>`);
            pieceData.setPosition(offsetCoord);

            this.placeOnObject( PIECE_DOM_ID, 'overall_player_board_'+player );

            this.setPieceClickEvent(PIECE_DOM_ID);
            
            const HEX_ID = this.convertOffsetCoordToHexId(offsetCoord);
            const anim = this.slideToObject( PIECE_DOM_ID, HEX_ID );
            
            await this.bgaPlayDojoAnimation(anim);
        },

        addBallToBoard: async function(gamedatas)
        {
            let ballData = gamedatas['ball'];

            this.hexWithBall = STHexLib.createOffsetCoord(ballData.hex_row, ballData.hex_column);

            console.log("hex with ball is " + this.hexWithBall);

            const MAIN_BOARD_EL = document.getElementById('mainBoard');
            MAIN_BOARD_EL.insertAdjacentHTML('beforeend', `<div id="ball" class="ball" ></div>`);

            const HEX_ID = this.convertOffsetCoordToHexId(this.hexWithBall);
            const anim = this.slideToObject('ball', HEX_ID );
            
            await this.bgaPlayDojoAnimation(anim);
        },

        makePiecesSelectable: function(pieceIds)
        {
            for (let i = 0; i < pieceIds.length; i++)
            {
                let DOMId = this.getPieceDOMId(pieceIds[i]);

                document.getElementById(DOMId).classList.add('clickable');
            }
        },

        onMoveAction: function(args)
        {
            let selectedPiece = args.selectedPiece;
            let moveableHexes = args.moveableHexes;

            let PieceDOMId = this.getPieceDOMId(selectedPiece);

            document.getElementById(PieceDOMId).classList.add('selected');

            for (let i = 0; i < moveableHexes.length; i++)
            {
                let hex = moveableHexes[i];
                let DOMHexId = this.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(hex.hex_row, hex.hex_column));

                document.getElementById(DOMHexId).classList.add('freeInRange');
            }
        },

        doesHexHasMovablePiece: function(hexId)
        {
            let result = false;

            let hexCoord = this.convertHexIdToOffsetCoord(hexId);
            let myTeamPieces = this.getHexesWithPlayer(this.player_id);

            for (let i = 0; i < myTeamPieces.length; ++i) 
            {
                if (myTeamPieces[i].hex.equals(hexCoord)) 
                {
                    let piece = myTeamPieces[i].piece;
                    if (!piece.isGoalie()) 
                    {
                        result = true;
                        break;
                    }
                }
            }

            return result;
        },

        doesHexHasBlockingPiece: function(hexId)
        {
            let result = false;

            let hexCoord = this.convertHexIdToOffsetCoord(hexId);
            let myTeamPieces = this.getHexesWithPlayer(this.playerId);
            let otherTeampieces = this.getHexesWithPlayer(this.opponentId);

            let allPieces = myTeamPieces.concat(otherTeampieces);

            for (let i = 0; i < allPieces.length; ++i) 
            {
                if (allPieces[i].hex.equals(hexCoord)) 
                {
                    result = true;
                    break;
                }
            }

            return result;
        },

        createPlayerPiece: function(owner, id, position, isFresh, pieceRole)
        {
            return {
                _owner: owner,
                _position: position,
                _isFresh: isFresh,
                _id: id,
                _role: pieceRole,

                playerRoles: {
                    None: 0,
                    Goalie: 1,
                    Captain: 2,
                    Regular: 3
                },

                isGoalie: function()
                {
                    return this._role === this.playerRoles.Goalie;
                },

                isCaptain: function()
                {
                    return this._role === this.playerRoles.Captain;
                },

                isFresh: function()
                {
                    return this._isFresh;
                },

                getRole: function()
                {
                    return this._role;
                },

                getOwner: function()
                {
                    return this._owner;
                },

                getPosition: function()
                {
                    return this._position;
                },

                setPosition: function(offsetPos)
                {
                    this._position = offsetPos;
                },

                getId: function()
                {
                    return this._id;
                }
            }
        },

        pieceRoleToString : function(role)
        {
            switch (role)
            {
                case 1:
                    return 'goalie';
                case 2:
                    return 'captain';
                case 3:
                    return "regular";
                default:
                    this.assert(false, "invalid role id!");
            }
        },

        assert: function (condition, message = "Assertion failed") {
            if (!condition) {
                throw new Error(message);
            }
        },

        ///////////////////////////////////////////////////
        //// state response function
        onPlayerPieceSetup: function(stateData) 
        {
            document.querySelectorAll('.availableToPlacePiece').forEach(div=> div.classList.remove('availableToPlacePiece'));

            let availableStartingPositions = stateData.args.availableStartingPositions;

            for (let i = 0; i < availableStartingPositions.length; ++i) 
            {
                let el =  availableStartingPositions[i];

                let hexColumn = el.hex_column;
                let hexRow = el.hex_row;

                let hexId = this.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(hexRow, hexColumn));
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

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your superteam.game.php file.
        
        */
        setupNotifications: function()
        {
            console.log( 'notifications subscriptions setup' );
            
            // TODO: here, associate your game notifications with local methods
            //dojo.subscribe( 'placedPiece', this, "notif_placedPiece" );

            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );

            this.bgaSetupPromiseNotifications();
        },  
        

        notif_placedPiece : async function(notifData)
        {
            let pieceData = this.piecesDataMap.get(notifData.placed_piece);
            let hex = STHexLib.createOffsetCoord( notifData.hex_row, notifData.hex_column);

            await this.addPieceToBoard(notifData.player_id, hex, pieceData);
        },

        
        notif_movedPiece: async function(notifData)
        {
            let destHex = STHexLib.createOffsetCoord( notifData.hex_row, notifData.hex_column);
            let pieceData = this.piecesDataMap.get(notifData.movedPiece);

            pieceData.setPosition(destHex);

            let animations = [];

            const PIECE_DOM_ID = this.getPieceDOMId(pieceData.getId());
            const DEST_HEX_ID = this.convertOffsetCoordToHexId(destHex);
            animations.push(this.slideToObject( PIECE_DOM_ID, DEST_HEX_ID ));

            if (parseInt(notifData.ballMoved))
            {
                this.hexWithBall = STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column);
                animations.push(this.slideToObject('ball', DEST_HEX_ID ));
            }

            await this.bgaPlayDojoAnimation(dojo.fx.combine(animations));
        },

        ///////////////////////////////////////////////////
        //// Debugging features

        setupDebugFeatures: function ()
        {
            let isDev = window.location.hostname === "studio.boardgamearena.com";
            if (!isDev)
                return;

            console.log("Debug mode is active, to see options type: help");

            window.help = function()
            {
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

                    Array.from(hexes).forEach(hex => 
                    {
                        let offsetCoord = this.convertHexIdToOffsetCoord(hex.id);                        
                        let cubeCoord = STHexLib.oddqToCubic(offsetCoord);

                        hex.innerHTML = val ? offsetCoord + '<br>'+ cubeCoord : "";
                    });
                }.bind(this)
            })
        },

        ///////////////////////////////////////////////////
        //// Private data

        //------------------------ board info

        hexLogicalSize: 0,

        hexInradius: 0,

        allHexes: [],

        LEFT_MARGIN: 0, 

        TOP_MARGIN_ODD: 0,

        HEX_HORIZONTAL_SPACE: 0,

        piecesDataMap: null,

        hexWithBall: null,

        //----------------------- general variables

        startHex: null,

        endHex: null,

        currentStateData: null,

        myColor: null,

        opponentColor: null,

        isWaitingForServerResponse: false,
   });             
});
