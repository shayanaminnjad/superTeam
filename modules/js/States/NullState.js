define(["dojo/_base/declare",
  g_gamethemeurl + "modules/js/States/BaseState.js",],
function(declare, BaseState) {

    const NullState = declare("st.NullState", BaseState, {

        constructor: function(root, stateData) {
        },

        //State Overrides
        _onActivePlayerEnter: function (args) {
        },

        _onInactivePlayerEnter: function (args) {
        },

        _onActivePlayerLeave: function () {
        },

        _onInactivePlayerLeave: function () {
        },

        getName: function() {
            return this.constructor.getName();
        }
        //Notifications

    });

    NullState.getName = function(){
       return 'nullState';
     };

    return NullState;

});