define([
    g_gamethemeurl + "modules/js/STUIConstants.js",
], function (UIConstants) {

    return {

        //Public Functions
        construct: function (root, timeIndex, orangeGoals, blueGoals) {
            this._root = root;
            this._initTrackerBoard();
            this.setTrackerTo(timeIndex);
        },

        animateTrackerTo(timeIndex)
        {
            let positionEl = this._positions[timeIndex];

            return this._root.bgaPlayDojoAnimation(this._root.slideToObject(this._timeTrackCubeEl, positionEl));
        },

        setTrackerTo(timeIndex)
        {
            let positionEl = this._positions[timeIndex];

            this._root.placeOnObject(this._timeTrackCubeEl, positionEl);
        },

        //Private Functions
        _initTrackerBoard: function()
        {
            this._root.getGameAreaElement().insertAdjacentHTML('beforeend', `<div id="trackerBoard"></div>`);

            const TRACKER_BOARD = document.getElementById('trackerBoard');
            const BOARD_WIDTH = parseFloat(getComputedStyle(TRACKER_BOARD).width);
            const BOARD_HEIGHT = parseFloat(getComputedStyle(TRACKER_BOARD).height);

            const CUBE_POS_LEFT_MARGIN = UIConstants.TRACKER_FIRST_CUBE_POSITION_FROM_LEFT_PERCENT * BOARD_WIDTH;
            const CUBE_POS_TOP_MARGIN = UIConstants.TRACKER_FIRST_CUBE_POSITION_FROM_TOP_PERCENT * BOARD_HEIGHT;
            const CUBE_POS_SPACING = UIConstants.TRACKER_SPACE_BETWEEN_POSITIONS_PERCENT * BOARD_WIDTH;
            const MIDDLE_SECTION_LENGTH = UIConstants.TRACKER_MIDDLE_SECTION_LENGTH_PERCENT * BOARD_WIDTH;

            const CUBE_SIDE = UIConstants.TRACKER_TIME_CUBE_SIDE_TO_WIDTH_PERCENT * BOARD_WIDTH; //move down!

            const CUBE_POSITIONS_COUNT = 8;
            const POSITION_ON_EACH_SIDE = 4;
            let distanceSoFar = CUBE_POS_LEFT_MARGIN;
            for(let i = 0; i < CUBE_POSITIONS_COUNT; i++)
            {
                let cubePosEl = document.createElement('div');

                cubePosEl.style.position = 'absolute';
                cubePosEl.style.left = `${distanceSoFar}px`;
                cubePosEl.style.top = `${CUBE_POS_TOP_MARGIN}px`;
                cubePosEl.style.width = `${CUBE_SIDE}px`;
                cubePosEl.style.height = `${CUBE_SIDE}px`;

                TRACKER_BOARD.appendChild(cubePosEl);
                this._positions.push(cubePosEl)

                distanceSoFar += CUBE_SIDE + CUBE_POS_SPACING;

                if (i == (POSITION_ON_EACH_SIDE - 1))
                    distanceSoFar += MIDDLE_SECTION_LENGTH - CUBE_POS_SPACING;
            }

            this._initTimeTracker(CUBE_SIDE, TRACKER_BOARD);

            this._initSupplyPile(BOARD_HEIGHT, BOARD_WIDTH , TRACKER_BOARD);
        },

        _initTimeTracker: function(sideSize, trackerBoard)
        {
            this._timeTrackCubeEl = document.createElement('div');
            this._timeTrackCubeEl.id = 'timeTrackCube';
            this._timeTrackCubeEl.className = 'cube';
            this._timeTrackCubeEl.style.width = `${sideSize}px`;
            this._timeTrackCubeEl.style.height = `${sideSize}px`;

            trackerBoard.appendChild(this._timeTrackCubeEl);
        },

        _initSupplyPile: function(boardHeight, boardWidth, trackerBoard) {
            const supplyDeck = document.createElement('div');
            supplyDeck.id = 'supply';
            supplyDeck.style.position = 'absolute'
            supplyDeck.style.left = `${UIConstants.TRACKER_FATIGUE_DECK_FROM_LEFT_PERCENT * boardWidth}px`;
            supplyDeck.style.top = `${UIConstants.TRACKER_FATIGUE_DECK_FROM_TOP_PERCENT * boardHeight}px`;
            supplyDeck.style.scale = "60%";
            supplyDeck.style.transform= "translateY(-48%) translateX(-10%) rotate(90deg)";
            trackerBoard.appendChild(supplyDeck);
        },

        _initGoalTracker: function(orangeGoals, blueGoals)
        {

        },

        _root: null,
        _timeTrackCubeEl: null,
        _positions: []
    };
});