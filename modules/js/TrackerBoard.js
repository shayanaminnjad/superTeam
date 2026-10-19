define([
    g_gamethemeurl + "modules/js/STUIConstants.js",
], function (UIConstants) {

    return {

        //Public Functions
        construct: function (root, timeIndex, orangeGoals, blueGoals, isFinalTurn, isAdvancedMode) {
            this._root = root;
            this._initTrackerBoard();
            this._initSupplyPile();
            this._initGoalTracker(orangeGoals, blueGoals);
            this._initLastTurnBanner();
            this._initGameModeBadge(isAdvancedMode);
            this.setTrackerTo(timeIndex);
            this.setFinalTurnBannerVisible(isFinalTurn);
        },

        setFinalTurnBannerVisible: function(isVisible)
        {
            this._lastTurnEl.style.display = isVisible ? '' : 'none';
        },

        animateTrackerTo: function(timeIndex)
        {
            let positionEl = this._positions[timeIndex];

            return this._root.bgaPlayDojoAnimation(this._root.slideToObject(this._timeTrackCubeEl, positionEl));
        },

        setTrackerTo: function(timeIndex)
        {
            let positionEl = this._positions[timeIndex];

            this._root.placeOnObject(this._timeTrackCubeEl, positionEl);
        },

        setOrangeGoals: function(goals)
        {
            let el = document.getElementById('orange_scoreBoard');
            el.innerText = goals;
        },

        setBlueGoals: function(goals)
        {
            let el = document.getElementById('blue_scoreBoard');
            el.innerText = goals;
        },

        //Private Functions
        _initTrackerBoard: function()
        {
            this._root.getGameAreaElement().insertAdjacentHTML('beforeend', `<div id="trackerBoard"></div>`);

            this._trackerBoard = document.getElementById('trackerBoard');
            this._computedBoardWidth = parseFloat(getComputedStyle(this._trackerBoard).width);
            this._computedBoardHeight = parseFloat(getComputedStyle(this._trackerBoard).height);

            const CUBE_POS_LEFT_MARGIN = UIConstants.TRACKER_FIRST_CUBE_POSITION_FROM_LEFT_PERCENT * this._computedBoardWidth;
            const CUBE_POS_TOP_MARGIN = UIConstants.TRACKER_FIRST_CUBE_POSITION_FROM_TOP_PERCENT * this._computedBoardHeight;
            const CUBE_POS_SPACING = UIConstants.TRACKER_SPACE_BETWEEN_POSITIONS_PERCENT * this._computedBoardWidth;
            const MIDDLE_SECTION_LENGTH = UIConstants.TRACKER_MIDDLE_SECTION_LENGTH_PERCENT * this._computedBoardWidth;

            const CUBE_SIDE = UIConstants.TRACKER_TIME_CUBE_SIDE_TO_WIDTH_PERCENT * this._computedBoardWidth; //move down!

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

                this._trackerBoard.appendChild(cubePosEl);
                this._positions.push(cubePosEl)

                distanceSoFar += CUBE_SIDE + CUBE_POS_SPACING;

                if (i == (POSITION_ON_EACH_SIDE - 1))
                    distanceSoFar += MIDDLE_SECTION_LENGTH - CUBE_POS_SPACING;
            }

            this._initTimeTracker(CUBE_SIDE);
        },

        _initTimeTracker: function(sideSize)
        {
            this._timeTrackCubeEl = document.createElement('div');
            this._timeTrackCubeEl.id = 'timeTrackCube';
            this._timeTrackCubeEl.className = 'cube';
            this._timeTrackCubeEl.style.width = `${sideSize}px`;
            this._timeTrackCubeEl.style.height = `${sideSize}px`;

            this._trackerBoard.appendChild(this._timeTrackCubeEl);
        },

        _initSupplyPile: function() {
            const supplyDeck = document.createElement('div');
            supplyDeck.id = 'supply';
            supplyDeck.style.position = 'absolute'
            supplyDeck.style.left = `${UIConstants.TRACKER_FATIGUE_DECK_FROM_LEFT_PERCENT * this._computedBoardWidth}px`;
            supplyDeck.style.top = `${UIConstants.TRACKER_FATIGUE_DECK_FROM_TOP_PERCENT * this._computedBoardHeight}px`;
            supplyDeck.style.scale = "60%";
            supplyDeck.style.transform= "translateY(-48%) translateX(-10%) rotate(90deg)";
            this._trackerBoard.appendChild(supplyDeck);
        },

        _initGoalTracker: function(orangeGoals, blueGoals)
        {
            const top = `${0.307627118 * this._computedBoardHeight}px`;
            const orangeLeft = this._root.myColor === 'red' ? `${0.503323262839 * this._computedBoardWidth}px` : `${0.43021148 * this._computedBoardWidth}px`;
            const blueLeft = this._root.myColor === 'blue' ? `${0.503323262839 * this._computedBoardWidth}px` : `${0.43021148 * this._computedBoardWidth}px`;

            const orangeEl = document.createElement('div');
            orangeEl.id = "orange_scoreBoard";
            orangeEl.className = 'scoreBoard';
            orangeEl.style.backgroundColor = '#e67e22';
            orangeEl.style.left = orangeLeft;
            orangeEl.style.top = top;
            orangeEl.innerText = orangeGoals;

            this._trackerBoard.appendChild(orangeEl);

            const blueEl = document.createElement('div');
            blueEl.id = "blue_scoreBoard";
            blueEl.className = 'scoreBoard';
            blueEl.style.left = blueLeft;
            blueEl.style.top = top;
            blueEl.style.backgroundColor = '#3498db';
            blueEl.innerText = blueGoals;

            this._trackerBoard.appendChild(blueEl);
        },

        _initLastTurnBanner: function()
        {
            this._lastTurnEl = document.createElement('div');
            this._lastTurnEl.id = 'lastTurnBanner';
            this._lastTurnEl.textContent = _('LAST TURN');
            this._lastTurnEl.style.display = 'none';

            this._trackerBoard.appendChild(this._lastTurnEl);
        },

        _initGameModeBadge: function(isAdvancedMode)
        {
            const badgeEl = document.createElement('div');
            badgeEl.id = 'gameModeBadge';
            badgeEl.classList.toggle('advanced', isAdvancedMode);
            badgeEl.textContent = isAdvancedMode ? _('Advanced mode: team and captain powers in play') : _('Basic mode: no team or captain powers');

            this._trackerBoard.appendChild(badgeEl);
        },

        _root: null,
        _lastTurnEl: null,
        _timeTrackCubeEl: null,
        _positions: [],
        _trackerBoard: null,
        _computedBoardWidth: 0,
        _computedBoardHeight: 0
    };
});