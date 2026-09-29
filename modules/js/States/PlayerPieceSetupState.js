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
                this._highlightAvailablePlaces();

                const title = $('pagemaintitletext');

                title.innerHTML = title.innerHTML.replace(
                    `[${args.pieceType}]`,
                    `<span class="piece ${args.pieceType} ${this._root.myColor} fresh"
            style="
            position: static;
            display: inline-block;
            width: 32px;
            height: 32px;
            overflow: visible;
            vertical-align: middle;
        "></span>`
                );
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
                this._disableHighlight();

            },

            _onInactivePlayerLeave() {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('availableToPlacePiece')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    this._disableHighlight();

                    try {
                        await this._root.performServerAction("actPlacedPiece", { placedPiece: this._stateData.args.pieceToPlace, hexColumn: offsetCoord.c, hexRow: offsetCoord.r }, { lock: true, checkAction: true });
                    }
                    catch (e) {
                        this._highlightAvailablePlaces();
                    }
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

            notif_resetPlayerPieces: async function (notifData) {
                if (this._root.isCurrentPlayerActive()) {
                    this._disableHighlight();
                }

                await this._root.pieceManager.animateResetPlayerPieces(parseInt(notifData.playerId), parseInt(notifData.goalieRow));

                if (this._root.isCurrentPlayerActive()) {
                    this._highlightAvailablePlaces();
                }
            },

            _highlightAvailablePlaces()
            {
                document.querySelectorAll('.availableToPlacePiece').forEach(div => div.classList.remove('availableToPlacePiece'));

                let availableStartingPositions = this._args.availableStartingPositions;

                for (let i = 0; i < availableStartingPositions.length; ++i) {
                    let el = availableStartingPositions[i];

                    let hexId = Board.convertOffsetCoordToHexId(HexLib.createOffsetCoord(el.hex_row, el.hex_column));
                    let hexEl = document.getElementById(hexId);

                    hexEl.classList.add('availableToPlacePiece');
                }
            },

            _disableHighlight() {
                //not efficient, works for now.
                document.querySelectorAll('.availableToPlacePiece').forEach(div => div.classList.remove('availableToPlacePiece'));
            },
        });


        PlayerPieceSetupState.getName = function () {
            return 'playerPieceSetup';
        }
        return PlayerPieceSetupState;

    });