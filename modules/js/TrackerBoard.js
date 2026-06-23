define([
    g_gamethemeurl + "modules/js/STUIConstants.js",
], function (UIConstants) {

    return {

        //Public Functions
        construct: function (root, timeIndex, orangeGoals, blueGoals) {
            this._root = root;
            this._initTrackerBoard(timeIndex);
        },

        AnimateTrackerTo(timeIndex)
        {

        },

        SetTrackerTo(timeIndex)
        {

        },

        //Private Functions
        _initTrackerBoard: function(timeIndex)
        {
            this._root.getGameAreaElement().insertAdjacentHTML('beforeend', `<div id="trackerBoard"></div>`);

            const TRACKER_BOARD = document.getElementById('trackerBoard');
            const BOARD_WIDTH = parseFloat(getComputedStyle(TRACKER_BOARD).width);
            const BOARD_HEIGHT = parseFloat(getComputedStyle(TRACKER_BOARD).height);

            this._leftMargin = UIConstants.TRACKER_FIRST_CUBE_POSITION_FROM_LEFT_PERCENT * BOARD_WIDTH;
            this._topMargin = UIConstants.TRACKER_FIRST_CUBE_POSITION_FROM_TOP_PERCENT * BOARD_HEIGHT;
            this._positionSpacing = UIConstants.TRACKER_SPACE_BETWEEN_POSITIONS_PERCENT * BOARD_WIDTH;
            this._middleSectionLength = UIConstants.TRACKER_MIDDLE_SECTION_LENGTH_PERCENT * BOARD_WIDTH;

            const CUBE_SIDE = UIConstants.TRACKER_TIME_CUBE_SIDE_TO_WIDTH_PERCENT * BOARD_WIDTH;

            const timeTrackCube = document.createElement('div');
            timeTrackCube.id = 'timeTrackCube';
            timeTrackCube.className = 'cube';
            timeTrackCube.width = `${CUBE_SIDE}px`;
            timeTrackCube.height = `${CUBE_SIDE}px`;

            TRACKER_BOARD.appendChild(timeTrackCube);
        },

        _initTimeTracker: function(timeIndex)
        {

        },

        _initGoalTracker: function(orangeGoals, blueGoals)
        {

        },

        _root: null,

        _leftMargin: 0,
        _topMargin: 0,
        _positionSpacing: 0,
        _middleSectionLength: 0,
        _positionsOnEachSide: 4

    };
});