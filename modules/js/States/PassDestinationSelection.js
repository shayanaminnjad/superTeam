define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js"],
    function (declare, BaseState, STHexLib, Board) {

        const PassDestinationSelection = declare("st.PassDestinationSelection", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._makeDestinationsSelectable();
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                this._makeDestinationsUnSelectable();
            },

            _onInactivePlayerLeave: function () {
            },

            _selfOnHexClicked: async function (evt) {
                if (evt.currentTarget.classList.contains('selectable')) {
                    evt.preventDefault();
                    evt.stopPropagation();

                    var offsetCoord = Board.convertHexIdToOffsetCoord(evt.currentTarget.id);

                    await this._root.performServerAction("actDestSelected", {
                        hexColumn: offsetCoord.c,
                        hexRow: offsetCoord.r
                    });
                }
            },

            // In mobile mode there is no hover: the ruler follows the marked hex instead.
            onInputHeld: function (hexEl) {
                this._showPassRuler(hexEl);
            },

            onInputReleased: function () {
                Board.removeRequiredPowerVisualization();
            },

            _selfOnHexEnter: async function (evt) {
                if (this._root.inputConfirmation.isEnabled())
                    return;

                this._showPassRuler(evt.currentTarget);
            },

            _showPassRuler: function (hexEl) {
                if (hexEl.classList.contains('selectable')) {
                    Board.visualizePowerRequired(Board.getHexWithBall(), Board.convertHexIdToOffsetCoord(hexEl.id));
                }
            },

            _selfOnHexLeave: async function(evt) {
                if (this._root.inputConfirmation.isEnabled())
                    return;

                Board.removeRequiredPowerVisualization();
            },

            _makeDestinationsSelectable: function() {
                let allForcedPositions = [];
                const args = this._args;

                for(const forcedSpace of args.forcedPositions)
                {
                    allForcedPositions.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(forcedSpace.hex_row, forcedSpace.hex_column)))
                }

                if (allForcedPositions.length > 0)
                {
                    Board.makeHexesSelectable(allForcedPositions);
                }
                else
                {
                    let allBlockedSpaces = [];

                    for (const space of args.blockedSpaces) {
                        allBlockedSpaces.push(Board.convertOffsetCoordToHexId(STHexLib.createOffsetCoord(space.hex_row, space.hex_column)));
                    }

                    this.hexWithBall = STHexLib.createOffsetCoord(args.hexWithBall.hex_row, args.hexWithBall.hex_column)
                    allBlockedSpaces.push(Board.convertOffsetCoordToHexId(this.hexWithBall));

                    Board.makeAllHexesSelectableExcept(allBlockedSpaces);
                }

                const hoveredHex = Board.getHoveredHexElement();

                if (hoveredHex && !this._root.inputConfirmation.isEnabled())
                    this._showPassRuler(hoveredHex);
            },

            _makeDestinationsUnSelectable: function(){
                Board.makeAllHexesNonSelectable();
                Board.removeRequiredPowerVisualization();
            },

            getName: function () {
                return this.constructor.getName();
            }
        });

        PassDestinationSelection.getName = function () {
            return 'passDestinationSelection';
        };

        return PassDestinationSelection;

    });