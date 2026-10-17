define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/STUIHelpers.js",
],
    function (declare, STUIHelpers) {

        const CARDS_PER_BOARD = 3;

        const DraftingUI = declare("st.DraftingUI", null, {
            // offers: { captains: [id, ...], coaches: [id, ...] }, onSelectionChanged: function()
            constructor: function (root, offers, onSelectionChanged) {
                this._root = root;
                this._onSelectionChanged = onSelectionChanged;
                this._selection = { captain: null, coach: null };
                this._optionEls = { captain: new Map(), coach: new Map() };
                this._tooltips = [];

                this._panelEl = document.createElement('div');
                this._panelEl.id = 'draftingPanel';
                this._panelEl.className = 'playerArea draftingPanel';

                this._createSection(_('Choose a captain power'), 'captain', offers.captains);
                this._createSection(_('Choose a coach board'), 'coach', offers.coaches);

                this._root.getGameAreaElement().prepend(this._panelEl);

                for (const tooltip of this._tooltips)
                    this._root.addTooltipHtml(tooltip.elId, tooltip.html);
            },

            //Public Methods
            isComplete: function () {
                return this._selection.captain !== null && this._selection.coach !== null;
            },

            getCaptainId: function () {
                return this._selection.captain;
            },

            getCoachId: function () {
                return this._selection.coach;
            },

            lock: function () {
                this._panelEl.classList.add('locked');
            },

            destroy: function () {
                this._panelEl.remove();
            },

            //Private Methods
            _createSection: function (title, kind, ids) {
                const sectionEl = document.createElement('div');
                sectionEl.className = 'draftingSection';
                this._panelEl.appendChild(sectionEl);

                const titleEl = document.createElement('div');
                titleEl.className = 'draftingTitle';
                titleEl.textContent = title;
                sectionEl.appendChild(titleEl);

                const optionsEl = document.createElement('div');
                optionsEl.className = 'draftingOptions';
                sectionEl.appendChild(optionsEl);

                for (const id of ids.map(Number))
                    optionsEl.appendChild(this._createOption(kind, id));
            },

            _createOption: function (kind, id) {
                const optionEl = document.createElement('div');
                optionEl.id = `draft_${kind}_${id}`;
                optionEl.className = 'draftingOption';
                optionEl.addEventListener('click', () => this._select(kind, id));
                this._optionEls[kind].set(id, optionEl);

                optionEl.appendChild(kind === 'captain' ? this._createCaptain(id) : this._createCoachBoard(id));

                const cardsEl = document.createElement('div');
                cardsEl.className = 'draftingCards';
                optionEl.appendChild(cardsEl);

                for (const cardType of this._getCardTypes(kind, id))
                    cardsEl.appendChild(this._createCard(`${optionEl.id}_${cardType}`, cardType));

                return optionEl;
            },

            _createCaptain: function (id) {
                const el = document.createElement('div');
                el.id = `draft_captain_${id}_power`;
                el.className = 'captainPower draftingBoard';
                el.style.backgroundPosition = `${STUIHelpers.captainAtlasPercent(id)}% 0`;

                const definition = (this._root.captainDefinitions || {})[id];
                if (definition)
                    this._queueTooltip(el.id, STUIHelpers.buildCaptainTooltipHtml(definition, id));

                return el;
            },

            _createCoachBoard: function (id) {
                const el = document.createElement('div');
                el.id = `draft_coach_${id}_board`;
                el.className = 'coachBoard draftingBoard';
                el.style.backgroundPosition = STUIHelpers.coachBoardAtlasPosition(id);

                const definition = (this._root.coachDefinitions || {})[id];
                if (definition)
                    this._queueTooltip(el.id, STUIHelpers.buildCoachTooltipHtml(definition, id));

                return el;
            },

            _createCard: function (elId, cardType) {
                const el = document.createElement('div');
                el.id = elId;
                el.className = 'st-card-front draftingCard';

                const [x, y] = this._root.cardsManager.getCardAtlasPosition(cardType);
                el.style.backgroundPosition = `${x} ${y}`;

                this._queueTooltip(el.id, this._root.cardsManager.getCardTooltipHtml(cardType));

                return el;
            },

            // BGA tooltips need the element in the document, so they are added after the panel is attached
            _queueTooltip: function (elId, html) {
                this._tooltips.push({ elId, html });
            },

            _getCardTypes: function (kind, id) {
                const types = [];

                if (kind === 'coach')
                    types.push(`Coach_${id}`);

                for (let i = 0; i < CARDS_PER_BOARD; i++)
                    types.push(kind === 'captain' ? `Captain_${id}_${i}` : `Team_${id}_${i}`);

                return types;
            },

            _select: function (kind, id) {
                this._selection[kind] = id;

                for (const [optionId, optionEl] of this._optionEls[kind])
                    optionEl.classList.toggle('selected', optionId === id);

                this._onSelectionChanged();
            },

            //Private Fields
            _root: null,
            _panelEl: null,
            _selection: null,
            _optionEls: null,
            _tooltips: null,
            _onSelectionChanged: null
        });

        return DraftingUI;
    });
