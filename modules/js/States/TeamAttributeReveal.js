define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js"],
    function (declare, BaseState) {

        const TeamAttributeReveal = declare("st.TeamAttributeReveal", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
            },

            _onInactivePlayerLeave: function () {
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_attributeAssignmentFinished: function (notifData) {
                const opponentAttributes = notifData[this._root.opponentId];

                const opponentCoachBoard = this._root.opponentPlayerArea.getCoachBoard();
                return opponentCoachBoard.updateOwnerDataAnimated(opponentAttributes);
            }

        });

        TeamAttributeReveal.getName = function () {
            return 'teamAttributeReveal';
        };

        return TeamAttributeReveal;

    });