//TODO: probably I should separate BoardLogic, from this.

define([
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/STUIConstants.js",
], function (HexLib, UIConstants) {

    return {

        //Public Functions
        construct: function (root, ballPosObj) {
            this._root = root;

            this._initBoard();

            this._addBall(ballPosObj);
        },

        //------------------------------------------------------------------------------------
        getMainBoardElement: function () {
            return document.getElementById('mainBoard');
        },
        //------------------------------------------------------------------------------------

        visualizePowerRequired: function (start, end) {
            this.removeRequiredPowerVisualization();

            let hexesInLine = HexLib.getHexesInLine(start, end);

            let opponentHexes = this._root.pieceManager.getAllPiecesOwnedBy(this._root.opponentId);
            let blockingPieces = HexLib.getHexsInPath(start, end, 1.2, opponentHexes);

            for (let i = 0; i < hexesInLine.length; i++) {
                let el = document.getElementById(this.convertOffsetCoordToHexId(hexesInLine[i]));
                el.classList.add('inPath');
            }

            for (let i = 0; i < blockingPieces.length; i++) {
                let el = document.getElementById(this.convertOffsetCoordToHexId(blockingPieces[i]));
                el.classList.add('blocking');

                let tempEl = document.createElement("span");
                tempEl.innerHTML = `<span class="powerIndication">+1</span>`;
                let powerIndicator = tempEl.firstElementChild;
                this._root.getGameAreaElement().appendChild(powerIndicator);

                this._root.placeOnObject(powerIndicator, el);
                //TODO: sometimes this might be covered by another piece!
                powerIndicator.style.top = (parseInt(powerIndicator.style.top) - 30) + 'px';
            }
        },

        //------------------------------------------------------------------------------------

        removeRequiredPowerVisualization: function () {
            document.querySelectorAll('.inPath').forEach(el => el.classList.remove('inPath'));
            document.querySelectorAll('.blocking').forEach(el => el.classList.remove('blocking'));
            document.querySelectorAll('.powerIndication').forEach(el => el.remove());
        },

        //------------------------------------------------------------------------------------

        highlightHex: function (hexId) {
            document.getElementById(hexId).classList.add('highlight');
        },

        //------------------------------------------------------------------------------------

        removeHexHighlights: function () {
            document.querySelectorAll('.highlight').forEach(e => e.classList.remove('highlight'));
        },

        //------------------------------------------------------------------------------------

        getHexWithBall: function () {
            return this._hexWithBall;
        },

        setHexWithBall: function (offsetCoord) {
            return this._hexWithBall = offsetCoord;
        },

        //------------------------------------------------------------------------------------

        passBall: async function (OffsetCoord) {
            this.setHexWithBall(OffsetCoord);

            const HEX_ID = this.convertOffsetCoordToHexId(OffsetCoord);
            const anim = this._root.slideToObject('ball', HEX_ID);

            await this._root.bgaPlayDojoAnimation(anim);
        },

        //------------------------------------------------------------------------------------

        getMoveBallAnimation: function (destHex) {
            return this._root.slideToObject('ball', destHex);
        },
        //------------------------------------------------------------------------------------

        convertOffsetCoordToHexId: function (offsetCoord) {
            return 'hex_' + offsetCoord.r + '_' + offsetCoord.c;
        },

        //------------------------------------------------------------------------------------

        convertHexIdToOffsetCoord: function (hexId) {
            var parsedHexId = this._parseHexId(hexId);
            return HexLib.createOffsetCoord(parsedHexId[0], parsedHexId[1]);
        },

        //------------------------------------------------------------------------------------
        makeAllHexesSelectableExcept: function (hexesToExclude) {
            document.querySelectorAll('.hex').forEach(el => el.classList.add('selectable'));

            for (let i = 0; i < hexesToExclude.length; i++) {
                document.getElementById(hexesToExclude[i]).classList.remove('selectable');
            }

        },
        //------------------------------------------------------------------------------------
        makeHexesSelectable: function (hexes) {
            for (const hex of hexes) {
                document.getElementById(hex).classList.add('selectable');
            }
        },
        //------------------------------------------------------------------------------------
        makeAllHexesNonSelectable: function() {
            document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
        },
        
        //------------------------------------------------------------------------------------

        //Private Functions

        _initBoard: function () {
            this._root.getGameAreaElement().insertAdjacentHTML('beforeend', `<div id="mainBoard"></div>`);

            const MAIN_BOARD_EL = document.getElementById('mainBoard');
            const BOARD_WIDTH = parseFloat(getComputedStyle(MAIN_BOARD_EL).width);
            const BOARD_HEIGHT = parseFloat(getComputedStyle(MAIN_BOARD_EL).height);

            const LEFT_MARGIN = UIConstants.HORIZONTAL_MARGIN_PERCENT * BOARD_WIDTH;
            const TOP_MARGIN_ODD = UIConstants.TOP_MARGIN_ODD_PERCENT * BOARD_HEIGHT;
            const TOP_MARGIN_EVEN = UIConstants.TOP_MARGIN_EVEN_PERCENT * BOARD_HEIGHT;

            const HEX_WIDTH = BOARD_WIDTH * UIConstants.HEX_WIDTH_PERCENT;
            const HEX_HEIGHT = BOARD_HEIGHT * UIConstants.HEX_HEIGHT_PERCENT;
            const HEX_WIDTH_ROUND = Math.round(HEX_WIDTH);
            const HEX_HEIGHT_ROUND = Math.round(HEX_HEIGHT);
            const HEX_VERTICAL_SPACE = UIConstants.HEX_VERTICAL_GAP_PERCENT * BOARD_HEIGHT;
            const HEX_HORIZONTAL_SPACE = UIConstants.HEX_HORIZONTAL_SPACE_PERCENT * BOARD_WIDTH;

            for (let x = 0; x < UIConstants.COLUMNS; x++) {
                let hexInColumn = (x + 1) % 2 == 1 ? UIConstants.EVEN_COLUMN_ROWS : UIConstants.ODD_COLUMN_ROWS;
                let left = Math.round(LEFT_MARGIN + (HEX_HORIZONTAL_SPACE) * x);
                let currentTopMargin = (x + 1) % 2 == 1 ? TOP_MARGIN_ODD : TOP_MARGIN_EVEN;

                for (let y = 0; y < hexInColumn; y++) {
                    let hexId = `hex_${y}_${x}`;
                    let top = Math.round(currentTopMargin + (y * (HEX_HEIGHT + HEX_VERTICAL_SPACE)));
                    MAIN_BOARD_EL.insertAdjacentHTML('afterbegin', `<div id='${hexId}' class="hex" style='left:${left}px; top:${top}px; width: ${HEX_WIDTH_ROUND}px; height: ${HEX_HEIGHT_ROUND}px;'></div>`);
                }
            }

            MAIN_BOARD_EL.insertAdjacentHTML('beforeend', `<div id='pieces'></div>`);
        },

        //------------------------------------------------------------------------------------

        _addBall: async function (ballData) {
            this._hexWithBall = HexLib.createOffsetCoord(ballData.hex_row, ballData.hex_column);

            const MAIN_BOARD_EL = this.getMainBoardElement();
            MAIN_BOARD_EL.insertAdjacentHTML('beforeend', `<div id="ball" class="ball" ></div>`);

            const HEX_ID = this.convertOffsetCoordToHexId(this._hexWithBall);
            const anim = this._root.slideToObject('ball', HEX_ID);

            await this._root.bgaPlayDojoAnimation(anim);
        },

        //------------------------------------------------------------------------------------

        _parseHexId: function (hexId) {
            let idEls = hexId.split("_");

            return [idEls[1], idEls[2]];
        },

        //Private Members
        _root: null,
        _hexWithBall: null,
    };
});