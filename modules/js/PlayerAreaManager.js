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

        reducePlayerMorale: async function (amount, PlaceSpentOn) {
            let activePlayer = this._root.getActivePlayerId();
            let currentMorale = this.getPlayerMorale(activePlayer);
            this.setPlayerMorale(activePlayer, currentMorale - amount);

            for (let i = 0; i < amount; i++) {
                let tempEl = document.createElement("div");
                tempEl.innerHTML = `<div class="spentMorale"></div>`;
                let moraleEl = tempEl.firstElementChild;
                this._root.getGameAreaElement().appendChild(moraleEl);
                let playerMoraleEl = document.getElementById(`playerMorale${activePlayer}`);

                this._root.placeOnObject(moraleEl, playerMoraleEl);

                const anim = this._root.slideToObject(moraleEl, PlaceSpentOn);

                await this._root.bgaPlayDojoAnimation(anim);
            }

            document.querySelectorAll(".spentMorale").forEach(el => el.remove());
        },

        //------------------------------------------------------------------------------------

        //Private Function
        _init: function (gamedatas) {
            console.log('init game datas' + gamedatas);
            for (const playerId of Object.keys(gamedatas.players)) {
                let currentMorale = gamedatas.players[playerId].morale;

                this._root.getPlayerPanelElement(playerId).insertAdjacentHTML('beforeend', `
                    <div class ="resources">
                    <span class = "moraleIcon"></span>
                    <span id="playerMorale${playerId}" class= "moraleLabel">${currentMorale}</span>
                    </div>`);

                this.setPlayerMorale(playerId, currentMorale);
            }
        },

        //Private Members
        _root: null,
    };
});