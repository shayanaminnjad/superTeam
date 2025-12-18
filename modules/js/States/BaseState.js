define(["dojo/_base/declare", "dojo/topic"], function (declare, topic) {

    const BaseState = declare("st.BaseState", null, {
        //Public Methods
        constructor: function (root, stateData) {
            this._root = root;
            this._stateData = stateData;
        },

        onEnter: function (args) {
            this._args = args;

            if (this._root.isCurrentPlayerActive()) {
                this._onActivePlayerEnter(args);
            }
            else {
                this._onInactivePlayerEnter(args);
            }
        },

        onLeave: function () {
            this.__removeAllHandles();

            if (this._root.isCurrentPlayerActive()) {
                this._onActivePlayerLeave();
            }
            else {
                this._onInactivePlayerLeave();
            }
        },

        onHexClicked: async function (evt) {
            try {
                this._selfOnHexClicked(evt);
            }
            catch (error) {
                console.error("click action failed, exception caught:", error);
            }
        },

        onHexEnter: function (evt) {
            try {
                this._selfOnHexEnter(evt);
            }
            catch (error) {
                console.error("hex enter failed, exception caught:", error);
            }
        },

        onHexLeave: function (evt) {
            try {
                this._selfOnHexLeave(evt);
            }
            catch (error) {
                console.error("hex leave failed, exception caught:", error);
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

        _selfOnHexClicked: async function (evt) {

        },

        _selfOnHexEnter: async function (evt) {

        },

        _selfOnHexLeave: async function (evt) {

        },

        //TODO: composition is a better suit here.
        subscribeEvent: function (eventName, handler) {
            this._eventHandles.push(topic.subscribe(eventName, handler));
        },

        //Private Methods
        __removeAllHandles: function () {
            for (const handle of this._eventHandles)
                handle.remove();
        },

        //Private Fields
        _root: null,
        _stateData: null,
        _args: null,
        _eventHandles: []
    });

    return BaseState;

});