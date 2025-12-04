define(["dojo/_base/declare"], function (declare) {

    const BaseState = declare("st.BaseState", null, {
        //Public Methods
        constructor: function (root, stateData) {
            this._root = root;
            this._stateData = stateData;
        },

        onEnter: function (args) {
            if (this._root.isCurrentPlayerActive()) {
                this._onActivePlayerEnter(args);
            }
            else {
                this._onInactivePlayerEnter(args);
            }
        },

        onLeave: function () {
            if (this._root.isCurrentPlayerActive()) {
                this._onActivePlayerLeave();
            }
            else {
                this._onInactivePlayerLeave();
            }
        },

        getName: function () {
            throw new Error("getName() not implemented!");
        },

        //Protected methods
        _onActivePlayerEnter(args) {
            throw new Error("_onActivePlayerEnter() not implemented!");
        },

        _onInactivePlayerEnter(args) {
            throw new Error("_onInactivePlayerEnter() not implemented!");
        },

        _onActivePlayerLeave() {
            throw new Error("_onActivePlayerLeave() not implemented!");
        },

        _onInactivePlayerLeave() {
            throw new Error("_onInactivePlayerLeave() not implemented!");
        },

        //Private Fields
        _root: null,
        _stateData: null
    });

    return BaseState;

});