define(["dojo/_base/declare",
  g_gamethemeurl + "modules/js/States/BaseState.js",],
  function (declare, BaseState) {
    const PlayerBasicTurnState = declare("st.PlayerBasicTurn", BaseState, {

      constructor: function (root, stateData) {
      },

      _onActivePlayerEnter(args) {
        this._root.handStock.addCards(Object.values(args._private.playerHand));

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

    PlayerBasicTurnState.getName = function () {
      return 'playerBasicTurn';
    };

    return PlayerBasicTurnState;

  });