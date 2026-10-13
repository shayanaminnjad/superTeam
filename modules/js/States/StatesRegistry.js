define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/States/PlayerPieceSetupState.js",
    g_gamethemeurl + "modules/js/States/PlayerBasicTurnState.js",
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
    g_gamethemeurl + "modules/js/States/BeforePlayerTurn.js",
    g_gamethemeurl + "modules/js/States/CardSelection.js",
    g_gamethemeurl + "modules/js/States/TacticRewardResolveState.js",
    g_gamethemeurl + "modules/js/States/PieceToSwapSelection.js",
    g_gamethemeurl + "modules/js/States/CaptainCardPerformerSelection.js",
    g_gamethemeurl + "modules/js/States/BypassTargetSelection.js",
    g_gamethemeurl + "modules/js/States/DiceRerollState.js",
    g_gamethemeurl + "modules/js/States/PushTargetSelectionState.js",
    g_gamethemeurl + "modules/js/States/PushDestinationSelectionState.js",
    g_gamethemeurl + "modules/js/States/PushingPieceSelection.js",
], function (
    declare,
    PlayerPieceSetupState,
    PlayerBasicTurnState,
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
    BeforePlayerTurn,
    CardSelection,
    TacticRewardResolveState,
    PieceToSwapSelection,
    CaptainCardPerformerSelectionState,
    BypassTargetSelection,
    DiceRerollState,
    PushTargetSelectionState,
    PushDestinationSelectionState,
    PushingPieceSelection
) {

    const StateRegistry = declare("st.StateRegistry", null, {

        //Public Methods
        constructor: function () {
            this._registry = new Map();

            this.registerState(PlayerPieceSetupState);
            this.registerState(PlayerBasicTurnState);
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
            this.registerState(CardSelection);
            this.registerState(TacticRewardResolveState);
            this.registerState(PieceToSwapSelection)
            this.registerState(CaptainCardPerformerSelectionState);
            this.registerState(BypassTargetSelection);
            this.registerState(DiceRerollState);
            this.registerState(PushTargetSelectionState);
            this.registerState(PushDestinationSelectionState);
            this.registerState(PushingPieceSelection);
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