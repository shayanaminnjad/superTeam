define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",],
    function (declare, BaseState) {

        const TeamAttributeAssignment = declare("st.TeamAttributeAssignment", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._root.myPlayerArea.getCoachBoard().setClickHandler((evt, skillSlot) => this.onSkillSelected(evt, skillSlot));
                this._root.myPlayerArea.getCoachBoard().makeAvailableBoxesSelectable();
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                this._cleanup();
            },

            _onInactivePlayerLeave: function () {
                this._cleanup();
            },

            onMultiActiveStateFinished: function() {
                this._cleanup();
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_cubePlaced: function (notifData) {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                myCoachBoard.placeCubeAnimated(parseInt(notifData.skill));
                myCoachBoard.makeAvailableBoxesSelectable();
            },

            onSkillSelected: function (evt, skillSlot) {
                evt.preventDefault();
                evt.stopPropagation();

                this._root.performServerAction("actPlacedAttrCube", { attributeId: skillSlot.skillIndex });
            },

            _cleanup: function () {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                myCoachBoard.removeClickHandler();
                myCoachBoard.makeAllNonSelectable();
            }
        });

        TeamAttributeAssignment.getName = function () {
            return 'teamAttributeAssignment';
        };

        return TeamAttributeAssignment;

    });