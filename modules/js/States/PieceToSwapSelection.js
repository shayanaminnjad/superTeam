define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js",
        g_gamethemeurl + "modules/js/STHexLib.js",
        g_gamethemeurl + "modules/js/BoardPresentation.js"
    ],
    function (declare, BaseState, STHexLib, Board) {

        const PieceToSwapSelection = declare("st.PieceToSwapSelection", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                let pieceToSwap = [];

                for (const space of args.piecesToSwap) {
                    pieceToSwap.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                Board.makeHexesSelectable(pieceToSwap);
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                Board.makeAllHexesNonSelectable();
            },

            _onInactivePlayerLeave: function () {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('selectable')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    Board.makeAllHexesNonSelectable();

                    await this._root.performServerAction("actPieceSelected", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r });
                }
            },

            getName: function () {
                return this.constructor.getName();
            }
        });

        PieceToSwapSelection.getName = function () {
            return 'pieceToSwapSelection';
        };

        return PieceToSwapSelection;

    });