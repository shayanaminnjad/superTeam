define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js"],
    function (declare, BaseState, HexLib, Board) {

        const TacklePerformerSelection = declare("st.TacklePerformerSelection", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                let tacklablePieces = [];

                for (const space of args.HexesWithTacklingPieces) {
                    tacklablePieces.push(Board.convertOffsetCoordToHexId(HexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                Board.makeHexesSelectable(tacklablePieces);
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

                    await this._root.performServerAction("actPieceSelected", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r });
                }
            },

            getName: function () {
                return this.constructor.getName();
            }
        });

        TacklePerformerSelection.getName = function () {
            return 'tacklePerformerSelection';
        };

        return TacklePerformerSelection;

    });