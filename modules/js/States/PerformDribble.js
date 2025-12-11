define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
],
    function (declare, BaseState, HexLib, Board) {

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

                return this._swapAnimated(lhsHex, rhsHex);
            },

            _swapAnimated: function(lhsHex, rhsHex) {
                let animations = this._root.pieceManager.swapPeicesAndGetAnimation(lhsHex, rhsHex);

                if (Board.getHexWithBall().equals(lhsHex)) {
                    animations.push(this._root.slideToObject('ball', LHS_DEST_HEX_ID));
                    Board.setHexWithBall(rhsHex);
                }
                else {
                    animations.push(this._root.slideToObject('ball', RHS_DEST_HEX_ID));
                    Board.setHexWithBall(lhsHex);
                }

                return this._root.bgaPlayDojoAnimation(dojo.fx.combine(animations));
            }
        });

        PerformDribble.getName = function () {
            return 'performDribble';
        };

        return PerformDribble;

    });