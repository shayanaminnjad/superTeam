define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/STUIHelpers.js"],
    function (declare, BaseState, STHexLib, Board, STUIHelpers) {

        const MoraleAssignmentState = declare("st.MoraleAssignmentState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                //args.requiredPower
                STUIHelpers.createCounter('generalactions', 'moraleAssign', 0, args.maxMoraleAssignable);
                let selectedHex = STHexLib.createOffsetCoord(args.selectedDestination.selected_hex_row, args.selectedDestination.selected_hex_column);
                this._root.visualizePowerRequired(Board.getHexWithBall(), selectedHex);
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                STUIHelpers.removeCounter('moraleAssign');
                Board.removeRequiredPowerVisualization();
            },

            _onInactivePlayerLeave: function () {
            },

            getName: function () {
                return this.constructor.getName();
            }
            //Notifications

        });

        MoraleAssignmentState.getName = function () {
            return 'moraleAssignment';
        };

        return MoraleAssignmentState;

    });