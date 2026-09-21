define([
    "dojo", "dojo/_base/declare", "dojo/topic",
    g_gamethemeurl + "modules/js/STHexLib.js",
    g_gamethemeurl + "modules/js/STUIConstants.js",
    g_gamethemeurl + "modules/js/BoardPresentation.js",
    g_gamethemeurl + "modules/js/PlayerPanelManager.js",
], function (dojo, declare, topic, HexLib, UIConstants, BoardPresentation, PlayerPanelManager) {

    return {
        //Public Functions
        construct: function (root, pieceManager) {
            this._root = root;
            this._pieceManager = pieceManager;
        },

        //------------------------------------------------------------------------------------

        UndoMoveAnimated: function (piecesToHexes, freshPieces) {
            let animations = [];
            console.log(this._pieceManager);
            for (let pieceToHex of piecesToHexes) {
                let piece = this._pieceManager.getPiece(pieceToHex["pieceId"]);
                animations.push(this.MovePieceWithAnimation(piece, pieceToHex["hex"], 0, 0));
            }

            for(let freshPiece of freshPieces)
            {
                let piece = this._pieceManager.getPiece(freshPiece);
                piece.setFreshness(true);
            }
            return dojo.fx.combine(animations);
        },

        //------------------------------------------------------------------------------------

        MovePieceWithAnimation: function (piece, destHex, spentMorale, ballMoved) {
            piece.setPosition(destHex);

            let animations = [];
            if (spentMorale) {
                animations.push(PlayerPanelManager.reducePlayerMoraleAnimated(this._root.getActivePlayerId(), 1, piece.getDOMID()));
            }

            let movementAnimations = [];

            const DEST_HEX_ID = BoardPresentation.convertOffsetCoordToHexId(destHex);
            movementAnimations.push(this._root.slideToObject(piece.getDOMID(), DEST_HEX_ID));

            if (ballMoved) {
                BoardPresentation.setHexWithBall(destHex);
                movementAnimations.push(BoardPresentation.getMoveBallAnimation(DEST_HEX_ID));
            }

            animations.push(dojo.fx.combine(movementAnimations));

            return dojo.fx.chain(animations);
        },

        //------------------------------------------------------------------------------------

        SwapAnimated: function(lhsHex, rhsHex, ballNewHex)
        {
            let animations = this._pieceManager.swapPeicesAndGetAnimation(lhsHex, rhsHex);

            if (!ballNewHex.equals(BoardPresentation.getHexWithBall()))
            {
                animations.push(this._root.slideToObject('ballParent', BoardPresentation.convertOffsetCoordToHexId(ballNewHex)));
                BoardPresentation.setHexWithBall(ballNewHex);
            }

            return this._root.bgaPlayDojoAnimation(dojo.fx.combine(animations));
        },
        //------------------------------------------------------------------------------------

        //Private Members
        _root: null,
        _pieceManager: null
    };
});