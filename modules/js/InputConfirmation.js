define([], function () {

    const STORAGE_KEY = 'superteam_mobile_mode';
    const CONFIRM_BUTTON_ID = 'inputConfirmButton';

    // Mobile mode: a tap on a hex or piece only marks it, and the server call waits for a Confirm button.
    // The choice lives in localStorage, so it is per device. The default follows the main input of the device.
    return {
        construct: function (root) {
            this._root = root;
        },

        // Until the player picks a value, a touch screen as the main input turns the mode on.
        isEnabled: function () {
            if (this._choice !== null)
                return this._choice;

            try {
                const stored = window.localStorage.getItem(STORAGE_KEY);

                if (stored !== null)
                    return stored === '1';
            }
            catch (e) {
            }

            return window.matchMedia('(pointer: coarse)').matches;
        },

        setEnabled: function (enabled) {
            // kept in memory too, in case storage is blocked
            this._choice = enabled;

            try {
                window.localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
            }
            catch (e) {
                console.warn('mobile mode could not be saved on this device', e);
            }

            if (!enabled)
                this.clear();
        },

        // Returns true when the input was held back, then onConfirm runs after the player confirms.
        holdIfNeeded: function (element, onConfirm) {
            if (!this.isEnabled() || !this._isSelectable(element))
                return false;

            this._pendingElement?.classList.remove('pendingConfirm');
            this._pendingElement = element;
            this._pendingAction = onConfirm;
            element.classList.add('pendingConfirm');

            if (!document.getElementById(CONFIRM_BUTTON_ID))
                this._root.statusBar.addActionButton(_('Confirm'), () => this._confirm(), { id: CONFIRM_BUTTON_ID, color: 'primary' });

            this._root.currentState.onInputHeld(element);

            return true;
        },

        clear: function () {
            this._pendingElement?.classList.remove('pendingConfirm');
            this._pendingElement = null;
            this._pendingAction = null;

            document.getElementById(CONFIRM_BUTTON_ID)?.remove();

            this._root.currentState?.onInputReleased();
        },

        _confirm: function () {
            const action = this._pendingAction;
            this.clear();

            if (action && !this._root.serverCallInProgress)
                action();
        },

        _isSelectable: function (element) {
            const classes = element.classList;

            return !classes.contains('inputBlocked') &&
                (classes.contains('selectable') || classes.contains('availableToPlacePiece') || classes.contains('clickable'));
        },

        _root: null,
        _choice: null,
        _pendingElement: null,
        _pendingAction: null,
    };
});
