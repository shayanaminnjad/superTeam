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

            for (let pieceToHex of piecesToHexes) {
                let piece = this._pieceManager.getPiece(pieceToHex["pieceId"]);
                animations.push(this.MovePieceWithAnimation(piece, pieceToHex["hex"], 0, 0));
            }

            for (let freshPiece of freshPieces) {
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
                animations.push(
                    PlayerPanelManager.reducePlayerMoraleAnimated(
                        this._root.getActivePlayerId(),
                        1,
                        piece.getDOMID()
                    )
                );
            }

            let movementAnimations = [];

            const DEST_HEX_ID =
                BoardPresentation.convertOffsetCoordToHexId(destHex);

            movementAnimations.push(
                this._root.slideToObject(
                    piece.getDOMID(),
                    DEST_HEX_ID
                )
            );

            if (ballMoved) {
                BoardPresentation.setHexWithBall(destHex);
                movementAnimations.push(
                    BoardPresentation.getMoveBallAnimation(DEST_HEX_ID)
                );
            }

            animations.push(dojo.fx.combine(movementAnimations));

            return dojo.fx.chain(animations);
        },

        //------------------------------------------------------------------------------------

        ShowAssignedMoraleAnimated: function (notifData) {
            let animations = [];

            Object.entries(notifData).forEach(([playerId, data]) => {
                const pieceId = data[0];
                const moraleAssigned = data[1];

                if (moraleAssigned <= 0)
                    return;

                const piece = this._pieceManager.getPiece(pieceId);

                if (!piece)
                    return;

                const sourceElement =
                    this._root.getPlayerPanelElement(playerId);

                const moraleLine =
                    this._createMoraleLine(
                        piece,
                        moraleAssigned
                    );

                for (let i = 0; i < moraleAssigned; i++) {
                    const target =
                        moraleLine.children[i];

                    target.style.visibility = 'hidden';

                    const flyingMorale =
                        document.createElement('div');

                    flyingMorale.classList.add('floatingMorale');

                    this._root.getGameAreaElement().appendChild(flyingMorale);
                    this._root.placeOnObject(flyingMorale, sourceElement);

                    const animation =
                        this._root.slideToObject(
                            flyingMorale,
                            target,
                            300,
                            i * 50
                        );

                    dojo.connect(animation, 'onEnd', () => {
                        dojo.destroy(flyingMorale);
                        target.style.visibility = 'visible';
                    });

                    animations.push(animation);
                }
            });

            return dojo.fx.combine(animations);
        },

        //------------------------------------------------------------------------------------

        ShowAssignedMorale: function (notifData) {
            Object.entries(notifData).forEach(([playerId, data]) => {
                const pieceId = data[0];
                const moraleAssigned = data[1];

                if (moraleAssigned <= 0)
                    return;

                const piece = this._pieceManager.getPiece(pieceId);

                if (!piece)
                    return;

                this._createMoraleLine(
                    piece,
                    moraleAssigned
                );
            });
        },

        //------------------------------------------------------------------------------------

        RemoveAssignedMorale: function () {
            document
                .querySelectorAll('.pieceMoraleLine')
                .forEach(el => el.parentElement.remove());
        },

        //------------------------------------------------------------------------------------

        _createMoraleLine: function (piece, moraleAmount) {
            const moraleLine =
                document.createElement('div');

            moraleLine.classList.add('pieceMoraleLine');

            for (let i = 0; i < moraleAmount; i++) {
                const moraleIcon =
                    document.createElement('div');

                moraleIcon.classList.add(
                    'resourceIcon',
                    'moraleIcon'
                );

                moraleLine.appendChild(moraleIcon);
            }

            this._pieceManager.attachToPiece(piece.getId(), moraleLine);

            return moraleLine;
        },

        //------------------------------------------------------------------------------------

        SwapAnimated: function (lhsHex, rhsHex, ballNewHex) {
            let animations =
                this._pieceManager.swapPeicesAndGetAnimation(
                    lhsHex,
                    rhsHex
                );

            if (!ballNewHex.equals(BoardPresentation.getHexWithBall())) {
                animations.push(
                    this._root.slideToObject(
                        'ballParent',
                        BoardPresentation.convertOffsetCoordToHexId(ballNewHex)
                    )
                );

                BoardPresentation.setHexWithBall(ballNewHex);
            }

            return this._root.bgaPlayDojoAnimation(
                dojo.fx.combine(animations)
            );
        },

        //------------------------------------------------------------------------------------

        //Private Members
        _root: null,
        _pieceManager: null
    };
});