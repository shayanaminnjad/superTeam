define(["dojo/_base/declare",
        getLibUrl('bga-cards', '1.x')
],
    function (declare, BgaCards) {

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
            setHandCardsSelectability: function (selectable) {
                this._handStock.setSelectionMode(selectable ? 'single' : 'none');
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

            placeAllToDeck: function() {
                let discardedCards = this._discardStock.getCards()
                for(card of discardedCards)
                {
                    card.location = `${this._root.player_id}_deck`;
                }

                let discardPromise =  this._hiddenDeck.addCards(this._discardStock.getCards());

                let cardsInhand = this._handStock.getCards();

                for(card of cardsInhand)
                {
                    card.location = `${this._root.player_id}_deck`;
                }

                let handPromise = this._hiddenDeck.addCards(this._handStock.getCards());

                return Promise.all([discardPromise, handPromise]);
            },

            shuffleDeck: function () {
                let cards = this._discardStock.getCards()
                for(card of cards)
                {
                    card.location = `${this._root.player_id}_deck`;
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
                        div.style.backgroundPositionY = `calc(100%)`;
                    },
                    isCardVisible: card => {return !!card.location && !card.location.includes("_deck");},
                });
            },

            _buildTooltipHtml: function (card, backXPos, backYPos) {
                const definition = (this._root.cardDefinitions || {})[card.type];
                const name = definition ? definition.name : card.type;
                const description = definition ? definition.toolTip : '';

                return `<div class="cardTooltip">
                    <div class="cardTooltipImage st-card-front" style="background-position: ${backXPos} ${backYPos};"></div>
                    <div class="cardTooltipText">
                        <div class="cardTooltipName">${name}</div>
                        <div class="cardTooltipDescription">${description}</div>
                    </div>
                </div>`;
            },

            _getCardPositionInsideAtlas: function (card) {
                //(sha) I need better row column sorting for the atlas
                //however it is not final yet, since I don't have all of
                //the cards, so lets make this work for now.

                rowMultiple = 0; //Basic
                colMultiple = 0;

                if (!card.type) //covers the case of dummy cards in shuffling. somehow they end up being here.
                {
                    return [`calc(100%)`, `calc(100%)`];
                }

                const tokens = card.type.split('_');
                const cardCategory = tokens[0];
                if (cardCategory === 'Basic') {
                    colMultiple = Number(tokens[1].charCodeAt(0) - 'A'.charCodeAt(0));
                }
                else if (cardCategory === 'Team') {
                    const teamId = tokens[1];
                    const cardNum = Number(tokens[2]);
                    if (teamId === '0') {
                        if (cardNum <= 1) {
                            rowMultiple = 0;
                            colMultiple = 5 + cardNum;
                        }
                        else {
                            rowMultiple = 1;
                            colMultiple = cardNum - 2;
                        }
                    }
                    else if (teamId === '1') {
                        rowMultiple = 1;
                        colMultiple = cardNum + 1;
                    }
                }
                else if (cardCategory === 'Captain') {
                    const teamId = tokens[1];
                    const cardNum = Number(tokens[2]);
                    if (teamId === '0') {
                        rowMultiple = 1;
                        colMultiple = 4 + cardNum;
                    }
                    else if (teamId === '2') {
                        rowMultiple = 2;
                        colMultiple = cardNum;
                    }
                }
                else if (cardCategory === 'Coach') {
                    const teamId = Number(tokens[1]);
                    rowMultiple = 2;
                    colMultiple = 3 + teamId;
                }
                else if (cardCategory === "Fatigue")
                {
                    rowMultiple = 2;
                    colMultiple = 5;
                }

                return [`calc(100% / 6 * ${colMultiple})`, `calc(100% / 2 * ${rowMultiple})`];
            },

            _initDiscardStock: function () {
                this._discardStock = new BgaCards.DiscardDeck(this._cardsManager, document.getElementById('myDiscard'), {
                    sort: BgaCards.sort('location_arg'),
                    autoPlace: card => card.location === `${this._root.player_id}_discard`,
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
                    autoPlace: card => card.location === 'hand' && parseInt(card.location_arg) === this._root.player_id,
                });
            },

            _initHiddenDeck: function () {
                this._hiddenDeck = new BgaCards.Deck(this._cardsManager, document.getElementById('hiddenDeck'), {
                    autoRemovePreviousCards: false,
                    counter: {
                        position: 'center',
                        extraClasses: 'text-shadow',
                    },
                    autoPlace: card => card.location === `${this._root.player_id}_deck`,
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

            //Private Fields
            _handStock: null,

            _discardStock: null,

            _hiddenDeck: null,

            _fatigueDeck: null,

            _opponentDiscardStock: null,

            _cardsManager: null,

            _root: null
        });

        return STCardsManager;
    });
