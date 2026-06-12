define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",],
    function (declare, BaseState) {

        const CardSelection = declare("st.CardSelection", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._cardManager = this._root.cardsManager;

                this._cardManager.setHandCardsSelectability(true);
                this._cardManager.setHandCardClickHandler(this._onCardClick.bind(this));

                this._updateButtons();
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                this._cardManager.setHandCardsSelectability(false);
                this._cardManager.setHandCardClickHandler(null);
            },

            _onInactivePlayerLeave: function () {
            },

            getName: function () {
                return this.constructor.getName();
            },
            //Notifications

            notif_cardPlayed: function (notifData) {
                this._cardManager.placeCards(Object.values(notifData.discards));
            },

            //Private Methods
            _onCardClick: function (card) {
                const isSelected = this._cardManager.isSelected(card);

                this._selectedCard = isSelected ? card : null;

                this._updateButtons();
            },

            _updateButtons: function () {
                this._root.statusBar.removeActionButtons();

                if (this._args.canPlayCard) {
                    this._root.statusBar.addActionButton('Play as basic', () => {
                        this._root.performServerAction("actCardSelected", { cardId: this._selectedCard.id, cardType: this._selectedCard.type, asBasic: true })
                    }, { id: 'cardAsBasicButton', color: 'primary', disabled: this._selectedCard === null });

                    this._root.statusBar.addActionButton('Perform tactic', () => {
                        this._root.performServerAction("actCardSelected", { cardId: this._selectedCard.id, cardType: this._selectedCard.type, asBasic: false })
                    }, { id: 'performTacticButton', color: 'primary', disabled: this._selectedCard === null });
                }

                if (this._args.canPass) {
                    this._root.statusBar.addActionButton('pass', () => {
                        this._root.performServerAction("actPass")
                    }, { color: 'alert' });
                }
            },

            //Private Fields
            _cardManager: null,
            _selectedCard: null
        });

        CardSelection.getName = function () {
            return 'cardSelection';
        };

        return CardSelection;

    });