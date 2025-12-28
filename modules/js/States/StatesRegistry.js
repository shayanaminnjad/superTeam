define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/PlayerPieceSetupState.js",
    g_gamethemeurl + "modules/js/States/PlayerTurnState.js",
    g_gamethemeurl + "modules/js/States/PieceSelectionState.js",
    g_gamethemeurl + "modules/js/States/PassDestinationSelection.js",
    g_gamethemeurl + "modules/js/States/MoveActionState.js",
    g_gamethemeurl + "modules/js/States/MoraleAssignmentState.js",
    g_gamethemeurl + "modules/js/States/NullState.js",
    g_gamethemeurl + "modules/js/States/ShootTargetSelectionState.js",
    g_gamethemeurl + "modules/js/States/DribbleTargetSelection.js",
    g_gamethemeurl + "modules/js/States/PerformDribble.js",
    g_gamethemeurl + "modules/js/States/TacklePerformerSelection.js",
    g_gamethemeurl + "modules/js/States/PerformTackle.js",
    g_gamethemeurl + "modules/js/States/TeamAttributeAssignment.js",
    g_gamethemeurl + "modules/js/States/TeamAttributeReveal.js",
    g_gamethemeurl + "modules/js/States/BeforePlayerTurn.js"
], function (
    declare,
    PlayerPieceSetupState,
    PlayerTurnState,
    PieceSelectionState,
    PassDestinationSelection,
    MoveActionState,
    MoraleAssignmentState,
    NullState,
    ShootTargetSelectionState,
    DribbleTargetSelection,
    PerformDribble,
    TacklePerformerSelection,
    PerformTackle,
    TeamAttributeAssignment,
    TeamAttributeReveal,
    BeforePlayerTurn
) {

    const StateRegistry = declare("st.StateRegistry", null, {

        //Public Methods
        constructor: function () {
            this._registry = new Map();

            this.registerState(PlayerPieceSetupState);
            this.registerState(PlayerTurnState);
            this.registerState(PieceSelectionState);
            this.registerState(PassDestinationSelection);
            this.registerState(MoveActionState);
            this.registerState(MoraleAssignmentState);
            this.registerState(ShootTargetSelectionState);
            this.registerState(DribbleTargetSelection);
            this.registerState(PerformDribble);
            this.registerState(TacklePerformerSelection);
            this.registerState(PerformTackle);
            this.registerState(TeamAttributeAssignment);
            this.registerState(TeamAttributeReveal);
            this.registerState(BeforePlayerTurn);
        },

        getState: function (name, root, stateData) {
            let res = this._registry.get(name);

            if (res === undefined)
                return new NullState(root, stateData);
            else
                return new res(root, stateData);
        },

        registerState: function (state) {
            this._registry.set(state.getName(), state);
        },

        //Private Fields
        _registry: null,
    });

    return StateRegistry;

});