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

            this.setupMainBoard(gamedatas);
            
            // Setup game notifications to handle (see "setupNotifications" method below)
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
            
            switch( stateName )
            {
            case 'playerPeiceSetup':
                this.OnPlayerPeiceSetup(args);
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
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName, args );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {
                 case 'playerTurn':    
                    const playableCardsIds = args.playableCardsIds; // returned by the argPlayerTurn

                    // Add test action buttons in the action status bar, simulating a card click:
                    playableCardsIds.forEach(
                        cardId => this.statusBar.addActionButton(_('Play card with id ${card_id}').replace('${card_id}', cardId), () => this.onCardClick(cardId))
                    ); 

                    this.statusBar.addActionButton(_('Pass'), () => this.bgaPerformAction("actPass"), { color: 'secondary' }); 
                    break;
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

            this.setPiecesData(gamedatas);

            this.placePlayers();

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

        onHexClicked: function(evt)
        {
            evt.preventDefault();
            evt.stopPropagation();

            document.querySelectorAll('.moveableRange').forEach(div=> div.classList.remove('moveableRange'));

            if (this.doesHexHasMovablePeice(evt.currentTarget.id))
            {
                var offsetCoord = this.convertHexIdToOffsetCoord(evt.currentTarget.id);
                let cubeCoord = STHexLib.oddqToCubic(offsetCoord);

                let neighbors = STHexLib.getNeighbours(cubeCoord, 2, this.getBoardOffsetExtent());

                for (let i = 0; i < neighbors.length; i++) {

                    let hexId = this.convertCubicCoordToHexId(neighbors[i]);
                    if (!this.doesHexHasBlockingPeice(hexId))
                    {
                        let hexEl = document.getElementById(hexId);

                        hexEl.classList.add('moveableRange');
                    }
                }
            }
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
            this.myPiecesData = [];
            this.opponentPiecesData = [];

            let boardData = gameDatas["board"];

            for (let i = 0; i < boardData.length; ++i)
            {
                let el = boardData[i];

                let hexColumn = el.hex_column;
                let hexRow = el.hex_row;

                let offsetCoord = STHexLib.createOffsetCoord(hexRow, hexColumn);
                if (el.has_ball === '1')
                {
                    hexWithBall = offsetCoord;
                }
                else if (el.team_id !== null)
                {
                    console.log(el.player_role);
                    this.peices.push(this.createPlayerPeice(el.team_id, 0, offsetCoord, 1, el.player_role));
                }
                else
                {
                    console.log(el);
                    this.assert(false, "what is this info? " + el);
                }
            }
        },

        getHexWithBall: function()
        {
            return this.hexWithBall;
        },

        placePlayers: function()
        {
            document.querySelectorAll('.hasPeice').forEach(el=>el.classList.remove('hasPeice'));

            const MAIN_BOARD_EL = document.getElementById('mainBoard');

            for (let i = 0; i < this.peices.length; ++i) 
            {
                let currentPiece = this.peices[i]; 
                let hex = currentPiece.getPosition();

                if (currentPiece.getOwner() === this.player_id) 
                {
                    let hexId = this.convertOffsetCoordToHexId(hex);
                    let hexEl = document.getElementById(hexId);
                    hexEl.classList.add('hasPeice');
                }
                
                let hexCoordinate = this.getHexPixelCoordinate(hex);
                let color = currentPiece.getOwner() == this.player_id ? 'orange' : 'blue'; //this has to change!
                let status = currentPiece.isFresh() ? 'fresh' : 'exhuasted';
                let type = currentPiece.isCaptain() ? 'captain' : (currentPiece.isGoalie() ? 'goalie' : 'normal');

                MAIN_BOARD_EL.insertAdjacentHTML('beforeend', `<div id='${color}_${currentPiece.id}' class="piece ${status} ${type} ${color}" style='left:${hexCoordinate.left}px; top:${hexCoordinate.top}px;'>${type[0]}_${status[0]}</div>`);
            }
        },

        doesHexHasMovablePeice: function(hexId)
        {
            let result = false;

            let hexCoord = this.convertHexIdToOffsetCoord(hexId);
            let myTeamPeices = this.getHexesWithPlayer(this.player_id);

            for (let i = 0; i < myTeamPeices.length; ++i) 
            {
                if (myTeamPeices[i].hex.equals(hexCoord)) 
                {
                    let peice = myTeamPeices[i].peice;
                    if (!peice.isGoalie()) 
                    {
                        result = true;
                        break;
                    }
                }
            }

            return result;
        },

        doesHexHasBlockingPeice: function(hexId)
        {
            let result = false;

            let hexCoord = this.convertHexIdToOffsetCoord(hexId);
            let myTeamPeices = this.getHexesWithPlayer(this.playerId);
            let otherTeamPeices = this.getHexesWithPlayer(this.opponentId);

            let allPeices = myTeamPeices.concat(otherTeamPeices);

            for (let i = 0; i < allPeices.length; ++i) 
            {
                if (allPeices[i].hex.equals(hexCoord)) 
                {
                    result = true;
                    break;
                }
            }

            return result;
        },

        createPlayerPeice: function(owner, id, position, isFresh, pieceRole)
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
                }
            }
        },

        assert: function (condition, message = "Assertion failed") {
            if (!condition) {
                throw new Error(message);
            }
        },

        ///////////////////////////////////////////////////
        //// state response function
        OnPlayerPeiceSetup: function(args) 
        {
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
            
            // Example 1: standard notification handling
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            
            // Example 2: standard notification handling + tell the user interface to wait
            //            during 3 seconds after calling the method in order to let the players
            //            see what is happening in the game.
            // dojo.subscribe( 'cardPlayed', this, "notif_cardPlayed" );
            // this.notifqueue.setSynchronous( 'cardPlayed', 3000 );
            // 
        },  
        
        // TODO: from this point and below, you can write your game notifications handling methods
        
        /*
        Example:
        
        notif_cardPlayed: function( notif )
        {
            console.log( 'notif_cardPlayed' );
            console.log( notif );
            
            // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
            
            // TODO: play the card in the user interface.
        },    
        
        */

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

        peices: [],

        hexWithBall: null,

        //----------------------- general variables

        startHex: null,

        endHex: null,
   });             
});
