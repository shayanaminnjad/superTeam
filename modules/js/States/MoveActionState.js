define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/PlayerPanelManager.js",
    g_gamethemeurl + "modules/js/STHexLib.js"],
    function (declare, BaseState, Board, PlayerPanelManager, STHexLib) {

        const MoveActionState = declare("st.MoveActionState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter(args) {
                this._resetMovementVisualCues();
                this._setMovementVisualCues(args);
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
                this._resetMovementVisualCues();
            },

            _onInactivePlayerLeave() {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('selectable')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    try {
                        this._resetMovementVisualCues();
                        await this._root.performServerAction("actPieceMoved", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r });
                    }
                    catch (e) {
                        this._setMovementVisualCues(this._args)
                    }

                }
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications

            //Private Methods
            _setMovementVisualCues: function (args) {
                let selectedPiece = args.selectedPiece;
                let moveableHexes = args.moveableHexes;

                let piece = this._root.pieceManager.getPiece(selectedPiece);
                piece.getDOMElement().classList.add('selected');

                for (let i = 0; i < moveableHexes.length; i++) {
                    let hex = moveableHexes[i];
                    let DOMHexId = Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(hex.hex_row, hex.hex_column));

                    document.getElementById(DOMHexId).classList.add('selectable');
                }
            },

            _resetMovementVisualCues: function () {
                document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
                let selected = document.querySelector('.selected');

                if (selected != null)
                    selected.classList.remove('selected')
            }
        });

        MoveActionState.getName = function () {
            return 'moveAction';
        };

        return MoveActionState;

    });