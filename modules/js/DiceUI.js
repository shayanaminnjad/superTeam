define([
    g_gamethemeurl + "modules/js/BoardPresentation.js",
], function (Board) {

    return {
        construct: function(root)
        {
            this._root = root;
        },

        showRollAndWait: async function (greenDie, whiteDice, moraleSpent, requiredPower) {

            totalPower = greenDie + moraleSpent;

            Board.getMainBoardElement().insertAdjacentHTML('beforeend', `<div id='diceRollArea'></div>`);

            let diceRollAreaEl = document.getElementById('diceRollArea');

            diceRollAreaEl.insertAdjacentHTML('beforeend', `<span class='greenDie'>${greenDie}</span>`);

            /*
            for (const whiteDie of whiteDice) {
                diceRollAreaEl.insertAdjacentHTML('beforeend', `<span class='whiteDie'>${whiteDie}</span>`);
                totalPower += whiteDie;
            }*/

            diceRollAreaEl.insertAdjacentHTML('beforeend', `<span id='moraleColumn'></span>`);
            let moraleColumnEl = document.getElementById('moraleColumn');

            for (let i = 0; i < moraleSpent; ++i)
                moraleColumnEl.insertAdjacentHTML('beforeend', `<span class='moraleSpent'></span>`);

            if (totalPower >= requiredPower) {
                diceRollAreaEl.insertAdjacentHTML('beforeend', `<span style='font-size: xx-large;'> >= ${requiredPower}</span>`);
            }
            else {
                diceRollAreaEl.insertAdjacentHTML('beforeend', `<span style='font-size: xx-large;'> < ${requiredPower}</span>`);
            }

            const TIMEOUT = 3000;
            setTimeout(() => {
                diceRollAreaEl.remove();
            }, TIMEOUT);

            return this._root.wait(TIMEOUT);
        },

        //Private Members
        _root: null,
    }
});