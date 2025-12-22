define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/STUIConstants.js",
],
    function (declare, UIConstants) {

        const Skills = {
            Pass: 0,
            Shoot: 1,
            Dribble: 2,
            Tackle: 3,
            GK: 4,
            Count: 5
        };

        const CoachBoard = declare("st.CoachBoard", null, {
            constructor: function (ownerId, ownerData, root, area) {
                this._ownerId = ownerId;
                this._ownerData = ownerData;
                this._root = root;
                this._area = area;
                this._skillBoxes = [];

                this._init();
                this.makeAvailableBoxesSelectable();
            },

            placeCubeAnimated: async function (skill) {
                let slot = this._getEmptyCubeSlot(skill);
                slot.isFilled = true;

                let cube = this._createCube(this._area.getElement());
                this._root.placeOnObject(cube, this._root.getPlayerPanelElement(this._ownerId));
                const anim = this._root.slideToObject(cube, slot.element);

                await this._root.bgaPlayDojoAnimation(anim);
            },

            makeAllNonSelectable: function () {
                for (const skillBox of this._skillBoxes) {
                    skillBox.element.classList.remove('selectable');
                }
            },

            makeAvailableBoxesSelectable: function () {
                for (const skillBox of this._skillBoxes) {
                    skillBox.element.classList.toggle('selectable', !skillBox.isFilled);
                }
            },

            _getEmptyCubeSlot: function (skill) {
                if (this._skillBoxes[skill].isFilled === false)
                    return this._skillBoxes[skill];
                else if (this._skillBoxes[Skills.Count + skill].isFilled === false)
                    return this._skillBoxes[Skills.Count + skill];
                else
                    throw new Error("invalid skill passed " + skill);
            },

            _init: function () {
                const coachBoard = document.createElement('div');
                coachBoard.id = `${this._ownerId}_coachBoard`;
                coachBoard.className = 'coachBoard';
                this._area.getElement().appendChild(coachBoard);

                const computedWidth = Math.round(parseFloat(getComputedStyle(coachBoard).width));
                const computedHeight = Math.round(parseFloat(getComputedStyle(coachBoard).height));

                const skillsGrid = document.createElement('div');
                skillsGrid.className = 'grid';
                skillsGrid.style.bottom = `${Math.round(UIConstants.COACH_BOARD_BOTTOM_TO_CUBE_PERCENT * computedHeight)}px`;
                skillsGrid.style.left = `${Math.round(UIConstants.COACH_BOARD_LEFT_TO_CUBE_PERCENT * computedWidth)}px`;
                skillsGrid.style.setProperty('--rows', 2);
                skillsGrid.style.setProperty('--columns', 5);
                skillsGrid.style.setProperty('--columns-gap', `${Math.round(UIConstants.COACH_BOARD_HORIZONTAL_MARGIN_PERCENT * computedWidth)}px`);
                skillsGrid.style.setProperty('--rows-gap', `${Math.round(UIConstants.COACH_BOARD_VERTICAL_MARGIN_PERCENT * computedHeight)}px`);
                coachBoard.appendChild(skillsGrid);

                for (let i = 0; i < 2; i++) {
                    for (let j = 0; j < Skills.Count; j++) {
                        {
                            const skillBox = document.createElement('div');
                            skillBox.id = `${i}_${j}_skillBox`;
                            skillBox.className = 'skillBox';
                            skillBox.style.width = `${Math.round(UIConstants.CUBE_WIDTH_PERCENT * computedWidth)}px`;
                            skillBox.style.height = `${Math.round(UIConstants.CUBE_HEIGHT_PERCENT * computedHeight)}px`;
                            skillsGrid.appendChild(skillBox);
                            let skillSlot = this._createSkillSlot(skillBox);
                            this._skillBoxes.push(skillSlot);

                            skillBox.addEventListener('click', (evt) => this._onClick(evt, skillSlot));
                        }
                    }
                }

                this._cubeHeight = Math.round(UIConstants.CUBE_HEIGHT_PERCENT * computedHeight);
                this._cubeWidth = Math.round(UIConstants.CUBE_WIDTH_PERCENT * computedWidth);
            },

            _createSkillSlot: function (element) {
                return {
                    element: element,
                    isFilled: false
                };
            },

            _createCube: function (parentEl) {
                const cube = document.createElement('div');
                cube.className = 'cube';
                cube.style.width = `${this._cubeWidth}px`;
                cube.style.height = `${this._cubeHeight}px`;
                parentEl.appendChild(cube);

                return cube;
            },

            _onClick: function (evt, skillSlot) {
            },

            //Private Fields
            _cubeWidth: 0,
            _cubeHeight: 0,
            _ownerId: 0,
            _ownerData: null,
            _root: null,
            _area: null,
            _skillSlots: null
        });

        CoachBoard.Skills = Skills;
        return CoachBoard;
    });