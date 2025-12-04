define(["dojo/_base/declare",
  g_gamethemeurl + "modules/js/States/BaseState.js",],
  function (declare, BaseState) {

    const PlayerTurnState = declare("st.PlayerTurn", BaseState, {

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

    PlayerTurnState.getName = function () {
      return 'playerTurn';
    };

    return PlayerTurnState;

  });