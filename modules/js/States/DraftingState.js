define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/DraftingUI.js"],
    function (declare, BaseState, DraftingUI) {

        const DraftingState = declare("st.DraftingState", BaseState, {

            constructor: function (root, stateData) {
                this._ui = null;
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                document.body.classList.add('drafting');

                this._ui = new DraftingUI(this._root, args._private, () => this._updateButtons());
                this._updateButtons();
            },

            _onInactivePlayerEnter: function (args) {
                document.body.classList.add('drafting');
            },

            _onActivePlayerLeave: function () {
                this._cleanup();
            },

            _onInactivePlayerLeave: function () {
                this._cleanup();
            },

            onMultiActiveStateFinished: function () {
                if (this._ui)
                    this._ui.lock();
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_draftRevealed: function (notifData) {
                for (const [playerId, powers] of Object.entries(notifData.players)) {
                    const playerData = this._root.gameDatas.players[playerId];
                    playerData.captain_power = powers.captain_power;
                    playerData.team_power = powers.team_power;

                    const playerArea = parseInt(playerId) === this._root.viewerId ? this._root.myPlayerArea : this._root.opponentPlayerArea;
                    playerArea.setPowers(powers.captain_power, powers.team_power);
                }
            },

            notif_deckCreated: function (notifData) {
                this._root.cardsManager.placeCards(Object.values(notifData.deck).concat(Object.values(notifData.supply)));
            },

            _updateButtons: function () {
                this._root.statusBar.removeActionButtons();

                this._root.statusBar.addActionButton(_('Confirm'), () => {
                    this._root.performServerAction("actConfirmDraft", {
                        captainId: this._ui.getCaptainId(),
                        coachId: this._ui.getCoachId()
                    });
                }, { id: 'confirmDraft', color: 'primary', disabled: !this._ui.isComplete() });
            },

            _cleanup: function () {
                document.body.classList.remove('drafting');

                if (this._ui) {
                    this._ui.destroy();
                    this._ui = null;
                }
            }

        });

        DraftingState.getName = function () {
            return 'drafting';
        };

        return DraftingState;

    });
