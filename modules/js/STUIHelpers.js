define([], function () {

    return {
        createCounter: function (where, id, min, max) {
            let generalActionsEl = document.getElementById(where);
            generalActionsEl.insertAdjacentHTML('afterbegin', `<span id=${id}><button id="${id}_subButton" type="button" class="action-button bgabutton bgabutton_blue">-</button><span id="${id}_value" style="margin-left: 5px">0</span><button id="${id}_addButton" type="button" class="action-button bgabutton bgabutton_blue">+</button></span>`)

            document.getElementById(`${id}_subButton`).addEventListener('click', e => {
                let counterValue = this.getCounterValue(id);
                if (counterValue > min)
                    this.setCounterValue(id, counterValue - 1);
            });

            document.getElementById(`${id}_addButton`).addEventListener('click', e => {
                let counterValue = this.getCounterValue(id);
                if (counterValue + 1 <= max)
                    this.setCounterValue(id, counterValue + 1);
            });
        },

        getCounterValue: function (id) {
            let counterTextEl = document.getElementById(`${id}_value`);
            return parseInt(counterTextEl.innerHTML);
        },

        setCounterValue: function (id, value) {
            let counterTextEl = document.getElementById(`${id}_value`);
            counterTextEl.innerHTML = value;
        },

        removeCounter: function (id) {
            document.getElementById(id).remove();
        },

        // x position (percent) of a captain inside captainsAtlas, undefined for an unknown id
        captainAtlasPercent: function (captainId) {
            const map = new Map([
                ["1", 0],
                ["3", 20],
                ["4", 40],
                ["6", 60],
                ["14", 80],
                ["21", 100]
            ]);

            return map.get(String(captainId));
        },

        // background-position of a coach board inside coachBoardsAtlas, undefined for an unknown id
        coachBoardAtlasPosition: function (coachBoardId) {
            const map = new Map([
                ["0", "0% 100%"],
                ["1", "100% 100%"],
                ["2", "50% 100%"],
                ["3", "0% 0%"],
                ["4", "50% 0%"],
                ["5", "100% 0%"]
            ]);

            return map.get(String(coachBoardId));
        },

        buildCaptainTooltipHtml: function (captainDefinition, captainId) {
            return this.buildTooltipHtml('captainTooltipImage', `${this.captainAtlasPercent(captainId)}% 0`,
                _(captainDefinition.name), _(captainDefinition.toolTip));
        },

        buildCoachTooltipHtml: function (coachDefinition, coachBoardId) {
            return this.buildTooltipHtml('coachTooltipImage', this.coachBoardAtlasPosition(coachBoardId),
                _(coachDefinition.name), _(coachDefinition.toolTip));
        },

        // name and description must already be translated
        buildTooltipHtml: function (imageClass, backgroundPosition, name, description) {
            return `<div class="cardTooltip">
                <div class="cardTooltipImage ${imageClass}" style="background-position: ${backgroundPosition};"></div>
                <div class="cardTooltipText">
                    <div class="cardTooltipName">${name}</div>
                    ${this.richTextToHtml(description)}
                </div>
            </div>`;
        },

        // Supported markup: **bold**, *italic*, a line with only --- (divider), blank line (gap).
        richTextToHtml: function (text) {
            const escaped = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            return escaped.split('\n').map(line => {
                const trimmed = line.trim();

                if (trimmed === '---') {
                    return '<hr class="cardTooltipDivider">';
                }
                if (trimmed === '') {
                    return '<div class="cardTooltipGap"></div>';
                }

                const formatted = trimmed
                    .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
                    .replace(/\*(.+?)\*/g, '<i>$1</i>');
                return `<div class="cardTooltipLine">${formatted}</div>`;
            }).join('');
        },

        // [token] markers in button labels from the server, e.g. "[move]"
        iconMap: {
            forward: '<div class="moveForward buttonIcon"></div>',
            backward: '<div class="moveBackward buttonIcon"></div>',
            forwardNearOpponent: '<div class="moveForwardNearOpponent buttonIcon"></div>',
            backwardNearOpponent: '<div class="moveBackNearOpponent buttonIcon"></div>',
            free: '<div class="moveFree buttonIcon"></div>',
            action: '<div class="action buttonIcon"></div>',
            move: '<div class="move buttonIcon"></div>',
            morale: '<div class="diceLogIcon diceLogMorale buttonIcon"></div>'
        },

        parseIcons: function (text) {
            return text.replace(/\[([^\]]+)\]/g, (match, key) => {
                return this.iconMap[key] ?? match;
            });
        },
    }
});