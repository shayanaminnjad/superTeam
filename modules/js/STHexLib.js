define([
    g_gamethemeurl + "modules/js/STMathLib.js"
],
    function (STMathLib) {

        //------------------------------------------------------------------------------------

        function createCubicCoord(q, r, s, safe = true) {
            q = parseFloat(q, 10);
            r = parseFloat(r, 10);
            s = parseFloat(s, 10);

            if (Math.abs(q + r + s) > 5 * Number.EPSILON && safe) {
                throw `Invalid cube coordinate (${q}, ${r}, ${s}): q + r + s must be 0, it is ${q + r + s}`;
            }

            return {
                q: q,
                r: r,
                s: s,

                toString: function () {
                    return '(' + this.q + ', ' + this.r + ', ' + this.s + ')';
                },

                equals: function (other) {
                    return this.q === other.q && this.r === other.r;
                },

                scale: function (factor) {
                    return createCubicCoord(this.q * factor, this.r * factor, this.s * factor);
                },

                toVector: function () {
                    return [this.q, this.r, this.s];
                },

                subtract: function (other) {
                    return createCubicCoord(this.q - other.q, this.r - other.r, this.s - other.s);
                }
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

            cubicRound: function (cubicCoordinate) {
                let qRound = Math.round(cubicCoordinate.q);
                let rRound = Math.round(cubicCoordinate.r);
                let sRound = Math.round(cubicCoordinate.s);

                let qDiff = Math.abs(qRound - cubicCoordinate.q);
                let rDiff = Math.abs(rRound - cubicCoordinate.r);
                let sDiff = Math.abs(sRound - cubicCoordinate.s);

                if (qDiff > rDiff && qDiff > sDiff) {
                    qRound = -rRound - sRound;
                }
                else if (rDiff > sDiff) {
                    rRound = -qRound - sRound;
                }
                else {
                    sRound = -qRound - rRound;
                }

                return this.createCubicCoord(qRound, rRound, sRound);
            },

            //------------------------------------------------------------------------------------

            cubicLerp: function (a, b, t) {
                return this.createCubicCoord(
                    STMathLib.lerp(a.q, b.q, t),
                    STMathLib.lerp(a.r, b.r, t),
                    STMathLib.lerp(a.s, b.s, t),
                    false);
            },

            //------------------------------------------------------------------------------------

            getHexsInPath: function (startPoint, endPoint, pathDistExtent, hexArray) {

                let result = [];

                const startAsCartesian = startPoint.toCartesian();
                const endAsCartesian = endPoint.toCartesian();

                let lineDir = STMathLib.vectorSubtract(endAsCartesian, startAsCartesian);

                for (let i = 0; i < hexArray.length; i++) {

                    let projSize = STMathLib.pointOntoLineProjectionSize(hexArray[i].toCartesian(), startAsCartesian, lineDir);

                    //TODO: this is not correct, since proj size of 0.1 becomes much larger in when the distance is bigger!
                    //instead of proj size, I can determine perpendicular cubic components, and if a piece is on that line, I can rule it out!
                    //what remains is the distance!

                    if (projSize > 0.1 && projSize < 0.9) {

                        let distance = STMathLib.pointFromLineDistance(hexArray[i].toCartesian(), startAsCartesian, lineDir);

                        if (distance < pathDistExtent) {
                            result.push(hexArray[i]);
                        }
                    }
                    else if (hexArray[i].equals(endPoint))
                    {
                        result.push(hexArray[i]);
                    }
                }

                return result;
            },

            //------------------------------------------------------------------------------------

            getHexesInLine: function (start, end) {
                let result = [];

                if (start.equals(end)) {
                    result.push(end);

                    return result;
                }

                let startCubic = this.oddqToCubic(start);
                let endCubic = this.oddqToCubic(end);

                let distance = this.getOffsetDistance(start, end);

                startCubic = this.cubicAdd(startCubic, this.createCubicCoord(1e-6, 2e-6, -3e-6, false));

                for (let i = 1; i <= distance; i++) {
                    let cubicCoordinateOnLine = this.cubicRound(this.cubicLerp(startCubic, endCubic, 1 / distance * i));
                    result.push(this.cubicToOddq(cubicCoordinateOnLine));
                }

                return result;
            },

            //------------------------------------------------------------------------------------

            getOffsetDistance: function (start, end) {
                let startCubic = this.oddqToCubic(start);
                let endCubic = this.oddqToCubic(end);

                let diff = endCubic.subtract(startCubic);

                return ((Math.abs(diff.q) + Math.abs(diff.r) + Math.abs(diff.s))) * 0.5;
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

                    toCartesian: function (hexSize = 1) {
                        const horizontalSpacing = 1.5 * hexSize;
                        const verticalSpacing = 1.73205 * hexSize;

                        const isEvenColumn = (this.c & 1) == 0;

                        if (isEvenColumn) {
                            return STMathLib.createVector2D(this.c * horizontalSpacing, this.r * verticalSpacing);
                        }
                        else { 
                            const oddOffset = verticalSpacing * 0.5;
                            return STMathLib.createVector2D(this.c * horizontalSpacing, oddOffset + this.r * verticalSpacing);
                        }
                    }
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