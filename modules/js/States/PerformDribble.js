define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/GameEventProcessor.js"
],
    function (declare, BaseState, HexLib, Board, GameEventProcessor) {

        const PerformDribble = declare("st.PerformDribble", BaseState, {

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
            notif_dribblePerformed: function (notifData) {
                let lhsHex = HexLib.createOffsetCoord(notifData.swappedHexes[0].hex_row, notifData.swappedHexes[0].hex_column);
                let rhsHex = HexLib.createOffsetCoord(notifData.swappedHexes[1].hex_row, notifData.swappedHexes[1].hex_column);
                let ballHex = HexLib.createOffsetCoord(notifData.ballPosition.hex_row, notifData.ballPosition.hex_column);

                GameEventProcessor.SwapAnimated(lhsHex, rhsHex, ballHex);
            }
        });

        PerformDribble.getName = function () {
            return 'performDribble';
        };

        return PerformDribble;

    });