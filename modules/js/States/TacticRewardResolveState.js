define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js",],
    function (declare, BaseState) {
        const TacticRewardResolveState = declare("st.TacticRewardResolveState", BaseState, {

            constructor: function (root, stateData) {
            },

            _onActivePlayerEnter(args) {
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
            },

            _onInactivePlayerLeave() {
            },

            getName: function () {
                return this.constructor.getName();
            }

        });

        TacticRewardResolveState.getName = function () {
            return 'tacticRewardResolveState';
        };

        return TacticRewardResolveState;

    });