define([
    g_gamethemeurl + "modules/js/Piece.js",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js"
],
    function (Piece, HexLib, Board) {

        function getPieceManager() {
            return {
                //Public functions
                construct: function (root) {
                    this._root = root;
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

                    this._root.placeOnObject(piece.getDOMElement(), this._root.getPlayerPanelElement(ownerPlayer));

                    const anim = this._root.slideToObject(piece.getDOMElement(), destHexId);

                    return this._root.bgaPlayDojoAnimation(anim);
                },

                animateResetPieces: async function (goalieRow) {
                    let animations = [];

                    for (const piece of this._piecesMap.values()) {
                        this._resetPiece(piece, goalieRow, animations);
                    }

                    var animation_id = dojo.fx.combine(animations);
                    dojo.connect(animation_id, 'onEnd', () => {

                        for (let piece of this._piecesMap.values()) {
                            if (!piece.isGoalie()) {
                                piece.setPosition(null);
                                piece.setFreshness(true);
                            }
                        }
                    });

                    return this._root.bgaPlayDojoAnimation(animation_id);
                },

                animateResetPlayerPieces: function(playerId, goalieRow)
                {
                    let animations = [];

                    for (const piece of this._piecesMap.values()) {
                        if (piece.getOwner() !== playerId)
                            continue;

                        this._resetPiece(piece, goalieRow, animations);
                    }

                    var animation_id = dojo.fx.combine(animations);
                    dojo.connect(animation_id, 'onEnd', () => {

                        for (let piece of this._piecesMap.values()) {
                            if (!piece.isGoalie() && piece.getOwner() === playerId) {
                                piece.setPosition(null);
                                piece.setFreshness(true);
                            }
                        }
                    });

                    return this._root.bgaPlayDojoAnimation(animation_id);
                },

                refreshAllPieces: function (playerId) {
                    for (let piece of this._piecesMap.values()) {
                        if (piece.getOwner() === playerId) {
                            piece.setFreshness(true);
                        }
                    }
                },

                swapPeicesAndGetAnimation: function (lhsHex, rhsHex) {
                    let animations = [];

                    let lhsPiece = this._root.pieceManager.getPieceAtPosition(lhsHex);
                    let rhsPiece = this._root.pieceManager.getPieceAtPosition(rhsHex);

                    const LHS_DEST_HEX_ID = Board.convertOffsetCoordToHexId(rhsPiece.getPosition());
                    const RHS_DEST_HEX_ID = Board.convertOffsetCoordToHexId(lhsPiece.getPosition());

                    let rhsOldPos = rhsPiece.getPosition();
                    rhsPiece.setPosition(lhsPiece.getPosition());
                    lhsPiece.setPosition(rhsOldPos);

                    animations.push(this._root.slideToObject(lhsPiece.getDOMID(), LHS_DEST_HEX_ID));
                    animations.push(this._root.slideToObject(rhsPiece.getDOMID(), RHS_DEST_HEX_ID));

                    return animations;
                },

                getAllPiecesOwnedBy: function (owner) {

                    let result = [];

                    for (const [key, value] of this._piecesMap) {

                        if (value.getOwner() === owner)
                            result.push(value.getPosition());
                    }

                    return result;
                },

                getPieceAtPosition: function (pos) {
                    for (const [key, value] of this._piecesMap) {
                        if (value.getPosition().equals(pos)) {
                            return value;
                        }
                    }

                    return null;
                },

                getPlayerGoalie: function (playerId) {

                    for (const [key, value] of this._piecesMap) {

                        if (value.getOwner() === playerId && value.isGoalie())
                            return value;
                    }
                },

                //Snaps every on-board piece back onto the hex it already occupies.
                //Used after the board orientation changes, since the hexes themselves
                //moved but pieces still need to sit exactly on top of them.
                resnapAll: function () {
                    for (const piece of this._piecesMap.values()) {
                        const pos = piece.getPosition();

                        if (pos === null) {
                            continue;
                        }

                        this._root.placeOnObject(piece.getDOMElement(), Board.convertOffsetCoordToHexId(pos));
                    }
                },

                _resetPiece(piece, goalieRow, animations) {
                    if (!piece.isGoalie()) {
                        animations.push(this._root.slideToObject(piece.getDOMElement(), this._root.getPlayerPanelElement(piece.getOwner())));
                    } else {
                        let goaliePos = piece.getPosition();

                        if (goaliePos.r !== goalieRow) {
                            let goalieNewPos = HexLib.createOffsetCoord(goalieRow, goaliePos.c);
                            piece.setPosition(goalieNewPos);

                            const DEST_HEX_ID = Board.convertOffsetCoordToHexId(goalieNewPos);
                            animations.push(this._root.slideToObject(piece.getDOMID(), DEST_HEX_ID));
                        }
                    }
                },


                //Private members
                _piecesMap: null,
                _root: null
            };
        }

        return {
            getPieceManager: getPieceManager
        };
    });