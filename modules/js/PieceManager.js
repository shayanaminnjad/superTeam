define([
    g_gamethemeurl + "modules/js/Piece.js"
],
    function (Piece) {

        //TODO: it would be good if this also managed the ball!
        function getPieceManager() {
            return {
                //Public functions
                construct: function (animator) {
                    this._animator = animator;
                    this._piecesMap = new Map();
                },

                getPiece: function (pieceId) {
                    return this._piecesMap.get(pieceId);
                },

                createNewPiece: function (pieceId, initializationData, color) {
                    if (!this._piecesMap.has(pieceId)) {
                        let piece = new Piece(initializationData, color);
                        this._piecesMap.set(pieceId, piece);
                        return piece;
                    } else {
                        throw new Error("Piece creation failed, piece already exists!");
                    }
                },

                makePieceListSelectable: function (pieces) {
                    for (let piece of pieces) {
                        this.makePieceSelectable(piece);
                    }
                },

                addMoraleTokenToPieces: function (pieces) {
                    for (let piece of pieces) {
                        let el = this.getPiece(piece).getDOMElement();

                        el.insertAdjacentHTML('beforeend', `<div class="moraleIcon pieceAttachment"></div>`);
                    }
                },

                removeAllMoraleTokens: function () {
                    document.querySelectorAll('.moraleIcon.pieceAttachment').forEach(el => el.remove());
                },

                makePieceSelectable: function (pieceId) {
                    let piece = this.getPiece(pieceId);
                    piece.getDOMElement().classList.add('clickable')
                },

                makeAllPiecesNonSelectable: function () {
                    document.querySelectorAll('.piece').forEach((e) => e.classList.remove('clickable'));
                },

                animatePieceFromPlayerBoardToHex: async function (pieceId, destHexId) {
                    let piece = this.getPiece(pieceId);
                    let ownerPlayer = piece.getOwner();

                    this._animator.placeOnObject(piece.getDOMElement(), 'overall_player_board_' + ownerPlayer);

                    const anim = this._animator.slideToObject(piece.getDOMElement(), destHexId);

                    await this._animator.bgaPlayDojoAnimation(anim);
                },

                getAllPiecesOwnedBy: function (owner) {

                    let result = [];

                    for (const [key, value] of this._piecesMap) {

                        if (value.getOwner() === owner)
                            result.push(value.getPosition());
                    }

                    return result;
                },

                getPlayerGoalie: function (playerId) {

                    for (const [key, value] of this._piecesMap) {

                        if (value.getOwner() === playerId && value.isGoalie())
                            return value;
                    }
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