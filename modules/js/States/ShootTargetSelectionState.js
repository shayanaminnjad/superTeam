define(["dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/BaseState.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/STHexLib.js"],
    function (declare, BaseState, Board, HexLib) {

        const ShootTargetSelectionState = declare("st.ShootTargetSelectionState", BaseState, {

            constructor: function (root, stateData) {
            },

            //State Overrides
            _onActivePlayerEnter: function (args) {
                this._placeTargetingTiles(this._isCurrentPlayerAttacker());
            },

            _onInactivePlayerEnter: function (args) {
            },

            _onActivePlayerLeave: function () {
                this._cleanup();
            },

            _onInactivePlayerLeave: function () {
                this._cleanup();
            },

            getName: function () {
                return this.constructor.getName();
            },
            //Notifications

            //Private Methods
            onAttackerCanceledTarget: function (data) {
                Board.removeRequiredPowerVisualization();
            },

            _placeTargetingTiles: function (isAttacker) {
                let goalieColumn = 16;
                if (isAttacker) {
                    if (this._root.player_id === this._root.userInRightId)
                        goalieColumn = 0;
                }
                else if (this._root.player_id !== this._root.userInRightId) {
                    goalieColumn = 0;

                }

                let rotation = "90deg";
                let leftRow = 4;
                let rightRow = 6;
                if (this._root.player_id === this._root.userInRightId) {
                    leftRow = 6;
                    rightRow = 4;
                    rotation = "-90deg";
                }

                const myColor = this._root.myColor;
                let mainBoardEl = Board.getMainBoardElement();

                this._createTargetingTile(mainBoardEl, rotation, leftRow, goalieColumn, `url('${g_gamethemeurl}img/${myColor}TargetLeft.png')`);
                this._createTargetingTile(mainBoardEl, rotation, 5, goalieColumn, `url('${g_gamethemeurl}img/${myColor}TargetCenter.png')`);
                this._createTargetingTile(mainBoardEl, rotation, rightRow, goalieColumn, `url('${g_gamethemeurl}img/${myColor}TargetRight.png')`);
            },

            _createTargetingTile: function (parent, rotation, row, column, image) {
                const tile = document.createElement('div');
                tile.className = 'shootTargetTile';
                tile.style.backgroundImage = image;
                tile.style.setProperty('--rot', rotation);
                tile.addEventListener('click', e => this._onTileClicked(e, row, column))
                parent.appendChild(tile);
                this._root.placeOnObject(tile, Board.convertOffsetCoordToHexId(HexLib.createOffsetCoord(row, column)));
            },

            _onTileClicked: function (tile, row, column) {
                const selectedTile = HexLib.createOffsetCoord(row, column);

                Board.visualizePowerRequired(Board.getHexWithBall(), selectedTile, !this._isCurrentPlayerAttacker(), false);

                //(sha) Make this a function inside root.
                this._root.statusBar.removeActionButtons();
                this._root.statusBar.addActionButton('confirm', () => {
                    this._root.performServerAction("actTargetSelected", { hexColumn: column, hexRow: row });
                }, { color: 'primary' });
            },

            _isCurrentPlayerAttacker: function () {
                return parseInt(this._args.attacker) === this._root.player_id;
            },

            _cleanup: function () {
                Board.removeRequiredPowerVisualization();
                document.querySelectorAll('.shootTargetTile').forEach(div => div.remove());
            }
        });

        ShootTargetSelectionState.getName = function () {
            return 'shootTargetSelection';
        };

        return ShootTargetSelectionState;

    });