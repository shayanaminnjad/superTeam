define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",],
    function (declare, BaseState) {

        const MAX_CUBES = 6;

        const TeamAttributeAssignment = declare("st.TeamAttributeAssignment", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._root.myPlayerArea.getCoachBoard().setClickHandler((evt, skillSlot) => this.onSkillSelected(evt, skillSlot));

                if (this._getAllCubesAssigned() < MAX_CUBES)
                    this._root.myPlayerArea.getCoachBoard().makeAvailableBoxesSelectable(args.IsGKAssignable);

                const title = $('pagemaintitletext');
                this._originalTitle = title.innerHTML;

                this._updateTitleBar();
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

                if (this._getAllCubesAssigned() < MAX_CUBES)
                    myCoachBoard.makeAvailableBoxesSelectable(this._args.IsGKAssignable);

                this._updateTitleBar();
            },

            notif_playerAttributesReset: async function(notifData) {
                const myAttributes = notifData[this._root.viewerId];
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();

                myCoachBoard.makeAllNonSelectable();

                await myCoachBoard.updateOwnerDataAnimated(myAttributes);

                myCoachBoard.makeAvailableBoxesSelectable(this._args.IsGKAssignable);

                this._updateTitleBar();
            },

            onSkillSelected: async function (evt, skillSlot) {
                evt.preventDefault();
                evt.stopPropagation();

                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                myCoachBoard.makeAllNonSelectable();

                try {
                    await this._root.performServerAction("actPlacedAttrCube", { attributeId: skillSlot.skillIndex }, { releaseOnAck: true });
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

            _updateTitleBar: function () {
                this._updateButtons();
                this._updateTitle();
            },

            _updateButtons: function() {
                this._root.statusBar.removeActionButtons();

                this._root.statusBar.addActionButton(_('Restart'), () => {
                    this._root.performServerAction("actRestart", {}, { releaseOnAck: true })
                }, {id: 'restart', color: 'alert', disabled: this._getAssignedCubesThisAssignment() === 0});

                this._root.statusBar.addActionButton(_('Confirm'), () => {
                    this._root.performServerAction("actConfirm", {}, { releaseOnAck: true })
                }, {id: 'confirm', color: 'primary', disabled: this._getAllCubesAssigned() !== MAX_CUBES});
            },

            _updateTitle: function() {
                const title = $('pagemaintitletext');
                const assigned = this._getAllCubesAssigned();

                if (assigned === MAX_CUBES)
                    title.innerHTML = _("Confirm or restart");
                else
                    title.innerHTML = this._originalTitle.replace('[markers_remained]', `${MAX_CUBES - assigned}`);
            },

            _getAllCubesAssigned: function() {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                return myCoachBoard.getAssignedCubes(false);
            },

            _getAssignedCubesThisAssignment: function() {
                const myCoachBoard = this._root.myPlayerArea.getCoachBoard();
                return myCoachBoard.getAssignedCubes(!this._args.IsGKAssignable);
            },

            _originalTitle: null
        });

        TeamAttributeAssignment.getName = function () {
            return 'teamAttributeAssignment';
        };

        return TeamAttributeAssignment;

    });