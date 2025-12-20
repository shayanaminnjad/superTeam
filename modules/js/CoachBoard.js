define([
    "dojo/_base/declare",
    g_gamethemeurl + "modules/js/STUIConstants.js",
],
    function (declare, UIConstants) {
        const CoachBoard = declare("st.CoachBoard", null, {
            constructor: function (ownerId, ownerData, root, area) {
                this._ownerId = ownerId;
                this._ownerData = ownerData;
                this._root = root;
                this._area = area;

                this._init();
            },

            _init: function () {
                const coachBoard = document.createElement('div');
                coachBoard.id = `${this._ownerId}_coachBoard`;
                coachBoard.className = 'coachBoard';
                this._area.getElement().appendChild(coachBoard);

                const computedWidth = parseFloat(getComputedStyle(coachBoard).width);
                const computedHeight = parseFloat(getComputedStyle(coachBoard).height);

                const skillsGrid = document.createElement('div');
                skillsGrid.className = 'grid';
                skillsGrid.style.bottom = `${UIConstants.BOTTOM_TO_CUBE_PERCENT * computedHeight}px`;
                skillsGrid.style.left = `${UIConstants.LEFT_TO_CUBE_PERCENT * computedWidth}px`;
                skillsGrid.style.setProperty('--rows', 2);
                skillsGrid.style.setProperty('--columns', 5);
                skillsGrid.style.setProperty('--columns-gap', `${UIConstants.HORIZONTAL_MARGIN_PERCENT * computedWidth}px`);
                skillsGrid.style.setProperty('--rows-gap', `${UIConstants.VERTICAL_MARGIN_PERCENT * computedHeight}px`);
                coachBoard.appendChild(skillsGrid);

                for (let i = 0; i < 2; i++) {
                    for (let j = 0; j < 5; j++) {
                        {
                            const skillBox = document.createElement('div');
                            skillBox.id = `${i}_${j}_skillBox`;
                            skillBox.className = 'skillBox';
                            skillBox.style.width = `${UIConstants.CUBE_WIDTH_PERCENT * computedWidth}px`;
                            skillBox.style.height = `${UIConstants.CUBE_HEIGHT_PERCENT * computedHeight}px`;
                            skillsGrid.appendChild(skillBox);
                            this._skillBoxes.push(skillBox);
                        }
                    }
                }
            },

            _ownerId: 0,
            _ownerData: null,
            _root: null,
            _area: null,
            _skillBoxes: []
        });

        return CoachBoard;
    });