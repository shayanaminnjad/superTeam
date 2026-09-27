//TODO: probably I should separate BoardLogic, from this.

define([
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/STUIConstants.js",
], function (HexLib, UIConstants) {

    return {

        //Public Functions

        construct: function (root, ballPosObj, flip = false) {
            this._root = root;
            this._flip = !!flip;
            this._initBoard();
            this._addBall(ballPosObj);
        },

        //------------------------------------------------------------------------------------
        getMainBoardElement: function () {
            return document.getElementById('mainBoard');
        },
        //------------------------------------------------------------------------------------

        visualizePowerRequired: function (start, end, opponentPerspective = false, includeGoalieAsBlocking = true) {
            this.removeRequiredPowerVisualization();
            
            this._drawRuler(start, end);

            const blockingPiecesOwner = opponentPerspective ? this._root.player_id : this._root.opponentId;

            let hexesInLine = HexLib.getHexesInLine(start, end);

            let ownedHexes = this._root.pieceManager.getAllPiecesOwnedBy(blockingPiecesOwner);
            let blockingPieces = HexLib.getHexsInPath(start, end, this._hexInfo.size * 0.5, ownedHexes, this._hexInfo);

            for (let i = 0; i < hexesInLine.length; i++) {
                let el = document.getElementById(this.convertOffsetCoordToHexId(hexesInLine[i]));
                el.classList.add('inPath');
            }

            for (let i = 0; i < blockingPieces.length; i++) {

                if (!includeGoalieAsBlocking && blockingPieces[i].r >= 4 &&  blockingPieces[i].r <= 6 && (blockingPieces[i].c === 0 || blockingPieces[i].c === 16))
                    continue;

                let el = document.getElementById(this.convertOffsetCoordToHexId(blockingPieces[i]));
                el.classList.add('blocking');
                el.style.setProperty('--col', opponentPerspective ? "#1fc7c7" : "red");

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
            this._removeRuler();
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
            const anim = this._root.slideToObject('ballParent', HEX_ID);

            await this._root.bgaPlayDojoAnimation(anim);
        },

        //------------------------------------------------------------------------------------

        getMoveBallAnimation: function (destHex) {
            return this._root.slideToObject('ballParent', destHex);
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
        makeAllHexesNonSelectable: function () {
            document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
        },

        //------------------------------------------------------------------------------------

        getOrientation: function () {
            return this._orientation;
        },

        //------------------------------------------------------------------------------------

        //Landscape is the board's native orientation (matches mainBoard.jpg and every
        //constant in STUIConstants.js). Portrait is derived by rotating the landscape
        //layout 90 degrees, never by recalibrating a second set of constants.
        setOrientation: function (orientation) {
            if (orientation !== 'landscape' && orientation !== 'portrait') {
                throw `Invalid board orientation: ${orientation}`;
            }

            if (this._orientation === orientation) {
                return;
            }

            this._orientation = orientation;

            this.getMainBoardElement().classList.toggle('portrait', orientation === 'portrait');

            //Lets orientation-dependent UI outside #mainBoard (e.g. the direction icons
            //on the action buttons) react to the same rotation the board itself uses.
            document.body.classList.toggle('board-portrait', orientation === 'portrait');

            this._layoutHexes();

            if (this._hexWithBall !== null) {
                this._root.placeOnObject('ballParent', this.convertOffsetCoordToHexId(this._hexWithBall));
            }

            this._root.pieceManager.resnapAll();

            this._resnapHexAnchoredElements();
        },

        //------------------------------------------------------------------------------------

        //Private Functions

        _initBoard: function () {
            this._orientation = 'landscape';

            this._root.getGameAreaElement().insertAdjacentHTML('beforeend', `<div id="mainBoard"></div>`);

            const MAIN_BOARD_EL = document.getElementById('mainBoard');
            this._landscapeWidth = parseFloat(getComputedStyle(MAIN_BOARD_EL).width);
            this._landscapeHeight = parseFloat(getComputedStyle(MAIN_BOARD_EL).height);
            this._rulerWidth = (this._landscapeWidth * UIConstants.HEX_WIDTH_PERCENT) * 0.5;

            this._hexInfo = HexLib.createHexInfo(
                UIConstants.NORMALIZED_HEX_SIZE,
                UIConstants.NORMALIZED_HEX_INRADIUS,
                UIConstants.NORMALIZED_HEX_HORIZONTAL_SPACING,
                UIConstants.NORMALIZED_VERTICALL_GAP,
                UIConstants.NORMALIZED_HEX_INRADIUS + UIConstants.NORMALIZED_VERTICALL_GAP * 0.5);

            for (let x = 0; x < UIConstants.COLUMNS; x++) {
                let hexInColumn = (x + 1) % 2 == 1 ? UIConstants.EVEN_COLUMN_ROWS : UIConstants.ODD_COLUMN_ROWS;

                for (let y = 0; y < hexInColumn; y++) {
                    let hexId = `hex_${y}_${x}`;
                    let rect = this._projectHex(y, x);
                    MAIN_BOARD_EL.insertAdjacentHTML('afterbegin', `<div id='${hexId}' class="hex" style='left:${rect.left}px; top:${rect.top}px; width: ${rect.width}px; height: ${rect.height}px;'></div>`);
                }
            }

            MAIN_BOARD_EL.insertAdjacentHTML('beforeend', `<div id='pieces'></div>`);

            this._addSVGLayer(MAIN_BOARD_EL);
        },

        //------------------------------------------------------------------------------------

        //Updates every existing hex element in place to match the current orientation.
        _layoutHexes: function () {
            for (let x = 0; x < UIConstants.COLUMNS; x++) {
                let hexInColumn = (x + 1) % 2 == 1 ? UIConstants.EVEN_COLUMN_ROWS : UIConstants.ODD_COLUMN_ROWS;

                for (let y = 0; y < hexInColumn; y++) {
                    let hexEl = document.getElementById(`hex_${y}_${x}`);
                    let rect = this._projectHex(y, x);

                    hexEl.style.left = rect.left + 'px';
                    hexEl.style.top = rect.top + 'px';
                    hexEl.style.width = rect.width + 'px';
                    hexEl.style.height = rect.height + 'px';
                }
            }
        },

        //------------------------------------------------------------------------------------

        //Re-places any transient overlay tagged with data-hex-row/data-hex-column (e.g. the
        //shoot target tiles) back onto its hex, since orientation changes move hexes but
        //these overlays aren't tracked/resnapped by the piece manager.
        _resnapHexAnchoredElements: function () {
            document.querySelectorAll('[data-hex-row]').forEach(el => {
                const offsetCoord = HexLib.createOffsetCoord(parseInt(el.dataset.hexRow), parseInt(el.dataset.hexColumn));
                this._root.placeOnObject(el, this.convertOffsetCoordToHexId(offsetCoord));
            });
        },

        //------------------------------------------------------------------------------------

        //Pixel rect for a hex in the board's native landscape layout, always derived from
        //the fixed landscape board size so it stays correct no matter the current orientation.
        _computeLandscapeHexRect: function (row, col) {
            const BOARD_WIDTH = this._landscapeWidth;
            const BOARD_HEIGHT = this._landscapeHeight;

            //Mirroring around the last column keeps the same odd/even margin category
            //on both ends (column 0 and column 16 are both "odd"), so no other constant
            //needs adjusting for a flipped board.
            const projectedCol = this._flip ? (UIConstants.COLUMNS - 1 - col) : col;

            const LEFT_MARGIN = UIConstants.HORIZONTAL_MARGIN_PERCENT * BOARD_WIDTH;
            const TOP_MARGIN_ODD = UIConstants.TOP_MARGIN_ODD_PERCENT * BOARD_HEIGHT;
            const TOP_MARGIN_EVEN = UIConstants.TOP_MARGIN_EVEN_PERCENT * BOARD_HEIGHT;

            const HEX_WIDTH = BOARD_WIDTH * UIConstants.HEX_WIDTH_PERCENT;
            const HEX_HEIGHT = BOARD_HEIGHT * UIConstants.HEX_HEIGHT_PERCENT;
            const HEX_VERTICAL_SPACE = UIConstants.HEX_VERTICAL_GAP_PERCENT * BOARD_HEIGHT;
            const HEX_HORIZONTAL_SPACE = UIConstants.HEX_HORIZONTAL_SPACE_PERCENT * BOARD_WIDTH;

            const isOddColumn = (projectedCol + 1) % 2 == 1;
            const topMargin = isOddColumn ? TOP_MARGIN_ODD : TOP_MARGIN_EVEN;

            return {
                left: Math.round(LEFT_MARGIN + HEX_HORIZONTAL_SPACE * projectedCol),
                top: Math.round(topMargin + row * (HEX_HEIGHT + HEX_VERTICAL_SPACE)),
                width: Math.round(HEX_WIDTH),
                height: Math.round(HEX_HEIGHT)
            };
        },

        //------------------------------------------------------------------------------------

        //Rotates the landscape rect 90 degrees clockwise around the board's own center for
        //portrait mode, matching the CSS rotate(90deg) on #mainBoard's background layer.
        _projectHex: function (row, col) {
            const rect = this._computeLandscapeHexRect(row, col);

            if (this._orientation !== 'portrait') {
                return rect;
            }

            return {
                left: Math.round(this._landscapeHeight - rect.top - rect.height),
                top: Math.round(rect.left),
                width: rect.height,
                height: rect.width
            };
        },

        //------------------------------------------------------------------------------------

        _addBall: async function (ballData) {
            this._hexWithBall = HexLib.createOffsetCoord(ballData.hex_row, ballData.hex_column);

            const MAIN_BOARD_EL = this.getMainBoardElement();

            const ballParent = document.createElement('div');
            ballParent.id = 'ballParent';

            const ball = document.createElement('div');
            ball.id = 'ball';
            ball.className = 'ball';

            ballParent.appendChild(ball);
            MAIN_BOARD_EL.appendChild(ballParent);

            const HEX_ID = this.convertOffsetCoordToHexId(this._hexWithBall);
            const anim = this._root.slideToObject('ballParent', HEX_ID);

            await this._root.bgaPlayDojoAnimation(anim);
        },

        //------------------------------------------------------------------------------------

        _parseHexId: function (hexId) {
            let idEls = hexId.split("_");

            return [idEls[1], idEls[2]];
        },

        //------------------------------------------------------------------------------------

        _addSVGLayer: function (mainBoardEl) {
            this._svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            this._svg.setAttribute("id", "svg");
            mainBoardEl.appendChild(this._svg);
        },

        //------------------------------------------------------------------------------------
        _drawRuler: function (startHex, endHex) {
            const startEl = document.getElementById(this.convertOffsetCoordToHexId(startHex));
            const endEl = document.getElementById(this.convertOffsetCoordToHexId(endHex));


            const startCenterX = startEl.offsetLeft + startEl.offsetWidth / 2;
            const startCenterY = startEl.offsetTop + startEl.offsetHeight / 2;

            const endCenterX = endEl.offsetLeft + endEl.offsetWidth / 2;
            const endCenterY = endEl.offsetTop + endEl.offsetHeight / 2;

            const line = document.createElementNS("http://www.w3.org/2000/svg", "line");
            line.setAttribute("id", "ruler");
            line.setAttribute("x1", startCenterX);
            line.setAttribute("y1", startCenterY);
            line.setAttribute("x2", endCenterX);
            line.setAttribute("y2", endCenterY);
            line.setAttribute("stroke", "yellow");
            line.setAttribute("stroke-width", this._rulerWidth);
            this._svg.appendChild(line);
        },

        //------------------------------------------------------------------------------------

        _removeRuler: function () {
            let rulerEl = document.getElementById('ruler');

            if (rulerEl != null)
                rulerEl.remove();

            let rulerFront = document.getElementById('ruler_front');

            if (rulerFront != null)
                rulerFront.remove();
        },

        //Private Members
        _root: null,
        _hexWithBall: null,
        _hexInfo: null,
        _svg: null,
        _rulerWidth: null,
        _orientation: 'landscape',
        _landscapeWidth: null,
        _landscapeHeight: null,
        _flip: false
    };
});