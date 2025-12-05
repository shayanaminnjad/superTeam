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

                this._makeAllHexesSelectableExcept(allBlockedSpaces);
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                document.querySelectorAll('.selectable').forEach(div => div.classList.remove('selectable'));
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

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications

            //Private Methods
            _makeAllHexesSelectableExcept: function (hexesToExclude) {
                document.querySelectorAll('.hex').forEach(el => el.classList.add('selectable'));

                for (let i = 0; i < hexesToExclude.length; i++) {
                    document.getElementById(hexesToExclude[i]).classList.remove('selectable');
                }
            },

        });

        PassDestinationSelection.getName = function () {
            return 'passDestinationSelection';
        };

        return PassDestinationSelection;

    });