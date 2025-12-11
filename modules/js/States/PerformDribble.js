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
                console.log(notifData);
                console.log(notifData.swappedHexes[0]);
                console.log(notifData.swappedHexes[1]);
                let lhsHex = HexLib.createOffsetCoord(notifData.swappedHexes[0].hex_row, notifData.swappedHexes[0].hex_column);
                let rhsHex = HexLib.createOffsetCoord(notifData.swappedHexes[1].hex_row, notifData.swappedHexes[1].hex_column);

                return this._swapAnimated(lhsHex, rhsHex);
            },

            _swapAnimated: function(lhsHex, rhsHex) {
                
                let lhsPiece = this._root.pieceManager.getPieceAtPosition(lhsHex);
                let rhsPiece = this._root.pieceManager.getPieceAtPosition(rhsHex);

                const LHS_DEST_HEX = Board.convertOffsetCoordToHexId(rhsPiece.getPosition());
                const RHS_DEST_HEX = Board.convertOffsetCoordToHexId(lhsPiece.getPosition());

                let rhsOldPos = rhsPiece.getPosition();
                rhsPiece.setPosition(lhsPiece.getPosition());
                lhsPiece.setPosition(rhsOldPos);

                console.log("lhs piece is "  + lhsPiece.getDOMID()  + " rhs piece is " + rhsPiece.getDOMID());

                let animations = [];

                animations.push(this._root.slideToObject(lhsPiece.getDOMID(), LHS_DEST_HEX));
                animations.push(this._root.slideToObject(rhsPiece.getDOMID(), RHS_DEST_HEX));

                if (Board.getHexWithBall().equals(lhsHex)) {
                    animations.push(this._root.slideToObject('ball', LHS_DEST_HEX));
                    Board.setHexWithBall(lhsPiece.getPosition());
                }
                else {
                    animations.push(this._root.slideToObject('ball', RHS_DEST_HEX));
                    Board.setHexWithBall(rhsPiece.getPosition());
                }

                return this._root.bgaPlayDojoAnimation(dojo.fx.combine(animations));
            }
        });

        PerformDribble.getName = function () {
            return 'performDribble';
        };

        return PerformDribble;

    });