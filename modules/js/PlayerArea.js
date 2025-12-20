define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/CoachBoard.js",
],
    function (declare, CoachBoard) {
        const PlayerArea = declare("st.PlayerArea", null, {
            constructor: function (ownerId, ownerData, root) {
                this._ownerId = ownerId;
                this._ownerData = ownerData;
                this._root = root;

                this._init();
            },

            getElement()
            {
                return this._areaEl;
            },

            _init: function()
            {
                this._areaEl = document.createElement('div');
                this._areaEl.id = `${this._ownerId}_PlayerArea`;
                this._areaEl.className = 'playerArea';
                this._root.getGameAreaElement().appendChild(this._areaEl);

                const playerName = document.createElement('div');
                playerName.innerHTML = `${this._ownerData.name}`;
                playerName.classList = 'readonlyText';
                playerName.style.setProperty('--color', `#${this._ownerData.color}`);
                playerName.style.setProperty('--size', 'large');
                this._areaEl.appendChild(playerName);

                //TODO: the data must be filtered, and only skill values must be sent.
                this._coachBoard = new CoachBoard(this._ownerId, this._ownerData, this._root, this)
            },

            _ownerId: 0,
            _ownerData: null,
            _root: null,
            _areaEl: null,
            _coachBoard: null
        });

        return PlayerArea;
    });