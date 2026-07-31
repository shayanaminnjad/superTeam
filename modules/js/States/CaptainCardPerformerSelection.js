define(["dojo/_base/declare",
        g_gamethemeurl + "modules/js/States/BaseState.js",
        g_gamethemeurl + "modules/js/BoardPresentation.js",],
    function (declare, BaseState, Board) {

        const CaptainCardPerformerSelectionState = declare("st.CaptainCardPerformerSelectionState", BaseState, {

            constructor: function (root, stateData) {
                console.log("Captain card selections state!")
            },

            _onActivePlayerEnter(args) {
                this._root.pieceManager.makePieceListSelectable(args.piecesToSelect);
                this._root.highlightPiecesHexes(args.piecesToSelect);
            },

            _onInactivePlayerEnter(args) {
            },

            _onActivePlayerLeave() {
                this._root.pieceManager.makeAllPiecesNonSelectable();
                Board.removeHexHighlights();
            },

            _onInactivePlayerLeave() {
            },

            getName: function () {
                return this.constructor.getName();
            }

        });

        CaptainCardPerformerSelectionState.getName = function () {
            return 'captainCardPerformerSelection';
        };

        return CaptainCardPerformerSelectionState;

    });