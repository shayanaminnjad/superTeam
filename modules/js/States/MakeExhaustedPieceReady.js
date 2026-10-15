define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js",
        g_gamethemeurl + "modules/js/STHexLib.js",
        g_gamethemeurl + "modules/js/BoardPresentation.js",],
    function (declare, BaseState, STHexLib, Board) {

        const MakeExhaustedPieceReady = declare("st.MakeExhaustedPieceReady", BaseState, {

            constructor: function (root, stateData) {
            },

            _onActivePlayerEnter(args) {
                let exhaustedPieces = [];

                for (const space of args.exhaustedPieces) {
                    exhaustedPieces.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                Board.makeHexesSelectable(exhaustedPieces);
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
                Board.makeAllHexesNonSelectable();
            },

            _onInactivePlayerLeave() {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('selectable')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    await this._root.performServerAction("actTargetSelected", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r });
                }
            },

            getName: function () {
                return this.constructor.getName();
            }

        });

        MakeExhaustedPieceReady.getName = function () {
            return 'readyExhaustedPlayer';
        };

        return MakeExhaustedPieceReady;

    });