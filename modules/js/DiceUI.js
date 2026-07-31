define([
    g_gamethemeurl + "modules/js/BoardPresentation.js",
], function (Board) {

    return {
        construct: function (root) {
            this._root = root;
        },

        showRollAndWait: async function (rollResults, requiredPower, blockingPieces, distance, skipEnabled) {

            //TODO: always show current player at top!
            Board.getMainBoardElement().insertAdjacentHTML('beforeend', `<div id='diceRollArea'></div>`);
            let diceRollAreaEl = document.getElementById('diceRollArea');

            for (let i = 0; i < rollResults.length; ++i) {
                const result = rollResults[i];

                totalPower = result.greenDie + result.assignedMorale;

                let playerRollLine = this._createRollLineElement(diceRollAreaEl, i, result);
                playerRollLine.insertAdjacentHTML('beforeend', `<span class='greenDie'>${result.greenDie}</span>`);

                for (const whiteDie of result.whiteDice) {
                    playerRollLine.insertAdjacentHTML('beforeend', `<span class='whiteDie'>${whiteDie}</span>`);
                    totalPower += whiteDie;
                }

                playerRollLine.insertAdjacentHTML('beforeend', `<div id='${result.playerId}_moraleRow'></div>`);
                let moraleRowEl = document.getElementById(`${result.playerId}_moraleRow`);

                for (let i = 0; i < result.assignedMorale; ++i)
                    moraleRowEl.insertAdjacentHTML('beforeend', `<span class='moraleSpent'></span>`);

                if (parseInt(result.playerId) === this._root.opponentId) {
                    playerRollLine.insertAdjacentHTML('beforeend', `<div class='powerDistance'>${distance}</div>`);
                    playerRollLine.insertAdjacentHTML('beforeend', `<div class="powerBlockingPieces">${blockingPieces}</div>`);
                }

                if (skipEnabled) {
                    playerRollLine.insertAdjacentHTML('beforeend', `<div style="color: white; font-size: 20px; font-weight: bold">SKIP</div>`);
                }

                playerRollLine.insertAdjacentHTML('beforeend', `<div style="color: white; font-size: 20px;">${totalPower}</div>`);
            }

            if (rollResults.length === 1) {
                let neutralRollLine = this._createNeutralRollLineElement(diceRollAreaEl);
                neutralRollLine.insertAdjacentHTML('beforeend', `<div class='powerDistance'>${distance}</div>`);
                neutralRollLine.insertAdjacentHTML('beforeend', `<div class="powerBlockingPieces">${blockingPieces}</div>`);
            }

            const TIMEOUT = 3000;
            setTimeout(() => {
                diceRollAreaEl.remove();
            }, TIMEOUT);

            return this._root.wait(TIMEOUT);
        },

        createRollResult: function (playerId, rollResult) {
            return {
                playerId: playerId,
                greenDie: rollResult.greenDie,
                whiteDice: rollResult.whiteDice,
                assignedMorale: rollResult.assignedMorale
            }
        },

        _createRollLineElement: function (parentEl, lineNum, result) {

            let backColor = (result.playerId === this._root.player_id ? this._root.myColor : this._root.opponentColor);
            let pos = (lineNum === 0 ? 'top:20%; left: 0;' : 'bottom: 20%; right: 0;');
            let rowStyle = (lineNum === 0 ? 'flex-direction: row;' : 'flex-direction: row-reverse;');
            let anchorClass = (lineNum === 0 ? 'left' : 'right');

            parentEl.insertAdjacentHTML('beforeend', `<div id='${result.playerId}_RollLine' style='--ribbonColor: ${backColor}; ${pos} ${rowStyle}' class='diceResult ${anchorClass}'></div>`)

            return document.getElementById(`${result.playerId}_RollLine`);
        },

        _createNeutralRollLineElement: function (parentEl) {
            let backColor = 'grey';
            let pos = 'bottom: 20%; right: 0;';
            let rowStyle = 'flex-direction: row-reverse;';
            let anchorClass = 'right';

            parentEl.insertAdjacentHTML('beforeend', `<div id='neutral_RollLine' style='--ribbonColor: ${backColor}; ${pos} ${rowStyle}' class='diceResult ${anchorClass}'></div>`)

            return document.getElementById(`neutral_RollLine`);
        },

        //Private Members
        _root: null,
    }
});