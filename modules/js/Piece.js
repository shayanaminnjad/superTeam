define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/STHexLib.js"
],
    function (declare, STHexLib) {

        // constant enum-like roles
        const playerRoles = {
            None: 0,
            Goalie: 1,
            Captain: 2,
            Regular: 3
        };

        const Piece = declare("st.Piece", null, {
            constructor: function (initData, color) {
                this._owner = parseInt(initData.owner_id);
                this._isFresh = initData.is_fresh === '1';
                this._id = parseInt(initData.id);
                this._role = parseInt(initData.piece_role);
                this._color = color;

                this._addToDOM();
                this.getDOMElement().style.display = "none";

                const hexColumn = initData.hex_column;
                const hexRow = initData.hex_row;

                if (hexColumn != -1 && hexRow != -1) {
                    let offsetCoord = STHexLib.createOffsetCoord(hexRow, hexColumn);
                    this.setPosition(offsetCoord);
                }
            },
            isGoalie: function () {
                return this._role === playerRoles.Goalie;
            },

            isCaptain: function () {
                return this._role === playerRoles.Captain;
            },

            isFresh: function () {
                return this._isFresh;
            },

            setFreshness: function (newVal) {
                if (newVal == this._isFresh)
                    return;

                this._isFresh = newVal;

                let el = this.getDOMElement();
                this._removeStatus(el);

                el.classList.add(this._getStatus());
                el.innerHTML = this._generateInnerHTML();
            },

            getRole: function () {
                return this._role;
            },

            getStringifiedRole: function () {
                switch (this._role) {
                    case playerRoles.Goalie: return "goalie";
                    case playerRoles.Captain: return "captain";
                    case playerRoles.Regular: return "regular";
                    default: throw new Error("invalid role id!");
                }
            },

            getOwner: function () {
                return this._owner;
            },

            getPosition: function () {
                return this._position;
            },

            setPosition: function (offsetPos) {
                if (this._position === null && offsetPos !== null) {
                    this.getDOMElement().style.display = "block";
                }
                else if (offsetPos === null) {
                    this.getDOMElement().style.display = "none";
                }

                this._position = offsetPos;
            },

            getId: function () {
                return this._id;
            },

            getDOMElement: function () {
                let DOMId = this.getDOMID();
                return document.getElementById(DOMId);
            },

            getDOMID: function () {
                return "piece_" + this.getId();
            },

            playerRoles: playerRoles,

            //Private functions
            _addToDOM: function () {

                const PIECE_DOM_ID = this.getDOMID();
                let status = this._getStatus();
                let type = this.getStringifiedRole();
                let inner = this._generateInnerHTML();

                document.getElementById('pieces').insertAdjacentHTML('beforeend', `<div id="${PIECE_DOM_ID}" class="piece ${status} ${type} ${this._color}" >${inner}</div>`);
            },

            _getStatus: function () {
                return this.isFresh() ? 'fresh' : 'exhausted';
            },

            _removeStatus: function (el) {
                el.classList.remove('fresh', 'exhausted');
            },

            _generateInnerHTML: function () {
                if (this.getRole() === playerRoles.Captain) {
                    let type = this.getStringifiedRole();
                    return `${type[0]}`;
                }
                else {
                    return '';
                }
            },

            // Private members
            _owner: null,
            _position: null,
            _isFresh: null,
            _id: null,
            _role: null,
            _color: null
        });

        Piece.playerRoles = playerRoles;
        return Piece;
    });
