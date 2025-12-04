define(["dojo/_base/declare",
  g_gamethemeurl + "modules/js/States/BaseState.js",
  g_gamethemeurl + "modules/js/BoardPresentation.js",],
  function (declare, BaseState, Board) {

    const PieceSelectionState = declare("st.PieceSelectionState", BaseState, {

      constructor: function (root, stateData) {
      },

      _onActivePlayerEnter(args) {
        this._root.pieceManager.makePieceListSelectable(args.selectablePieces);
        this._root.pieceManager.makePieceListSelectable(args.selectableExhaustedPieces);
        this._root.pieceManager.addMoraleTokenToPieces(args.selectableExhaustedPieces);
        this._root.highlightPiecesHexes(args.selectablePieces.concat(args.selectableExhaustedPieces));
      },

      _onInactivePlayerEnter(args) {
      },

      _onActivePlayerLeave() {
        this._root.pieceManager.makeAllPiecesNonSelectable();
        this._root.pieceManager.removeAllMoraleTokens();
        Board.removeHexHighlights();
      },

      _onInactivePlayerLeave() {
      },

      getName: function () {
        return this.constructor.getName();
      }

    });

    PieceSelectionState.getName = function () {
      return 'pieceSelection';
    };

    return PieceSelectionState;

  });