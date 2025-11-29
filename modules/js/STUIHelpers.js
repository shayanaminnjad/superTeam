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
    }
});