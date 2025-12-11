define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js"
],
    function (declare, BaseState, HexLib) {

        const PerformTackle = declare("st.PerformTackle", BaseState, {

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
            notif_tacklePerformed: function (notifData) {
                let lhsHex = HexLib.createOffsetCoord(notifData.swappedHexes[0].hex_row, notifData.swappedHexes[0].hex_column);
                let rhsHex = HexLib.createOffsetCoord(notifData.swappedHexes[1].hex_row, notifData.swappedHexes[1].hex_column);

                let animations = this._root.pieceManager.swapPeicesAndGetAnimation(lhsHex, rhsHex);
                return this._root.bgaPlayDojoAnimation(dojo.fx.combine(animations));
            }

        });

        PerformTackle.getName = function () {
            return 'performTackle';
        };

        return PerformTackle;

    });