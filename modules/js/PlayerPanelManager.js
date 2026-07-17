define([], function () {

    return {
        //Public Functions
        construct: function (root, gamedatas) {
            this._root = root;

            this._init(gamedatas);
        },

        //------------------------------------------------------------------------------------

        setPlayerMorale: function (playerId, amount) {
            let moraleEl = document.getElementById(`playerMorale${playerId}`);
            moraleEl.innerHTML = amount;
        },

        //------------------------------------------------------------------------------------

        getPlayerMorale: function (playerId) {
            let moraleEl = document.getElementById(`playerMorale${playerId}`);
            return parseInt(moraleEl.innerHTML);
        },

        //------------------------------------------------------------------------------------

        reducePlayerMoraleAnimated: function (amount, PlaceSpentOn) {
            let activePlayer = this._root.getActivePlayerId();
            let currentMorale = this.getPlayerMorale(activePlayer);
            this.setPlayerMorale(activePlayer, currentMorale - amount);

            let animations = [];

            for (let i = 0; i < amount; i++) {
                let tempEl = document.createElement("div");
                tempEl.innerHTML = `<div class="spentMorale"></div>`;
                let moraleEl = tempEl.firstElementChild;
                this._root.getGameAreaElement().appendChild(moraleEl);
                let playerMoraleEl = document.getElementById(`playerMorale${activePlayer}`);

                this._root.placeOnObject(moraleEl, playerMoraleEl);

                animations.push(this._root.slideToObject(moraleEl, PlaceSpentOn));
            }

            let animation = dojo.fx.combine(animations);

            dojo.connect(animation, "onEnd", this, function() {
                document.querySelectorAll(".spentMorale").forEach(el => el.remove());
            });

            return animation;
        },

        setPlayerScoredGoals: function (playerId, amount) {
            let scoredGoalsEl = document.getElementById(`playerGoals${playerId}`);
            scoredGoalsEl.innerHTML = amount;
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
                    <span class = "moraleIcon resourceIcon"></span>
                    <span id="playerMorale${playerId}" class= "label">${currentMorale}</span></div>`);

                this.setPlayerMorale(playerId, currentMorale);

                let scoredGoals = gamedatas.players[playerId].goals;

                playerResources.insertAdjacentHTML('beforeend', `
                    <div class ="resource">
                    <span class = "goalsIcon resourceIcon"></span>
                    <span id="playerGoals${playerId}" class= "label">${scoredGoals}</span></div>`);

                this.setPlayerScoredGoals(playerId, scoredGoals);
            }
        },

        //Private Members
        _root: null,
    };
});