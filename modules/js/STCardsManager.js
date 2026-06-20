define(["dojo/_base/declare",
    getLibUrl('bga-animations', '1.x'),
    getLibUrl('bga-cards', '1.x'),
],
    function (declare, BgaAnimations, BgaCards) {

        const STCardsManager = declare("st.CardsManager", null, {
            constructor: function (root, cards) {
                this._root = root;

                this._initAnimationsManager();
                this._initCardsManager();
                this._initHiddenDeck();
                this._initDiscardStock();
                this._initHandStock();

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

            shuffleDeck: function () {
                let cards = this._discardStock.getCards()
                for(card of cards)
                {
                    card.location = `${this._root.player_id}_deck`;
                } 

                let promise =  this._hiddenDeck.addCards(this._discardStock.getCards()).then(() => this._hiddenDeck.shuffle());

                return promise;
            },

            //Private Methods
            _initAnimationsManager: function () {
                this._animationManager = new BgaAnimations.Manager({
                    animationsActive: () => this._root.bga.gameui.bgaAnimationsActive(),
                });
            },

            _initCardsManager: function () {
                const cardWidth = 200 * 0.7;
                const cardHeight = 280 * 0.7;

                this._cardsManager = new BgaCards.Manager({
                    animationManager: this._animationManager,
                    type: 'st-card', //css type
                    cardWidth: cardWidth,
                    cardHeight: cardHeight,
                    cardBorderRadius: '8px',
                    getId: (card) => card.id,
                    setupFrontDiv: (card, div) => {
                        const [backXPos, backYPos] = this._getCardPositionInsideAtlas(card);
                        div.style.backgroundPositionX = backXPos;
                        div.style.backgroundPositionY = backYPos;
                        this._root.addTooltipHtml(div.id, `tooltip of ${card.type}`);
                    },
                    setupBackDiv: (card, div) => {
                        div.style.backgroundPositionX = `calc(100%)`;
                        div.style.backgroundPositionY = `calc(100%)`;
                    },
                    isCardVisible: card => {return !!card.location && !card.flipped && !card.location.includes("_deck");},
                });
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
                    else if (TeamId === '1') {
                        rowMultiple = 1;
                        colMultiple = cardNum + 1;
                    }
                }
                else if (cardCategory === 'Captain') {
                    const teamId = tokens[1];
                    const cardNum = Number(tokens[2]);
                    if (teamId === '0') {
                        rowMultiple = 1;
                        colMultiple = 3 + cardNum;
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

                return [`calc(100% / 6 * ${colMultiple})`, `calc(100% / 2 * ${rowMultiple})`];
            },

            _initDiscardStock: function () {
                this._discardStock = new BgaCards.DiscardDeck(this._cardsManager, document.getElementById('myDiscard'), {
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

            //Private Fields
            _animationManager: null,

            _handStock: null,

            _discardStock: null,

            _hiddenDeck: null,

            _cardsManager: null,

            _root: null
        });

        return STCardsManager;
    });
