define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js"],
    function (declare, BaseState) {

        const MultiCardSelectState = declare("st.MultiCardSelectState", BaseState, {

            constructor: function (root, stateData) {
                this._cardManager = root.cardsManager;
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._cardManager.setHandCardsSelectability("multiple");
                this._cardManager.setHandCardClickHandler(this._onCardClick.bind(this));

                this._minCards = args.minCards;
                this._maxCards = args.maxCards;

                this._updateButtons();
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                this._cardManager.setHandCardsSelectability("none");
            },

            _onInactivePlayerLeave: function () {
            },

            _onCardClick: function (card) {
                this._updateButtons();
            },

            _updateButtons: function () {
                this._root.statusBar.removeActionButtons();

                this._root.statusBar.addActionButton(_('Confirm selection'), () => {
                    this._root.performServerAction("actCardsSelected", {
                        cardsSelectedIds : this._getSelectedCards()
                    })
                }, {id: 'confirmCardsSelection', color: 'primary', disabled: !this._areEnoughCardsSelected()});
            },

            _areEnoughCardsSelected: function ()
            {
                const selectedCards = this._getSelectedCards();

                return selectedCards.length >= this._minCards && selectedCards.length <= this._maxCards;
            },

            _getSelectedCards: function ()
            {
                return this._cardManager.getCardsSelectedIds();
            },

            _minCards: 0,
            _maxCards: 0,
            _cardManager: null,

            getName: function () {
                return this.constructor.getName();
            }
        });

        MultiCardSelectState.getName = function () {
            return 'multiCardSelection';
        };

        return MultiCardSelectState;

    });