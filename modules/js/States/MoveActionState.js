define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/PlayerAreaManager.js",
    g_gamethemeurl + "modules/js/STHexLib.js",],
    function (declare, BaseState, Board, PlayerAreaManager, STHexLib) {

        const MoveActionState = declare("st.MoveActionState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter(args) {
                document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));

                this._highlightSelectedPiece(args);
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
                document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
                document.querySelector('.selected').classList.remove('selected');
            },

            _onInactivePlayerLeave() {
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_movedPiece: async function (notifData) {
                let destHex = STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column);
                let piece = this._root.pieceManager.getPiece(notifData.movedPiece);

                if (parseInt(notifData.spentMorale) === 1) {
                    await PlayerAreaManager.reducePlayerMorale(1, piece.getDOMID());
                }

                piece.setPosition(destHex);

                let animations = [];

                const DEST_HEX_ID = Board.convertOffsetCoordToHexId(destHex);
                animations.push(this._root.slideToObject(piece.getDOMID(), DEST_HEX_ID));

                if (parseInt(notifData.ballMoved)) {
                    Board.setHexWithBall(STHexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column));
                    animations.push(Board.getMoveBallAnimation(DEST_HEX_ID));
                }

                await this._root.bgaPlayDojoAnimation(dojo.fx.combine(animations));
            },

            //Private Methods
            _highlightSelectedPiece: function (args) {
                let selectedPiece = args.selectedPiece;
                let moveableHexes = args.moveableHexes;

                console.log("selected piece" + selectedPiece);

                let piece = this._root.pieceManager.getPiece(selectedPiece);
                piece.getDOMElement().classList.add('selected');

                for (let i = 0; i < moveableHexes.length; i++) {
                    let hex = moveableHexes[i];
                    let DOMHexId = Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(hex.hex_row, hex.hex_column));

                    document.getElementById(DOMHexId).classList.add('selectable');
                }
            }

        });

        MoveActionState.getName = function () {
            return 'moveAction';
        };

        return MoveActionState;

    });