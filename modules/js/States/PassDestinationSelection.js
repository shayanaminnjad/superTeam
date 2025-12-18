define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js"],
    function (declare, BaseState, STHexLib, Board) {

        const PassDestinationSelection = declare("st.PassDestinationSelection", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                let allBlockedSpaces = [];

                for (const space of args.blockedSpaces) {
                    allBlockedSpaces.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                }

                this.hexWithBall = STHexLib.createOffsetCoord(args.hexWithBall.hex_row, args.hexWithBall.hex_column)
                allBlockedSpaces.push(Board.convertOffsetCoordToHexId(this.hexWithBall));

                Board.makeAllHexesSelectableExcept(allBlockedSpaces);
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                Board.makeAllHexesNonSelectable();
                Board.removeRequiredPowerVisualization();
            },

            _onInactivePlayerLeave: function () {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('selectable')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    await this._root.performServerAction("actDestSelected", { hexColumn: offsetCoord.c, hexRow: offsetCoord.r });
                }
            },

            _selfOnHexEnter: async function (evt) {
                if (evt.currentTarget.classList.contains('selectable')) {
                    Board.visualizePowerRequired(Board.getHexWithBall(), Board.convertHexIdToOffsetCoord(evt.currentTarget.id));
                }
            },

            _selfOnHexLeave: async function(evt) {
                Board.removeRequiredPowerVisualization();
            },

            getName: function () {
                return this.constructor.getName();
            }
        });

        PassDestinationSelection.getName = function () {
            return 'passDestinationSelection';
        };

        return PassDestinationSelection;

    });