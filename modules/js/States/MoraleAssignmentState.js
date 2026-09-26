define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/STUIHelpers.js",
    g_gamethemeurl + "modules/js/GameEventProcessor.js"
    ],
    function (declare, BaseState, STHexLib, Board, STUIHelpers, GameEventProcessor) {

        const MoraleAssignmentState = declare("st.MoraleAssignmentState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                STUIHelpers.createCounter('generalactions', 'moraleAssign', 0, args.maxMoraleAssignable);

                GameEventProcessor.ShowAssignedMorale(args.moraleAssigned);

                let selectedHex = STHexLib.createOffsetCoord(
                    args.selectedDestination.hex_row,
                    args.selectedDestination.hex_column
                );

                if (parseInt(args.requiredPower) > 0) {
                    Board.visualizePowerRequired(
                        Board.getHexWithBall(),
                        selectedHex,
                        parseInt(args.attacker) !== this._root.player_id,
                        !args.isShooting
                    );
                }
                else {
                    Board.highlightHex(
                        Board.convertOffsetCoordToHexId(selectedHex)
                    );
                }
            },

            _onInactivePlayerEnter: function (args) {
                GameEventProcessor.ShowAssignedMorale(args.moraleAssigned);

                if (args.isShooting && parseInt(args.requiredPower) > 0) {
                    let selectedHex = STHexLib.createOffsetCoord(
                        args.selectedDestination.hex_row,
                        args.selectedDestination.hex_column
                    );

                    Board.visualizePowerRequired(
                        Board.getHexWithBall(),
                        selectedHex,
                        parseInt(args.attacker) !== this._root.player_id,
                        false
                    );
                }
            },

            _onActivePlayerLeave: function () {
                STUIHelpers.removeCounter('moraleAssign');
                Board.removeRequiredPowerVisualization();
                Board.removeHexHighlights();
            },

            _onInactivePlayerLeave: function () {
                Board.removeRequiredPowerVisualization();
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_moraleAssigned: async function (notifData) {
                return this._root.bgaPlayDojoAnimation(GameEventProcessor.ShowAssignedMoraleAnimated(notifData));
            }

        });

        MoraleAssignmentState.getName = function () {
            return 'moraleAssignment';
        };

        return MoraleAssignmentState;

    });