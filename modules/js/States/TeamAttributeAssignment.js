define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",],
    function (declare, BaseState) {

        const TeamAttributeAssignment = declare("st.TeamAttributeAssignment", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._root.myPlayerArea.getCoachBoard().setClickHandler((evt, skillSlot) => this.onSkillSelected(evt, skillSlot));
                this._root.myPlayerArea.getCoachBoard().makeAvailableBoxesSelectable(args.IsGKAssignable);
                this._updateButtons();
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                this._cleanup();
            },

            _onInactivePlayerLeave: function () {
                this._cleanup();
            },

            onMultiActiveStateFinished: function() {
                this._cleanup();
            },

            getName: function () {
                return this.constructor.getName();
            },

            //Notifications
            notif_cubePlaced: async function (notifData) {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                myCoachBoard.makeAllNonSelectable();
                await myCoachBoard.placeCubeAnimated(parseInt(notifData.skill));
                myCoachBoard.makeAvailableBoxesSelectable(this._args.IsGKAssignable);

                this._updateButtons();
            },

            notif_playerAttributesReset: async function(notifData) {
                const myAttributes = notifData[this._root.viewerId];
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();

                myCoachBoard.makeAllNonSelectable();

                await myCoachBoard.updateOwnerDataAnimated(myAttributes);

                myCoachBoard.makeAvailableBoxesSelectable(this._args.IsGKAssignable);

                this._updateButtons();
            },

            onSkillSelected: function (evt, skillSlot) {
                evt.preventDefault();
                evt.stopPropagation();

                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                myCoachBoard.makeAllNonSelectable();

                try {
                    this._root.performServerAction("actPlacedAttrCube", { attributeId: skillSlot.skillIndex });
                }
                catch (e) {
                    myCoachBoard.makeAvailableBoxesSelectable(this._args.IsGKAssignable);
                }
            },

            _cleanup: function () {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                myCoachBoard.removeClickHandler();
                myCoachBoard.makeAllNonSelectable();
            },

            _updateButtons: function () {
                this._root.statusBar.removeActionButtons();

                this._root.statusBar.addActionButton(_('Restart'), () => {
                    this._root.performServerAction("actRestart", {})
                }, {id: 'restart', color: 'alert', disabled: this._getAssignedCubes() === 0});
            },

            _getAssignedCubes: function() {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();

                return myCoachBoard.getAssignedCubes(!this._args.IsGKAssignable);
            }

        });

        TeamAttributeAssignment.getName = function () {
            return 'teamAttributeAssignment';
        };

        return TeamAttributeAssignment;

    });