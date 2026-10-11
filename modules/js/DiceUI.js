define([
    g_gamethemeurl + "modules/js/GameEventProcessor.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    getLibUrl('bga-dice', '1.x')
], function (GameEventProcessor, Board, BgaDice) {

    const DISPLAY_TIME = 5 * 1000;

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

        // ------------------------------------------------------------------
        // Public API
        // ------------------------------------------------------------------

        isShowing: function () {
            return this._active !== null && this._active.areaEl.isConnected;
        },

        showRoll: async function (params, immediate) {
            if (this._active) {
                this.dismiss();
            }

            const { rollResults, attacker, requiredPower, blockingPieces, distance, actionName, passDest = null } = params;

            if (passDest) {
                Board.visualizePowerRequired(Board.getHexWithBall(), passDest, attacker !== this._root.viewerId);
                this._passDestShown = true;
            }

            Board.getMainBoardElement().insertAdjacentHTML('beforeend', `<div id="diceRollArea"></div>`);
            const areaEl = document.getElementById('diceRollArea');

            const active = this._active = {
                areaEl,
                params: { attacker, distance, blockingPieces, requiredPower },
                players: [],
                dismissTimer: null,
                dismissResolve: null,
            };

            const actionTitleEl = document.createElement('div');
            actionTitleEl.className = 'diceActionTitle';
            actionTitleEl.textContent = _(actionName);
            areaEl.appendChild(actionTitleEl);

            // Phase 1: create the lines and put the dice in.
            for (let i = 0; i < rollResults.length; ++i) {
                // Copy so rerolls never mutate the caller's data.
                const result = { ...rollResults[i], whiteDice: [...rollResults[i].whiteDice] };

                const line = this._createRollLineElement(areaEl, i, result);
                const stock = new BgaDice.LineStock(this._diceManager, line, { direction: 'row', gap: '8px' });

                const greenDie = { id: `green-${result.playerId}`, face: result.greenDie, type: 'die-green' };
                const whiteDice = result.whiteDice.map((value, j) => ({
                    id: `white-${result.playerId}-${j}`,
                    face: value + 2,
                    type: 'die-white',
                }));
                const allDice = [greenDie, ...whiteDice];

                if (immediate) {
                    await stock.addDice(allDice);
                } else {
                    for (const die of allDice) {
                        const finalFace = die.face;
                        die.face = Math.floor(Math.random() * 6) + 1;
                        await stock.addDice([die]);
                        die.face = finalFace;
                    }
                }

                active.players.push({ result, line, stock, allDice, whiteDice, totalEl: null });
            }

            // Phase 2: roll (unless immediate) and decorate.
            for (const player of active.players) {
                if (!immediate) {
                    await Promise.all(player.allDice.map(die => player.stock.rollDie(die)));
                }
                this._decoratePlayerLine(player, params, immediate);
            }

            if (rollResults.length === 1) {
                this._buildNeutralLine(areaEl, requiredPower, blockingPieces, distance, immediate);
            }
        },

        rerollWhiteDice: async function (rollResults) {
            const active = this._active;
            if (!active) {
                return false;
            }

            const rolls = [];

            for (const newResult of rollResults) {
                const player = active.players.find(p => Number(p.result.playerId) === Number(newResult.playerId));
                if (!player) {
                    continue;
                }

                player.result.whiteDice.forEach((currentValue, j) => {
                    if (currentValue !== -1) {
                        return;
                    }

                    const newValue = newResult.whiteDice[j];
                    player.result.whiteDice[j] = newValue;

                    const die = player.whiteDice[j];
                    die.face = newValue + 2;
                    rolls.push(player.stock.rollDie(die));
                });
            }

            await Promise.all(rolls);

            const DISPLAY_TIME = 2 * 1000;
            await this._root.wait(DISPLAY_TIME);

            for (const player of active.players) {
                player.totalEl.textContent = this._computeTotal(player.result, active.params);
            }

            return rolls.length > 0;
        },

        dismiss: function () {
            if (!this._active) {
                return;
            }
            this._active.areaEl.remove();
            this._active = null;
        },

        clearRollVisuals: function () {
            GameEventProcessor.RemoveAssignedMorale();

            if (this._passDestShown) {
                Board.removeRequiredPowerVisualization();
                this._passDestShown = false;
            }
        },

        // ------------------------------------------------------------------
        // Log formatting (unchanged)
        // ------------------------------------------------------------------

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

        // ------------------------------------------------------------------
        // Internals
        // ------------------------------------------------------------------

        _computeTotal: function (result, { attacker, distance, blockingPieces }) {
            let total = result.greenDie + result.assignedMorale + result.captainStars;

            for (const whiteDie of result.whiteDice) {
                total += whiteDie;
            }

            if (Number(result.playerId) !== Number(attacker)) {
                if (distance > 0) {
                    total += this._getDistancePowerContribution(distance);
                }
                if (blockingPieces > 0) {
                    total += blockingPieces;
                }
            }

            return total;
        },

        _decoratePlayerLine: function (player, params, immediate) {
            const { result, line } = player;
            const { attacker, distance, blockingPieces, skipEnabledPlayerId } = params;

            if (result.assignedMorale > 0) {
                const moraleRowEl = document.createElement('div');
                moraleRowEl.id = `${result.playerId}_moraleRow`;
                line.appendChild(moraleRowEl);

                for (let j = 0; j < result.assignedMorale; ++j) {
                    const moraleEl = document.createElement('span');
                    moraleEl.className = 'moraleSpent';
                    moraleRowEl.appendChild(moraleEl);
                    this._fadeInElement(moraleEl, immediate);
                }
            }

            if (result.captainStars > 0) {
                const starColumnEl = document.createElement('div');
                starColumnEl.className = 'captainStarColumn';
                line.appendChild(starColumnEl);

                for (let j = 0; j < result.captainStars; ++j) {
                    const starEl = document.createElement('span');
                    starEl.className = 'blackStar';
                    starColumnEl.appendChild(starEl);
                }

                this._fadeInElement(starColumnEl, immediate);
            }

            if (Number(result.playerId) !== Number(attacker)) {
                if (distance > 0) {
                    const distanceEl = document.createElement('div');
                    distanceEl.className = 'powerDistance';
                    distanceEl.textContent = this._getDistancePowerContribution(distance);
                    line.appendChild(distanceEl);
                    this._fadeInElement(distanceEl, immediate);
                }

                if (blockingPieces > 0) {
                    const blockingEl = document.createElement('div');
                    blockingEl.className = 'powerBlockingPieces';
                    blockingEl.textContent = blockingPieces;
                    line.appendChild(blockingEl);
                    this._fadeInElement(blockingEl, immediate);
                }
            }

            if (Number(skipEnabledPlayerId) === Number(result.playerId)) {
                const skipEl = document.createElement('div');
                skipEl.style.color = 'white';
                skipEl.style.fontSize = '20px';
                skipEl.style.fontWeight = 'bold';
                skipEl.textContent = 'SKIP';
                line.appendChild(skipEl);
                this._fadeInElement(skipEl, immediate);
            }

            const totalEl = document.createElement('div');
            totalEl.style.color = 'white';
            totalEl.style.fontSize = '20px';
            totalEl.textContent = this._computeTotal(result, this._active.params);
            line.appendChild(totalEl);
            this._fadeInElement(totalEl, immediate);

            player.totalEl = totalEl;
        },

        _buildNeutralLine: function (areaEl, requiredPower, blockingPieces, distance, immediate) {
            const neutralRollLine = this._createNeutralRollLineElement(areaEl);

            const neutralDistanceEl = document.createElement('div');
            neutralDistanceEl.className = 'powerDistance';
            neutralDistanceEl.textContent = this._getDistancePowerContribution(distance);
            neutralRollLine.appendChild(neutralDistanceEl);
            this._fadeInElement(neutralDistanceEl, immediate);

            const neutralBlockingEl = document.createElement('div');
            neutralBlockingEl.className = 'powerBlockingPieces';
            neutralBlockingEl.textContent = blockingPieces;
            neutralRollLine.appendChild(neutralBlockingEl);
            this._fadeInElement(neutralBlockingEl, immediate);

            const requiredPowerEl = document.createElement('div');
            requiredPowerEl.style.color = 'white';
            requiredPowerEl.style.fontSize = '20px';
            requiredPowerEl.textContent = requiredPower;
            neutralRollLine.appendChild(requiredPowerEl);
            this._fadeInElement(requiredPowerEl, immediate);
        },

        _fadeInElement: function (element, immediate = false) {
            if (immediate) {
                return;
            }

            element.style.opacity = '0';
            element.style.transition = 'opacity 0.8s ease-out';

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    element.style.opacity = '1';
                });
            });
        },

        _createRollLineElement: function (parentEl, lineNum, result) {
            const backColor = this._root.getPlayerActualColor(result.playerId);

            const pos = lineNum === 0 ? 'top:20%; left: 0;' : 'bottom: 20%; right: 0;';
            const rowStyle = lineNum === 0 ? 'flex-direction: row;' : 'flex-direction: row-reverse;';
            const anchorClass = lineNum === 0 ? 'left' : 'right';

            parentEl.insertAdjacentHTML(
                'beforeend',
                `<div
                    id='${result.playerId}_RollLine'
                    style='--dice-result-color: ${backColor}; ${pos} ${rowStyle}'
                    class='diceResult ${anchorClass}'>
                </div>`
            );

            return document.getElementById(`${result.playerId}_RollLine`);
        },

        _createNeutralRollLineElement: function (parentEl) {
            parentEl.insertAdjacentHTML(
                'beforeend',
                `<div
                    id='neutral_RollLine'
                    style='--dice-result-color: grey; bottom: 20%; right: 0; flex-direction: row-reverse;'
                    class='diceResult right'>
                </div>`
            );

            return document.getElementById('neutral_RollLine');
        },

        _getDistancePowerContribution: function (distance) {
            return Math.ceil(distance / 2);
        },

        _root: null,
        _diceManager: null,
        _active: null,
        _passDestShown: false,
    };
});