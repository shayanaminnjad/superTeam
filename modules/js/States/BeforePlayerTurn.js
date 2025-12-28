define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",],
    function (declare, BaseState) {

        const BeforePlayerTurn = declare("st.BeforePlayerTurn", BaseState, {

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
            notif_piecesRefreshed: function (notifData) {
                this._root.pieceManager.refreshAllPieces();
            },

        });

        BeforePlayerTurn.getName = function () {
            return 'beforePlayerTurn';
        };

        return BeforePlayerTurn;

    });