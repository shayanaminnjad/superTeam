define([], function () {

    return {
        //Public Functions
        construct: function (root, gamedatas) {
            this._root = root;
            this._init(gamedatas);
        },

        //------------------------------------------------------------------------------------

        setPlayerMorale: function (playerId, amount, animate = false) {
            let moraleEl = document.getElementById(`playerMorale${playerId}`);
            let currentAmount = parseInt(moraleEl.innerHTML, 10) || 0;

            if (!animate || currentAmount === amount) {
                moraleEl.innerHTML = amount;
                return dojo.animateProperty({ node: moraleEl, duration: 1 });
            }

            let stepDuration = 200; // Duration in ms for each step
            let totalSteps = Math.abs(amount - currentAmount);
            let stepDirection = amount > currentAmount ? 1 : -1;

            let countAnim = dojo.animateProperty({
                node: moraleEl,
                duration: stepDuration * totalSteps,
                onPlay: function () {
                    clearTimeout(moraleEl._moraleHoldTimeout);
                    moraleEl.classList.add("morale-changing");

                    let counter = currentAmount;
                    let interval = setInterval(function () {
                        counter += stepDirection;
                        moraleEl.innerHTML = counter;

                        if (counter === amount) {
                            clearInterval(interval);
                        }
                    }, stepDuration);
                },
                onEnd: function () {
                    moraleEl.innerHTML = amount;
                    moraleEl._moraleHoldTimeout = setTimeout(function () {
                        moraleEl.classList.remove("morale-changing");
                    }, 500);
                }
            });

            return countAnim;
        },

        //------------------------------------------------------------------------------------

        getPlayerMorale: function (playerId) {
            let moraleEl = document.getElementById(`playerMorale${playerId}`);
            return parseInt(moraleEl.innerHTML);
        },

        //------------------------------------------------------------------------------------

        increasePlayerMoraleAnimated: function (playerId, NewMorale, animStartEl) {
            return this._animateMoraleChange(playerId, NewMorale, animStartEl, true);
        },

        reducePlayerMoraleAnimated: function (playerId, amount, PlaceSpentOn) {
            let targetMorale = this.getPlayerMorale(playerId) - amount;
            return this._animateMoraleChange(playerId, targetMorale, PlaceSpentOn, false);
        },

        setPlayerCards: function (playerId, amount) {
            let cardsEl = document.getElementById(`playerCards${playerId}`);
            cardsEl.innerHTML = amount;
        },

        setAttacker: function (attackerId){
            for (const [playerId, element] of Object.entries(this._tacticalStateElMap)) {
                const isAttacker = parseInt(playerId) === attackerId;

                element.innerHTML = isAttacker ? _("ATTACKER") : _("DEFENDER");
                element.style.setProperty("--color", "#E5DFCB");
            }
        },

        //------------------------------------------------------------------------------------

        //Private Function
        _init: function (gamedatas) {
            for (const playerId of Object.keys(gamedatas.players)) {
                this._root.getPlayerPanelElement(playerId).insertAdjacentHTML('beforeend',
                    `<div id="${playerId}Resources" class ="resourcesParent"></div>`);

                let playerResources = document.getElementById(`${playerId}Resources`);

                let currentMorale = gamedatas.players[playerId].morale;

                playerResources.insertAdjacentHTML('beforeend', `
                    <div class = "resource">
                    <span id="playerMoraleIcon${playerId}" class = "moraleIcon resourceIcon"></span>
                    <span id="playerMorale${playerId}" class= "label">${currentMorale}</span></div>`);

                this.setPlayerMorale(playerId, currentMorale);

                let cards = gamedatas.players[playerId].cards;

                playerResources.insertAdjacentHTML('beforeend', `
                    <div class ="resource">
                    <span class = "cardIcon resourceIcon"></span>
                    <span id="playerCards${playerId}" class= "label">${cards}</span></div>`);

                const tacticalEl = document.createElement('div');
                tacticalEl.classList = 'readonlyText';
                tacticalEl.style.setProperty('--color', `#FFFFFF`);
                tacticalEl.style.setProperty('--size', '16px');

                playerResources.appendChild(tacticalEl);

                this._tacticalStateElMap[playerId] = tacticalEl;

                if (parseInt(playerId) === parseInt(gamedatas.first_starter_player)) {
                    playerResources.insertAdjacentHTML('beforeend', `
                        <div class="resource starterResource">
                        <span id="starterBadge${playerId}" class="starterBadge">${_("1st")}</span></div>`);

                    this._root.addTooltipHtml(`starterBadge${playerId}`, _("This player started the game."));
                }
            }

            this.setAttacker(parseInt(gamedatas.attacker));

            this.setPlayerCards(this._root.opponentId, gamedatas.other_deck_count);
            this.setPlayerCards(this._root.viewerId, gamedatas.player_deck_count);
        },

        _animateMoraleChange: function (playerId, targetMorale, targetEl, isIncrease = true) {
            let currentMorale = this.getPlayerMorale(playerId);
            let amount = Math.abs(targetMorale - currentMorale);

            if (amount === 0) {
                return this.setPlayerMorale(playerId, targetMorale, true);
            }

            let animations = [];
            const delayMs = 150;

            for (let i = 0; i < amount; i++) {
                let tempEl = document.createElement("div");
                tempEl.innerHTML = `<div class="floatingMorale"></div>`;
                let moraleEl = tempEl.firstElementChild;
                this._root.getGameAreaElement().appendChild(moraleEl);

                let playerMoraleEl = document.getElementById(`playerMoraleIcon${playerId}`);

                let startEl = isIncrease ? targetEl : playerMoraleEl;
                let endEl = isIncrease ? playerMoraleEl : targetEl;

                this._root.placeOnObject(moraleEl, startEl);

                let slideAnim = this._root.slideToObject(moraleEl, endEl);

                if (i > 0) {
                    let delayAnim = dojo.animateProperty({
                        node: moraleEl,
                        duration: i * delayMs
                    });
                    animations.push(dojo.fx.chain([delayAnim, slideAnim]));
                } else {
                    animations.push(slideAnim);
                }
            }

            let floatingAnimation = dojo.fx.combine(animations);

            dojo.connect(floatingAnimation, "onEnd", this, function () {
                document.querySelectorAll(".floatingMorale").forEach(el => el.remove());
            });

            let counterAnimation = this.setPlayerMorale(playerId, targetMorale, true);

            return isIncrease
                ? dojo.fx.chain([floatingAnimation, counterAnimation])
                : dojo.fx.chain([counterAnimation, floatingAnimation]);
        },

        //Private Members
        _root: null,
        _tacticalStateElMap: []
    };
});