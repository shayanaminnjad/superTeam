define(["dojo/_base/declare",
  g_gamethemeurl + "modules/js/States/BaseState.js",],
function(declare, BaseState) {

    const CardSelection = declare("st.CardSelection", BaseState, {

        constructor: function(root, stateData) {
        },

        //State Overrides
        _onActivePlayerEnter: function (args) {
            this._root.cardsManager.setHandCardsSelectability(true);
            this._root.cardsManager.setHandCardClickHandler(this._onCardClick.bind(this));
        },

        _onInactivePlayerEnter: function (args) {
        },

        _onActivePlayerLeave: function () {
            this._root.cardsManager.setHandCardsSelectability(false);
            this._root.cardsManager.setHandCardClickHandler(null);
        },

        _onInactivePlayerLeave: function () {
        },

        getName: function() {
            return this.constructor.getName();
        },
        //Notifications

        //Private Methods
        _onCardClick: function(card)
        {
            console.log(card + ' clicked');
        }
    });

    CardSelection.getName = function(){
       return 'cardSelection';
     };

    return CardSelection;

});