define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/CoachBoard.js",
],
    function (declare, CoachBoard) {
        const PlayerArea = declare("st.PlayerArea", null, {
            constructor: function (ownerId, ownerData, root) {
                this._ownerId = ownerId;
                this._ownerData = ownerData;
                this._root = root;

                this._init();
            },

            getElement() {
                return this._areaEl;
            },

            getCoachBoard() {
                return this._coachBoard;
            },

            _init: function () {
                this._areaEl = document.createElement('div');
                this._areaEl.id = `${this._ownerId}_PlayerArea`;
                this._areaEl.className = 'playerArea';
                this._root.getGameAreaElement().appendChild(this._areaEl);

                const playerName = document.createElement('div');
                playerName.innerHTML = `${this._ownerData.name}`;
                playerName.classList = 'readonlyText';
                playerName.style.setProperty('--color', `#${this._ownerData.color}`);
                playerName.style.setProperty('--size', 'large');
                this._areaEl.appendChild(playerName);

                //---------------

                const playerStuffContainer = document.createElement('div');
                playerStuffContainer.style.display = 'flex';
                playerStuffContainer.style.flexDirection = 'row';
                playerStuffContainer.style.flexWrap = 'wrap'; 
                playerStuffContainer.style.gap = '20px';
                playerStuffContainer.style.paddingLeft = '10px';
                this._areaEl.appendChild(playerStuffContainer);

                this._coachBoard = new CoachBoard(this._ownerId, this._ownerData, this._root, this, playerStuffContainer);

                if (this._root.player_id === this._ownerId) {
                    const playerHiddenDeck = document.createElement('div');
                    playerHiddenDeck.id = 'hiddenDeck';
                    playerStuffContainer.appendChild(playerHiddenDeck);
                    
                    const playerDiscard = document.createElement('div');
                    playerDiscard.id = 'myDiscard';
                    playerStuffContainer.appendChild(playerDiscard);

                    const playerCardsWrapper = document.createElement('div');
                    playerCardsWrapper.id = 'cardsWrapper';
                    this._areaEl.appendChild(playerCardsWrapper);

                    const hideShowToggle = document.createElement('button');
                    hideShowToggle.id = 'cardsHideShowToggle';
                    hideShowToggle.setAttribute('aria-label', 'Hide hand');
                    this._areaEl.appendChild(hideShowToggle);

                    hideShowToggle.addEventListener('click', e => {
                        const nowLow = playerCardsWrapper.classList.toggle('low');
                        hideShowToggle.classList.toggle('low', nowLow);
                        hideShowToggle.setAttribute('aria-label', nowLow ? 'Show hand' : 'Hide hand');
                    });

                    const playerHand = document.createElement('div');
                    playerHand.id = 'myHand';
                    playerCardsWrapper.appendChild(playerHand);
                }
                else
                {
                    const opponentDiscard = document.createElement('div');
                    opponentDiscard.id = 'opponentDiscard';
                    playerStuffContainer.appendChild(opponentDiscard);
                }
            },

            _ownerId: 0,
            _ownerData: null,
            _root: null,
            _areaEl: null,
            _coachBoard: null
        });

        return PlayerArea;
    });