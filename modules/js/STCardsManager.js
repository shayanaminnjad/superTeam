define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/STUIHelpers.js",
        getLibUrl('bga-cards', '1.x')
],
    function (declare, STUIHelpers, BgaCards) {

        const STCardsManager = declare("st.CardsManager", null, {
            constructor: function (root, cards) {
                this._root = root;

                this._initCardsManager();
                this._initHiddenDeck();
                this._initDiscardStock();
                this._initHandStock();
                this._initFatigueCards();
                this._initOpponentDiscard();

                this._cardsManager.placeCards(cards);
            },

            //Public Methods
            setHandCardsSelectability: function (mode) {
                this._handStock.setSelectionMode(mode);
            },

            setHandCardClickHandler: function (handler) {
                this._handStock.onCardClick = handler;
            },

            setSelectionHandler: function (handler) {
                this._handStock.onSelectionChange = handler;
            },

            isSelected: function (card) {
                return this._handStock.isSelected(card);
            },

            placeCards: function (cards) {
                return this._cardsManager.placeCards(cards);
            },

            removeCard: function (card) {
                return this._cardsManager.removeCard(card);
            },

            removeOpponentDiscard: function () {
                this._opponentDiscardStock.removeAll();
            },

            removeMyDiscard: function () {
                this._discardStock.removeAll();
            },

            placeAllToDeck: function() {
                let discardedCards = this._discardStock.getCards()
                for(card of discardedCards)
                {
                    card.location = `${this._root.viewerId}_deck`;
                }

                let discardPromise =  this._hiddenDeck.addCards(this._discardStock.getCards());

                let cardsInhand = this._handStock.getCards();

                for(card of cardsInhand)
                {
                    card.location = `${this._root.viewerId}_deck`;
                }

                let handPromise = this._hiddenDeck.addCards(this._handStock.getCards());

                return Promise.all([discardPromise, handPromise]);
            },

            shuffleDeck: function () {
                let cards = this._discardStock.getCards()
                for(card of cards)
                {
                    card.location = `${this._root.viewerId}_deck`;
                } 

                let promise =  this._hiddenDeck.addCards(this._discardStock.getCards()).then(() => this._hiddenDeck.shuffle());

                return promise;
            },

            setHandVisibility: function(isVisible) {
                let CardWrapperEl = document.getElementById("cardsWrapper");
                let ShowHideToggleEl = document.getElementById("cardsHideShowToggle");

                CardWrapperEl.style.visibility = isVisible ? "visible" : "hidden";
                ShowHideToggleEl.style.visibility = isVisible ? "visible" : "hidden";
            },

            getCardsInHand: function()
            {
                let cardsInhand = this._handStock.getCards();
                return cardsInhand.length;
            },

            getCardsSelectedIds: function(){
                let res = [];
                const selection = this._handStock.getSelection();

                for(let card of selection)
                    res.push(card.id);

                return res;
            },

            getCardLogHtml: function (cardType) {
                return STCardsManager.getCardLogHtml(cardType, this._root.cardDefinitions);
            },

            // [x, y] background position of a card type inside the cards atlas
            getCardAtlasPosition: function (cardType) {
                return this._getCardPositionInsideAtlas({ type: cardType });
            },

            getCardTooltipHtml: function (cardType) {
                const [backXPos, backYPos] = this._getCardPositionInsideAtlas({ type: cardType });
                return this._buildTooltipHtml({ type: cardType }, backXPos, backYPos);
            },

            //Private Methods
            _initCardsManager: function () {
                const cardWidth = 200 * 0.7;
                const cardHeight = 280 * 0.7;

                this._cardsManager = new BgaCards.Manager({
                    animationManager: this._root.animationManager,
                    type: 'st-card', //css type
                    cardWidth: cardWidth,
                    cardHeight: cardHeight,
                    cardBorderRadius: '8px',
                    getId: (card) => card.id,
                    setupFrontDiv: (card, div) => {
                        const [backXPos, backYPos] = this._getCardPositionInsideAtlas(card);
                        div.style.backgroundPositionX = backXPos;
                        div.style.backgroundPositionY = backYPos;
                        this._root.addTooltipHtml(div.id, this._buildTooltipHtml(card, backXPos, backYPos));
                    },
                    setupBackDiv: (card, div) => {
                        div.style.backgroundPositionX = `calc(100%)`;
                        div.style.backgroundPositionY = `calc(0%)`;
                    },
                    isCardVisible: card => {return !!card.location && !card.location.includes("_deck");},
                });
            },

            _buildTooltipHtml: function (card, backXPos, backYPos) {
                const definition = (this._root.cardDefinitions || {})[card.type];
                const name = definition ? _(definition.name) : card.type;
                const description = definition && definition.toolTip ? _(definition.toolTip) : '';

                return STUIHelpers.buildTooltipHtml('st-card-front', `${backXPos} ${backYPos}`, name, description);
            },

            _getCardPositionInsideAtlas: function (card) {

                if (!card.type) //covers the case of dummy cards in shuffling. somehow they end up being here.
                {
                    return [`calc(100%)`, `calc(0%)`];
                }

                let rowMultiple = 0; //Basic
                let colMultiple = 0;

                const tokens = card.type.split('_');
                const cardCategory = tokens[0];
                if (cardCategory === 'Basic') {
                    colMultiple = Number(tokens[1].charCodeAt(0) - 'A'.charCodeAt(0));
                }
                else if (cardCategory === 'Team') {
                    const AdvancedCardsPerTeam = 3;
                    const teamId = Number(tokens[1]);
                    const cardNum = Number(tokens[2]);
                    rowMultiple = Math.floor(teamId / 2) + 1;
                    colMultiple = teamId % 2 === 0 ? cardNum : cardNum + AdvancedCardsPerTeam;
                }
                else if (cardCategory === 'Captain') {
                    const cptPerTeam = 3;
                    const captainId = tokens[1];
                    let cptInOrder = this._captainIdToNumericOrder(captainId)
                    const cardNum = Number(tokens[2]);

                    rowMultiple = Math.floor(cptInOrder / 2) + 4;
                    colMultiple = cptInOrder % 2 === 0 ? cardNum : cptPerTeam + cardNum;
                }
                else if (cardCategory === 'Coach') {
                    const teamId = Number(tokens[1]);
                    rowMultiple = 1 + teamId;
                    colMultiple = 6 ;
                }
                else if (cardCategory === "Fatigue")
                {
                    rowMultiple = 0;
                    colMultiple = 5;
                }

                return [`calc(100% / 6 * ${colMultiple})`, `calc(100% / 6 * ${rowMultiple})`];
            },

            _initDiscardStock: function () {
                this._discardStock = new BgaCards.DiscardDeck(this._cardsManager, document.getElementById('myDiscard'), {
                    sort: BgaCards.sort('location_arg'),
                    autoPlace: card => card.location === `${this._root.viewerId}_discard`,
                    counter: {
                        position: 'center',
                        extraClasses: 'text-shadow'
                    },
                });
            },

            _initHandStock: function () {
                this._handStock = new BgaCards.HandStock(this._cardsManager, document.getElementById('myHand'), {
                    sort: BgaCards.sort('type', 'type_arg'),
                    cardOverlap: 5,
                    fanShaped: true,
                    floatZIndex: 110,
                    selectableCardStyle: {
                        outlineSize: 2,
                        outlineColor: 'green',
                    },
                    autoPlace: card => card.location === 'hand' && parseInt(card.location_arg) === this._root.viewerId,
                });
            },

            _initHiddenDeck: function () {
                this._hiddenDeck = new BgaCards.Deck(this._cardsManager, document.getElementById('hiddenDeck'), {
                    autoRemovePreviousCards: false,
                    counter: {
                        position: 'center',
                        extraClasses: 'text-shadow',
                    },
                    autoPlace: card => card.location === `${this._root.viewerId}_deck`,
                    autoUpdateCardNumber: true
                });
            },

            _initFatigueCards: function() {
              this._fatigueDeck = new BgaCards.DiscardDeck(this._cardsManager, document.getElementById('supply'), {
                  maxHorizontalShift: 0,
                  maxRotation: 0,
                  maxVerticalShift: 0,
                  autoPlace: card => card.location === 'supply',
              });
            },

            _initOpponentDiscard: function() {
                this._opponentDiscardStock = new BgaCards.DiscardDeck(this._cardsManager, document.getElementById('opponentDiscard'), {
                    sort: BgaCards.sort('location_arg'),
                    autoPlace: card => card.location === `${this._root.opponentId}_discard`,
                });
            },

            _captainIdToNumericOrder : function(teamId) {
                const TEAM_ORDER = {
                    '1': 0,
                    '3': 1,
                    '4': 2,
                    '6': 3,
                    '14': 4,
                    '21': 5,
                };

                return TEAM_ORDER[teamId];
            },

            //Private Fields
            _handStock: null,

            _discardStock: null,

            _hiddenDeck: null,

            _fatigueDeck: null,

            _opponentDiscardStock: null,

            _cardsManager: null,

            _root: null
        });

        //Static so history logs can be formatted on refresh, before any manager instance or card definitions exist.
        STCardsManager.getCardLogHtml = function (cardType, cardDefinitions) {
            const [backXPos, backYPos] = STCardsManager.prototype._getCardPositionInsideAtlas({ type: cardType });
            const definition = (cardDefinitions || {})[cardType];
            const name = definition ? definition.name : cardType;

            return `<span class="cardLogItem" data-card-type="${cardType}">
                <span class="cardLogImage st-card-front" style="background-position: ${backXPos} ${backYPos};"></span>
                <span class="cardLogName">${name}</span>
            </span>`;
        };

        return STCardsManager;
    });
