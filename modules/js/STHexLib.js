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

            getHexsInPath: function (startPoint, endPoint, halfLineThickness, hexArray, hexInfo) {

                let result = [];

                const startAsCartesian = startPoint.toCartesian(hexInfo);
                const endAsCartesian = endPoint.toCartesian(hexInfo);

                const lineDir = STMathLib.vectorSubtract(endAsCartesian, startAsCartesian);

                for (let i = 0; i < hexArray.length; i++) {

                    const currHexAsCartesian = hexArray[i].toCartesian(hexInfo);
                    const projSize = STMathLib.pointOntoLineProjectionSize(currHexAsCartesian, startAsCartesian, lineDir);

                    if (projSize >= 0 && projSize <= 1) {
                        const pointOnLineProjection = STMathLib.vectorAdd(startAsCartesian, STMathLib.vectorScale(lineDir, projSize));
                        const pointToCenterVec = STMathLib.vectorSubtract(currHexAsCartesian, pointOnLineProjection);
                        const moveTowardCenterMagnitude = Math.min(STMathLib.vectorSize(pointToCenterVec), halfLineThickness);
                        const hexClosestPoint = STMathLib.vectorAdd(pointOnLineProjection, STMathLib.vectorScale(STMathLib.normalize(pointToCenterVec), moveTowardCenterMagnitude));
                        const distanceToclosestPoint = STMathLib.vectorSize(STMathLib.vectorSubtract(currHexAsCartesian, hexClosestPoint));

                        if (distanceToclosestPoint <= hexInfo.inRadius) {
                            result.push(hexArray[i]);
                        }
                        else {
                            const hexRight = STMathLib.createVector2D(currHexAsCartesian.x + hexInfo.size, currHexAsCartesian.y);
                            const hexLeft = STMathLib.createVector2D(currHexAsCartesian.x - hexInfo.size, currHexAsCartesian.y);
                            const hexTop = currHexAsCartesian.y + hexInfo.inRadius;
                            const hexBottom = currHexAsCartesian.y - hexInfo.inRadius;

                            if (hexClosestPoint.x < (hexRight.x) && hexClosestPoint.x > (hexLeft.x)
                                && hexClosestPoint.y < (hexTop) && hexClosestPoint.y > (hexBottom)) {

                                const hexTopRight = STMathLib.createVector2D(currHexAsCartesian.x + hexInfo.size * 0.5, hexTop);
                                const hexTopLeft = STMathLib.createVector2D(currHexAsCartesian.x - hexInfo.size * 0.5, hexTop);
                                const hexBottomRight = STMathLib.createVector2D(currHexAsCartesian.x + hexInfo.size * 0.5, hexBottom);
                                const hexBottomLeft = STMathLib.createVector2D(currHexAsCartesian.x - hexInfo.size * 0.5, hexBottom);

                                const rotation = Math.PI * 0.5; //90 degrees in radian

                                const rightToTopProj = STMathLib.pointOntoLineProjectionSize(hexClosestPoint, hexRight, STMathLib.rotateVector(STMathLib.vectorSubtract(hexTopRight, hexRight), rotation));
                                const bottomToRightProj = STMathLib.pointOntoLineProjectionSize(hexClosestPoint, hexRight, STMathLib.rotateVector(STMathLib.vectorSubtract(hexRight, hexBottomRight), rotation));
                                const leftToBottomProj = STMathLib.pointOntoLineProjectionSize(hexClosestPoint, hexLeft, STMathLib.rotateVector(STMathLib.vectorSubtract(hexLeft, hexBottomLeft), rotation));
                                const topToLeftProj = STMathLib.pointOntoLineProjectionSize(hexClosestPoint, hexLeft, STMathLib.rotateVector(STMathLib.vectorSubtract(hexTopLeft, hexLeft), rotation));

                                if(rightToTopProj > 0 && bottomToRightProj >0 && leftToBottomProj > 0 && topToLeftProj >0)
                                {
                                    result.push(hexArray[i]);
                                }
                            }
                        }

                    }
                    else if (hexArray[i].equals(endPoint)) {
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

                    //I know this is wierd, since inRadius should be calculated from in radius, however the hex I am working with
                    //Is not perfect regular hex, this helps me to have exact center points.
                    toCartesian: function (hexInfo) {
                        const horizontalSpacing = 1.5 * hexInfo.size;
                        const verticalSpacing = 2 * hexInfo.inRadius;

                        const isEvenColumn = (this.c & 1) == 0;

                        if (isEvenColumn) {
                            return STMathLib.createVector2D(this.c * (horizontalSpacing + hexInfo.horizontalGap), this.r * (verticalSpacing + hexInfo.verticalGap));
                        }
                        else {
                            const oddOffset = verticalSpacing * 0.5;
                            return STMathLib.createVector2D(this.c * (horizontalSpacing + hexInfo.horizontalGap), oddOffset + this.r * (verticalSpacing + hexInfo.verticalGap));
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

            createHexInfo: function (size, inRadius, horizontalGap, verticalGap) {
                return {
                    size: size,
                    inRadius: inRadius,
                    horizontalGap: horizontalGap,
                    verticalGap: verticalGap
                }
            }
            //------------------------------------------------------------------------------------
        };
    });