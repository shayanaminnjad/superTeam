define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
],
    function (declare, BaseState, HexLib, Board) {

        const PlayerPieceSetupState = declare("st.PlayerPieceState", [BaseState], {

            //Public Methods
            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter(args) {
                document.querySelectorAll('.availableToPlacePiece').forEach(div => div.classList.remove('availableToPlacePiece'));

                let availableStartingPositions = args.availableStartingPositions;

                for (let i = 0; i < availableStartingPositions.length; ++i) {
                    let el = availableStartingPositions[i];

                    let hexId = Board.convertOffsetCoordToHexId(HexLib.createOffsetCoord(el.hex_row, el.hex_column));
                    let hexEl = document.getElementById(hexId);

                    hexEl.classList.add('availableToPlacePiece');
                }
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
                    document.querySelectorAll('.availableToPlacePiece').forEach(div => div.classList.remove('availableToPlacePiece'));
            },

            _onInactivePlayerLeave() {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('availableToPlacePiece')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    await this._root.bgaPerformAction("actPlacedPiece", { placedPiece: this._stateData.args.pieceToPlace, hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
                }
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_placedPiece: async function (notifData) {
                let hex = HexLib.createOffsetCoord(notifData.hex_row, notifData.hex_column);
                this._root.pieceManager.getPiece(notifData.placed_piece).setPosition(hex);

                await this._root.pieceManager.animatePieceFromPlayerBoardToHex(notifData.placed_piece, Board.convertOffsetCoordToHexId(hex));
            },
        });


        PlayerPieceSetupState.getName = function () {
            return 'playerPieceSetup';
        }
        return PlayerPieceSetupState;

    });