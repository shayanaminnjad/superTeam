define([
    g_gamethemeurl + "modules/js/STMathLib.js"
],
    function (STMathLib) {

        //------------------------------------------------------------------------------------

        function createCubicCoord(q, r, s) {
            q = parseInt(q, 10);
            r = parseInt(r, 10);
            s = parseInt(s, 10);

            if (q + r + s !== 0) {
                throw `Invalid cube coordinate (${q}, ${r}, ${s}): q + r + s must be 0, it is ${q + r + s}`;
            }

            return {
                q: q,
                r: r,
                s: s,

                toString: function () {
                    return '(' + q + ', ' + r + ', ' + s + ')';
                },

                equals: function (other) {
                    return this.q === other.q && this.r === other.r;
                },

                scale: function (factor) {
                    return createCubicCoord(q * factor, r * factor, s * factor);
                },

                toVector: function () {
                    return [q, r, s];
                },
            };
        }

        //------------------------------------------------------------------------------------

        const CUBIC_DIRECTIONS = [
            createCubicCoord(1, -1, 0),
            createCubicCoord(0, -1, 1),
            createCubicCoord(-1, 0, 1),
            createCubicCoord(-1, 1, 0),
            createCubicCoord(0, 1, -1),
            createCubicCoord(1, 0, -1)
        ];

        //------------------------------------------------------------------------------------

        const DIRECTION = Object.freeze({
            NORTHEAST: 0,
            NORTH: 1,
            NORTHWEST: 2,
            SOUTHWEST: 3,
            SOUTH: 4,
            SOUTHEAST: 5,
            COUNT: 6
        });

        //====================================================================================
        //------------------------------------------------------------------------------------
        //====================================================================================

        return {

            Direction: DIRECTION,

            //------------------------------------------------------------------------------------

            oddqToCubic: function (coord) {
                let q = coord.c;
                let r = coord.r - Math.floor((coord.c - (coord.c & 1)) / 2);

                return this.createCubicCoord(q, r, -q - r);
            },

            //------------------------------------------------------------------------------------

            cubicToOddq: function (coord) {
                let c = coord.q;
                let r = coord.r + Math.floor((coord.q - (coord.q & 1)) / 2);

                return this.createOffsetCoord(r, c);
            },

            //------------------------------------------------------------------------------------

            oddqToCartesian: function (coord, hexSize) {
                let result;

                const horizontalSpacing = hexSize * 1.5;
                const verticalSpacing = 1.73205 * hexSize; //sqrt(3) * hexSize

                let isEvenColumn = (coord.c & 1) === 0;

                if (isEvenColumn) {
                    result = STMathLib.createVector2D(coord.c * horizontalSpacing, coord.r * verticalSpacing);
                }
                else {
                    const oddOffset = verticalSpacing * 0.5;

                    result = STMathLib.createVector2D(coord.c * horizontalSpacing, oddOffset + coord.r * verticalSpacing);
                }

                return result;
            },

            //------------------------------------------------------------------------------------

            getHexsInPath: function (startPoint, endPoint, pathDistExtent, hexArray) {
                
                let result = [];

                let lineDir = STMathLib.vectorSubtract(endPoint, startPoint);

                for (let i = 0; i < hexArray.length; i++) {

                    let projSize = STMathLib.pointOntoLineProjectionSize(hexArray[i].cartesian, startPoint, lineDir);

                    if (projSize > 0 && projSize < 1) {

                        let distance = STMathLib.pointFromLineDistance(hexArray[i].cartesian, startPoint, lineDir);

                            if (distance < pathDistExtent) {
                            result.push(hexArray[i]);
                        }
                    }
                }

                return result;
            },

            //------------------------------------------------------------------------------------

            getNeighbour: function (cubicCoord, direction) {
                return this.cubicAdd(cubicCoord, direction);
            },

            //------------------------------------------------------------------------------------

            getNeighbours: function (cubicCoord, dist, offsetExtent) {
                let result = [];

                for (let currDist = 1; currDist <= dist; currDist++) {
                    let currentHex = this.getNeighbour(cubicCoord, this.getCubicDirection(DIRECTION.SOUTH).scale(currDist));

                    if (this.isInsideExtent(this.cubicToOddq(currentHex), offsetExtent)) {
                        result.push(currentHex);
                    }

                    for (let currDir = 0; currDir < DIRECTION.COUNT; currDir++) {
                        for (let i = 0; i < currDist; i++) {
                            currentHex = this.getNeighbour(currentHex, this.getCubicDirection(currDir));
                            if (this.isInsideExtent(this.cubicToOddq(currentHex), offsetExtent)) {
                                result.push(currentHex);
                            }
                        }
                    }
                }

                return result;
            },

            //------------------------------------------------------------------------------------

            cubicAdd: function (hex, vec) {
                return this.createCubicCoord(hex.q + vec.q, hex.r + vec.r, hex.s + vec.s);
            },

            cubicSubtract: function (a, b) {
                return this.createCubicCoord(a.q - b.q, a.r - b.r, a.s - b.s);
            },

            cubicDistance: function (startPoint, endPoint) {
                let diff = this.cubicSubtract(endPoint, startPoint);

                return (Math.abs(diff.q) + Math.abs(diff.r) + Math.abs(diff.s)) * 0.5;
            },

            //------------------------------------------------------------------------------------

            getCubicDirection: function (direction) {
                return CUBIC_DIRECTIONS[direction];
            },

            //------------------------------------------------------------------------------------

            isInsideExtent: function (offsetHex, offsetHexExtent) {
                let isEvenColumn = (offsetHex.c & 1) === 0;

                return offsetHex.c <= offsetHexExtent.cMax && offsetHex.c >= offsetHexExtent.cMin &&
                    offsetHex.r <= (isEvenColumn ? offsetHexExtent.rEvenMax : offsetHexExtent.rOddMax) &&
                    offsetHex.r >= (isEvenColumn ? offsetHexExtent.rEvenMin : offsetHexExtent.rOddMin);
            },

            //------------------------------------------------------------------------------------

            createCubicCoord: createCubicCoord,

            //------------------------------------------------------------------------------------

            createOffsetCoord: function (r, c) {
                r = parseInt(r);
                c = parseInt(c);

                return {
                    r: r,
                    c: c,

                    toString: function () {
                        return '(' + r + ', ' + c + ')';
                    },

                    equals: function (other) {
                        return this.r === other.r && this.c === other.c;
                    },

                    toVector: function () {
                        return [r, c, 0];
                    },
                };
            },

            //------------------------------------------------------------------------------------
            createOffsetExtent: function (cMin, cMax, rOddMin, rOddMax, rEvenMin, rEvenMax) {
                return {
                    cMin: cMin,
                    cMax: cMax,
                    rEvenMin: rEvenMin,
                    rOddMin: rOddMin,
                    rEvenMax: rEvenMax,
                    rOddMax: rOddMax,
                };
            },
            //------------------------------------------------------------------------------------
        };
    });