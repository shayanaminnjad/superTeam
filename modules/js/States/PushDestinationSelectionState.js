define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js",
        g_gamethemeurl + "modules/js/STHexLib.js",
        g_gamethemeurl + "modules/js/BoardPresentation.js"
    ],
    function (declare, BaseState, STHexLib, Board) {

        const PushDestinationSelectionState = declare("st.PushDestinationSelectionState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                let pushHexes = [];

                for (const space of args.pushHexes) {
                    pushHexes.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                Board.makeHexesSelectable(pushHexes);
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

        PushDestinationSelectionState.getName = function () {
            return 'pushDestinationSelection';
        };

        return PushDestinationSelectionState;

    });