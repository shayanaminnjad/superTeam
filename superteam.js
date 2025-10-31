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

            this.myColor = gamedatas.players[this.player_id].color === 'FC5414' ? 'orange' : 'blue';
            this.opponentColor = this.myColor === 'orange' ? 'blue' : 'orange';

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
            
            switch( stateName )
            {
            case 'playerPieceSetup':
                if (this.isCurrentPlayerActive())
                {
                    this.OnPlayerPieceSetup(args);
                }
            case 'playerTurn':
                console.log("in playerTurn");
                break;
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
            case 'dummy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName, args );

            console.log("active player id " + this.getActivePlayerId());
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                 case 'playerTurn':    
                        this.statusBar.addActionButton(_('Play a card'), () => this.bgaPerformAction("actPlacedPiece"), { color: 'primary' });
                        this.statusBar.addActionButton(_('1 Movement'), () => this.bgaPerformAction("actPlacedPiece"), { color: 'primary' });
                        this.statusBar.addActionButton(_('Pass Forward'), () => this.bgaPerformAction("actPassForward"), { color: 'primary' });
                        this.statusBar.addActionButton(_('Shoot'), () => this.bgaPerformAction("actPlacedPiece"), { color: 'primary' });
                        this.statusBar.addActionButton(_('Dribble'), () => this.bgaPerformAction("actPlacedPiece"), { color: 'primary' });
                        this.statusBar.addActionButton(_('End Turn'), () => this.bgaPerformAction("actPlacedPiece"), { color: 'secondary' });
                        break;
                    /*const playableCardsIds = args.playableCardsIds; // returned by the argPlayerTurn

                    // Add test action buttons in the action status bar, simulating a card click:
                    playableCardsIds.forEach(
                        cardId => this.statusBar.addActionButton(_('Play card with id ${card_id}').replace('${card_id}', cardId), () => this.onCardClick(cardId))
                    ); 
                    */
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
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

        initHexClickListener: function()
        {
            document.querySelectorAll('.hex').forEach(hex=> {
                hex.addEventListener('click', e=>this.onHexClicked(e));
                //hex.addEventListener('mouseenter', e=>this.onHexMouseEnter(e));
                //hex.addEventListener('mouseleave', e=>this.onHexMouseLeave(e));
            });
        },

        onHexClicked: async function(evt)
        {
            evt.preventDefault();
            evt.stopPropagation();

            if (this.currentStateData.name === 'playerPieceSetup' && !this.isWaitingForServerResponse)
            {
                if (evt.currentTarget.classList.contains('availableToPlacePiece'))
                {
                    var offsetCoord = this.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    try
                    {
                        this.isWaitingForServerResponse = true;
                        await this.bgaPerformAction("actPlacedPiece", {placedPiece: this.currentStateData.args.pieceToPlace, hexColumn: offsetCoord.c, hexRow: offsetCoord.r}, { lock: true, checkAction: true});
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

            /*
            document.querySelectorAll('.moveableRange').forEach(div=> div.classList.remove('moveableRange'));

            if (this.doesHexHasMovablePiece(evt.currentTarget.id))
            {
                var offsetCoord = this.convertHexIdToOffsetCoord(evt.currentTarget.id);
                let cubeCoord = STHexLib.oddqToCubic(offsetCoord);

                let neighbors = STHexLib.getNeighbours(cubeCoord, 2, this.getBoardOffsetExtent());

                for (let i = 0; i < neighbors.length; i++) {

                    let hexId = this.convertCubicCoordToHexId(neighbors[i]);
                    if (!this.doesHexHasBlockingPiece(hexId))
                    {
                        let hexEl = document.getElementById(hexId);

                        hexEl.classList.add('moveableRange');
                    }
                }
            }
                */
        },

        onHexMouseEnter: function(evt)
        {
            
        },

        onHexMouseLeave: function(evt)
        {
            document.querySelectorAll('.nearPlayerInRange').forEach(div=> div.classList.remove('nearPlayerInRange'));
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
            const PIECE_DOM_ID = `piece_${pieceData.getId()}`;
            
            let color = parseInt(player) === this.player_id ? this.myColor : this.opponentColor;
            let status = pieceData.isFresh() ? 'fresh' : 'exhuasted';
            let type = this.pieceRoleToString(pieceData.getRole());
            
            document.getElementById('pieces').insertAdjacentHTML('beforeend', `<div id="${PIECE_DOM_ID}" class="piece ${status} ${type} ${color}" >${type[0]}_${status[0]}</div>`);
            pieceData.setPosition(offsetCoord);

            this.placeOnObject( PIECE_DOM_ID, 'overall_player_board_'+player );
            
            const HEX_ID = `hex_${offsetCoord.r}_${offsetCoord.c}`;
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

            const HEX_ID = `hex_${this.hexWithBall.r}_${this.hexWithBall.c}`;
            const anim = this.slideToObject('ball', HEX_ID );
            
            await this.bgaPlayDojoAnimation(anim);
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
        OnPlayerPieceSetup: function(stateData) 
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

        ///////////////////////////////////////////////////
        //// Debugging features

        setupDebugFeatures: function ()
        {
            let isDev = window.location.hostname === "studio.boardgamearena.com";
            if (!isDev)
                return;

            window.debug = {};
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
