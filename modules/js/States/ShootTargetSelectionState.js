define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/STHexLib.js"],
    function (declare, BaseState, Board, HexLib) {

        const ShootTargetSelectionState = declare("st.ShootTargetSelectionState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                let isAttacker = parseInt(args.attacker) === this._root.player_id;

                if (isAttacker) {
                    this._root.addConfirmationStepToActionButtons();
                    this.subscribeEvent('serverActionRequested', this.onAttackerSelectedTarget.bind(this));
                    this.subscribeEvent('requestedActionCanceled', this.onAttackerCanceledTarget.bind(this));
                }
            },

            _onInactivePlayerEnter: function (args) {
                //This is a multiActive state, there are not inActive
            },

            _onActivePlayerLeave: function () {
                if (this._args.isAttacker) {
                    this._root.removeConfirmationStepFromActionButton();
                }
            },

            _onInactivePlayerLeave: function () {
                //This is a multiActive state, there are not inActive
            },

            getName: function () {
                return this.constructor.getName();
            },
            //Notifications

            //Private Methods
            onAttackerSelectedTarget: function (data) {
                //TODO: this must change based on whether goalie is on left or right. 
                let goalieColumn = 0;

                if (this._root.opponentId === this._root.userInRightId)
                    goalieColumn = 16;

                let targetHex = null;

                if (data === 'left') {
                    targetHex = HexLib.createOffsetCoord(4, goalieColumn)
                }
                else if (data === 'middle') {
                    targetHex = HexLib.createOffsetCoord(5, goalieColumn)
                }
                else {
                    targetHex = HexLib.createOffsetCoord(6, goalieColumn)
                }

                Board.visualizePowerRequired(Board.getHexWithBall(), targetHex);
            },

            onAttackerCanceledTarget: function (data) {
                Board.removeRequiredPowerVisualization();
            }
        });

        ShootTargetSelectionState.getName = function () {
            return 'shootTargetSelection';
        };

        return ShootTargetSelectionState;

    });