define([
    g_gamethemeurl + "modules/js/Piece.js"
],
function (Piece) {

    function getPieceManager() {
        return {
            //Public functions
            construct: function(animator) {
                this._animator = animator;
                this._piecesMap = new Map();
            },

            getPiece: function(pieceId) {
                return this._piecesMap.get(pieceId);
            },

            createNewPiece: function(pieceId, initializationData, color) {
                if (!this._piecesMap.has(pieceId)) {
                    let piece = this._createPiece(initializationData, color);
                    this._piecesMap.set(pieceId, piece);
                    return piece;
                } else {
                    throw new Error("Piece creation failed, piece already exists!");
                }
            },

            //Private functions
            _createPiece: function(initializationData, color) {
                let piece = Piece.getPiece(this._document);
                piece.construct(initializationData, color);
                return piece;
            },

            //Private members
            _piecesMap: null,
            _animator: null
        };
    }

    return {
        getPieceManager: getPieceManager
    };
});