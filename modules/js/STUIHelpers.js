define([], function () {

    return {
        createCounter: function (where, id, min, max) {
            let generalActionsEl = document.getElementById(where);
            generalActionsEl.insertAdjacentHTML('afterbegin', `<span id=${id}><button id="${id}_subButton" type="button" class="action-button bgabutton bgabutton_blue">-</button><span id="${id}_value" style="margin-left: 5px">0</span><button id="${id}_addButton" type="button" class="action-button bgabutton bgabutton_blue">+</button></span>`)

            document.getElementById(`${id}_subButton`).addEventListener('click', e => {
                let counterTextEl = document.getElementById('${id}_value');
                let counterValue = parseInt(counterTextEl.innerHTML);
                if (counterValue > min)
                    counterTextEl.innerHTML = counterValue - 1;
            });

            document.getElementById(`${id}_addButton`).addEventListener('click', e => {
                let counterTextEl = document.getElementById('${id}_value');
                let counterValue = parseInt(counterTextEl.innerHTML);
                if (counterValue + 1 <= max)
                    counterTextEl.innerHTML = counterValue + 1;
            });
        },

        removeCounter: function(id)
        {
            document.getElementById(id).remove();
        }
    }
});