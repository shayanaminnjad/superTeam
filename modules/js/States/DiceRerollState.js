define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js",
        g_gamethemeurl + "modules/js/DiceUI.js",
    ],
    function (declare, BaseState, DiceUI) {

        const DiceRerollState = declare("st.DiceRerollState", BaseState, {

            constructor: function (root, stateData) {
            },

            // State Overrides
            _onActivePlayerEnter: function (args) {
                return this._showDice(args);
            },

            _onInactivePlayerEnter: function (args) {
                return this._showDice(args);
            },

            _onActivePlayerLeave: function () {
                this._hideDice();
                DiceUI.clearRollVisuals();
            },

            _onInactivePlayerLeave: function () {
                this._hideDice();
                DiceUI.clearRollVisuals();
            },

            notif_reroll: async function (notifData) {
                const p = this._root.buildDiceParams(notifData);
                await DiceUI.rerollWhiteDice(p.rollResults);
                DiceUI.clearRollVisuals();
            },


            _showDice: function (args) {
                if (DiceUI.isShowing()) {
                    return;
                }

                return DiceUI.showRoll(this._root.buildDiceParams(args), true);
            },

            _hideDice: function () {
                DiceUI.dismiss();
            },

            getName: function () {
                return this.constructor.getName();
            }
        });

        DiceRerollState.getName = function () {
            return 'diceReRollState';
        };

        return DiceRerollState;
    });