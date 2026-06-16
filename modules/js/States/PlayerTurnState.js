define(["dojo/_base/declare",
  g_gamethemeurl + "modules/js/States/BaseState.js",],
  function (declare, BaseState) {
//TODO: this is basic turn essentially I must change the namme.
    const PlayerTurnState = declare("st.PlayerTurn", BaseState, {

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

    PlayerTurnState.getName = function () {
      return 'playerTurn';
    };

    return PlayerTurnState;

  });