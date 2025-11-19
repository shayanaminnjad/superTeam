define([
    g_gamethemeurl + "modules/js/Piece.js"
],
function (Piece) {

    //TODO: it would be good if this also managed the ball!
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

            makePieceListSelectable: function(pieces)
            {
                for(let piece of pieces)
                {
                    this.makePieceSelectable(piece);
                }
            },

            addMoraleTokenToPieces: function(pieces)
            {
                for(let piece of pieces)
                {
                    let el = this.getPiece(piece).getDOMElement();

                    el.insertAdjacentHTML('beforeend', `<div class="moraleIcon pieceAttachment"></div>`);
                }
            },

            removeAllMoraleTokens: function()
            {
                document.querySelectorAll('.moraleIcon.pieceAttachment').forEach(el => el.remove());
            },

            makePieceSelectable: function(pieceId)
            {
                console.log(pieceId);
                let piece = this.getPiece(pieceId);
                console.log(piece);
                piece.getDOMElement().classList.add('clickable')
            },

            makeAllPiecesNonSelectable: function()
            {
                document.querySelectorAll('.piece').forEach((e) => e.classList.remove('clickable'));
            },

            animatePieceFromPlayerBoardToHex: async function(pieceId, destHexId)
            {
                let piece = this.getPiece(pieceId);
                let ownerPlayer = piece.getOwner();

                this._animator.placeOnObject(piece.getDOMElement(), 'overall_player_board_' + ownerPlayer);

                const anim = this._animator.slideToObject(piece.getDOMElement(), destHexId);

                await this._animator.bgaPlayDojoAnimation(anim);
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