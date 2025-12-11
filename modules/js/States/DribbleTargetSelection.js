define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js"
],
    function (declare, BaseState, STHexLib, Board) {

        const DribbleTargetSelection = declare("st.DribbleTargetSelection", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                let adjacentOpponents = [];

                for (const space of args.adjacentOpponents) {
                    adjacentOpponents.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                Board.makeHexesSelectable(adjacentOpponents);
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

                    await this._root.performServerAction("actTargetSelected", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r });
                }
            },

            getName: function () {
                return this.constructor.getName();
            }
        });

        DribbleTargetSelection.getName = function () {
            return 'dribbleTargetSelection';
        };

        return DribbleTargetSelection;

    });