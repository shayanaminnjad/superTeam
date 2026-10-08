define([
    g_gamethemeurl + "modules/js/GameEventProcessor.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    getLibUrl('bga-dice', '1.x')
], function (GameEventProcessor, Board, BgaDice) {

    return {
        construct: function (root) {
            this._root = root;

            this._diceManager = new BgaDice.Manager({
                animationManager: this._root.animationManager,
                faces: 6,
                borderRadius: 0,
                size: 32,
                perspective: 1000,
                type: 'st-dice',
                setupFaceDiv: (die, element, face) => {
                    element.classList.add('superteam-dice-face');
                    element.classList.add(`face-${face}`);
                    element.classList.add(die.type);
                }
            });
        },

        showRollAndWait: async function (
            rollResults,
            attacker,
            requiredPower,
            blockingPieces,
            distance,
            skipEnabledPlayerId,
            actionName,
            passDest = null
        ) {

            if (passDest) {
                Board.visualizePowerRequired(Board.getHexWithBall(), passDest, attacker !== this._root.player_id);
            }

            Board.getMainBoardElement().insertAdjacentHTML('beforeend', `<div id="diceRollArea"></div>`);

            const diceRollAreaEl = document.getElementById('diceRollArea');

            const actionTitleEl = document.createElement('div');
            actionTitleEl.className = 'diceActionTitle';
            actionTitleEl.textContent = _(actionName);
            diceRollAreaEl.appendChild(actionTitleEl);

            const rollPlayers = [];

            for (let i = 0; i < rollResults.length; ++i) {
                const result = rollResults[i];

                const playerRollLine = this._createRollLineElement(diceRollAreaEl, i, result);

                const playerDiceStock = new BgaDice.LineStock(
                    this._diceManager,
                    playerRollLine,
                    {
                        direction: 'row',
                        gap: '8px',
                    }
                );

                const playerDice = [
                    {
                        id: `green-${result.playerId}`,
                        face: result.greenDie,
                        type: 'die-green',
                    },
                ];

                for (let j = 0; j < result.whiteDice.length; ++j) {
                    playerDice.push({
                        id: `white-${result.playerId}-${j}`,
                        face: result.whiteDice[j] + 2,
                        type: 'die-white',
                    });
                }


                for (let i = 0; i < playerDice.length; ++i) {

                    const die = playerDice[i];
                    const finalFace = die.face;
                    die.face = Math.floor(Math.random() * 6) + 1;

                    await playerDiceStock.addDice([die]);

                    die.face = finalFace;
                }

                let totalPower = result.greenDie + result.assignedMorale + result.captainStars;

                for (const whiteDie of result.whiteDice) {
                    totalPower += whiteDie;
                }

                if (parseInt(result.playerId) !== attacker) {
                    if (distance > 0) {
                        totalPower += this._getDistancePowerContribution(distance);
                    }

                    if (blockingPieces > 0) {
                        totalPower += blockingPieces;
                    }
                }

                rollPlayers.push({
                    result,
                    playerRollLine,
                    playerDiceStock,
                    playerDice,
                    totalPower,
                });
            }

            for (const player of rollPlayers) {
                const {
                    result,
                    playerRollLine,
                    playerDiceStock,
                    playerDice,
                    totalPower,
                } = player;

                await Promise.all(playerDice.map(die => playerDiceStock.rollDie(die)));

                if (result.assignedMorale > 0) {
                    const moraleRowEl = document.createElement('div');

                    moraleRowEl.id = `${result.playerId}_moraleRow`;

                    playerRollLine.appendChild(moraleRowEl);

                    for (let j = 0; j < result.assignedMorale; ++j) {
                        const moraleEl = document.createElement('span');

                        moraleEl.className = 'moraleSpent';

                        moraleRowEl.appendChild(moraleEl);

                        this._fadeInElement(moraleEl);
                    }
                }

                if (result.captainStars > 0) {
                    const starColumnEl = document.createElement('div');

                    starColumnEl.className = 'captainStarColumn';

                    playerRollLine.appendChild(starColumnEl);

                    for (let j = 0; j < result.captainStars; ++j) {
                        const starEl = document.createElement('span');

                        starEl.className = 'blackStar';

                        starColumnEl.appendChild(starEl);
                    }

                    this._fadeInElement(starColumnEl);
                }

                if (parseInt(result.playerId) !== attacker) {
                    if (distance > 0) {
                        const distancePowerContribution = this._getDistancePowerContribution(distance);

                        const distanceEl = document.createElement('div');

                        distanceEl.className = 'powerDistance';
                        distanceEl.textContent = distancePowerContribution;

                        playerRollLine.appendChild(distanceEl);

                        this._fadeInElement(distanceEl);
                    }

                    if (blockingPieces > 0) {
                        const blockingEl = document.createElement('div');

                        blockingEl.className = 'powerBlockingPieces';
                        blockingEl.textContent = blockingPieces;

                        playerRollLine.appendChild(blockingEl);

                        this._fadeInElement(blockingEl);
                    }
                }

                if (skipEnabledPlayerId === result.playerId) {
                    const skipEl = document.createElement('div');

                    skipEl.style.color = 'white';
                    skipEl.style.fontSize = '20px';
                    skipEl.style.fontWeight = 'bold';
                    skipEl.textContent = 'SKIP';

                    playerRollLine.appendChild(skipEl);

                    this._fadeInElement(skipEl);
                }

                const totalPowerEl = document.createElement('div');

                totalPowerEl.style.color = 'white';
                totalPowerEl.style.fontSize = '20px';
                totalPowerEl.textContent = totalPower;

                playerRollLine.appendChild(totalPowerEl);

                this._fadeInElement(totalPowerEl);
            }

            if (rollResults.length === 1) {
                const neutralRollLine = this._createNeutralRollLineElement(diceRollAreaEl);

                const neutralDistanceEl = document.createElement('div');

                neutralDistanceEl.className = 'powerDistance';
                neutralDistanceEl.textContent = this._getDistancePowerContribution(distance);

                neutralRollLine.appendChild(neutralDistanceEl);

                this._fadeInElement(neutralDistanceEl);

                const neutralBlockingEl = document.createElement('div');

                neutralBlockingEl.className = 'powerBlockingPieces';
                neutralBlockingEl.textContent = blockingPieces;

                neutralRollLine.appendChild(neutralBlockingEl);

                this._fadeInElement(neutralBlockingEl);

                const requiredPowerEl = document.createElement('div');

                requiredPowerEl.style.color = 'white';
                requiredPowerEl.style.fontSize = '20px';
                requiredPowerEl.textContent = requiredPower;

                neutralRollLine.appendChild(requiredPowerEl);

                this._fadeInElement(requiredPowerEl);
            }

            const DISPLAY_TIME = 5 * 1000;

            await this._root.wait(DISPLAY_TIME);

            GameEventProcessor.RemoveAssignedMorale();
            diceRollAreaEl.remove();

            if (passDest) {
                Board.removeRequiredPowerVisualization();
            }
        },

        _fadeInElement: function (element) {
            element.style.opacity = '0';
            element.style.transition = 'opacity 0.8s ease-out';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    element.style.opacity = '1';
                });
            });
        },

        createRollResult: function (playerId, rollResult) {
            return {
                playerId: playerId,
                greenDie: rollResult.greenDie,
                whiteDice: rollResult.whiteDice,
                assignedMorale: rollResult.assignedMorale,
                captainStars: rollResult.captainStars
            };
        },

        formatRollLog: function (rollResults, players, requiredPower, distance, blockingPieces, actionName, skipEnabledPlayerId) {
            let html = `<div class="diceLogAction">${_(actionName)}</div>`;

            for (const playerId of Object.keys(rollResults)) {
                const skipEnabled = parseInt(playerId) === skipEnabledPlayerId;
                html += this._formatRollLogRow(this._root.getPlayerActualColor(parseInt(playerId)), rollResults[playerId], skipEnabled);
            }

            return html + this._formatRequiredPowerLogRow(requiredPower, distance, blockingPieces);
        },

        _formatRollLogRow: function (playerColor, result, skipEnabled) {
            // Logs written before captain stars existed have no such field.
            const captainStars = result.captainStars ?? 0;
            const totalPower = result.whiteDice.reduce((sum, die) => sum + die, result.greenDie + result.assignedMorale + captainStars);

            let html = '<div class="diceLogRow">';
            html += `<span class="diceLogColorMark" style="background-color:${playerColor}"></span>`;
            html += `<span class="diceLogIcon superteam-dice-face die-green face-${result.greenDie}"></span>`;

            for (const whiteDie of result.whiteDice) {
                html += `<span class="diceLogIcon superteam-dice-face die-white face-${whiteDie + 2}"></span>`;
            }

            if (result.assignedMorale > 0) {
                html += '<span class="diceLogIcon diceLogMorale"></span>';
                html += `<span class="diceLogMoraleValue">×${result.assignedMorale}</span>`;
            }

            if (captainStars > 0) {
                html += '<span class="blackStar"></span>';
                html += `<span class="diceLogMoraleValue">×${captainStars}</span>`;
            }

            if (skipEnabled) {
                html += `<span class="diceLogSkip">${_('SKIP')}</span>`;
            }

            html += '<span class="diceLogEquals">=</span>';
            html += `<span class="diceLogTotal">${totalPower}</span>`;
            return html + '</div>';
        },

        _formatRequiredPowerLogRow: function (requiredPower, distance, blockingPieces) {
            if (requiredPower === 0) {
                return '';
            }

            let html = '<div class="diceLogRow diceLogTargetRow">';

            if (distance > 0) {
                html += '<span class="diceLogIcon diceLogDistance"></span>';
            }

            if (blockingPieces > 0) {
                html += '<span class="diceLogIcon diceLogBlocking"></span>';
            }

            html += '<span class="diceLogEquals">=</span>';
            html += `<span class="diceLogTotal">${requiredPower}</span>`;
            return html + '</div>';
        },

        _createRollLineElement: function (parentEl, lineNum, result) {
            const backColor = this._root.getPlayerActualColor(result.playerId);

            const pos =
                lineNum === 0
                    ? 'top:20%; left: 0;'
                    : 'bottom: 20%; right: 0;';

            const rowStyle =
                lineNum === 0
                    ? 'flex-direction: row;'
                    : 'flex-direction: row-reverse;';

            const anchorClass =
                lineNum === 0 ? 'left' : 'right';

            parentEl.insertAdjacentHTML(
                'beforeend',
                `<div
                    id='${result.playerId}_RollLine'
                    style='--dice-result-color: ${backColor}; ${pos} ${rowStyle}'
                    class='diceResult ${anchorClass}'>
                </div>`
            );

            return document.getElementById(
                `${result.playerId}_RollLine`
            );
        },

        _createNeutralRollLineElement: function (parentEl) {
            const backColor = 'grey';
            const pos = 'bottom: 20%; right: 0;';
            const rowStyle = 'flex-direction: row-reverse;';
            const anchorClass = 'right';

            parentEl.insertAdjacentHTML(
                'beforeend',
                `<div
                    id='neutral_RollLine'
                    style='--dice-result-color: ${backColor}; ${pos} ${rowStyle}'
                    class='diceResult ${anchorClass}'>
                </div>`
            );

            return document.getElementById(
                'neutral_RollLine'
            );
        },

        _getDistancePowerContribution: function (distance) {
            return Math.ceil(distance / 2);
        },

        _root: null,
        _diceManager: null,
    };
});