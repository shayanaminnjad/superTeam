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

                const lineDir = endAsCartesian.subtract(startAsCartesian);

                const rect = this.getRectangleShape(startAsCartesian, lineDir, halfLineThickness);

                for (const hex of hexArray) {

                    const currHexAsCartesian = hex.toCartesian(hexInfo);
                    const hexShape = this.getHexShape(currHexAsCartesian, hexInfo);

                    if (this.satIntersect(hexShape, rect)) {
                        result.push(hex);
                    }
                }

                return result;
            },

            //------------------------------------------------------------------------------------

            satIntersect: function (hexShape, rectShape) {
                const testingAxis = [...hexShape.getSATAxis(), ...rectShape.getSATAxis()];

                const hexVerticesArray = hexShape.getVerticesAsArray();
                const rectVerticesArray = rectShape.getVerticesAsArray();

                for (const axis of testingAxis) {
                    const hexMinMaxProj = this.getMinMaxProjections(hexVerticesArray, axis);
                    const rectMinMaxProj = this.getMinMaxProjections(rectVerticesArray, axis);

                    if (hexMinMaxProj.max < rectMinMaxProj.min || hexMinMaxProj.min > rectMinMaxProj.max)
                        return false;
                }

                return true;
            },

            getMinMaxProjections: function (verticesArray, axis) {
                let hexMinPoint = Infinity;
                let hexMaxPoint = -Infinity;
                for (const vertex of verticesArray) {
                    let projSize = STMathLib.dotProduct(vertex, axis);
                    hexMinPoint = Math.min(hexMinPoint, projSize);
                    hexMaxPoint = Math.max(hexMaxPoint, projSize);
                }

                return {
                    min: hexMinPoint,
                    max: hexMaxPoint
                };
            },

            //------------------------------------------------------------------------------------

            getHexShape: function (hexCenter, hexInfo) {
                return {
                    right: STMathLib.createVector2D(hexCenter.x + hexInfo.size, hexCenter.y),
                    left: STMathLib.createVector2D(hexCenter.x - hexInfo.size, hexCenter.y),
                    topRight: STMathLib.createVector2D(hexCenter.x + hexInfo.size * 0.5, hexCenter.y - hexInfo.inRadius),
                    topLeft: STMathLib.createVector2D(hexCenter.x - hexInfo.size * 0.5, hexCenter.y - hexInfo.inRadius),
                    bottomRight: STMathLib.createVector2D(hexCenter.x + hexInfo.size * 0.5, hexCenter.y + hexInfo.inRadius),
                    bottomLeft: STMathLib.createVector2D(hexCenter.x - hexInfo.size * 0.5, hexCenter.y + hexInfo.inRadius),

                    getSATAxis: function () {
                        const rotation = Math.PI * 0.5;

                        return [
                            STMathLib.rotateVector(this.topRight.subtract(this.right), rotation).normalized(),
                            STMathLib.rotateVector(this.right.subtract(this.bottomRight), rotation).normalized(),
                            STMathLib.rotateVector(this.bottomLeft.subtract(this.bottomRight), rotation).normalized()
                        ];
                    },

                    getVerticesAsArray() {
                        return [
                            this.right,
                            this.bottomRight,
                            this.bottomLeft,
                            this.left,
                            this.topLeft,
                            this.topRight
                        ];
                    }
                }
            },

            //------------------------------------------------------------------------------------

            getRectangleShape: function (lineStart, lineDir, halfThickness) {
                const normalizedDir = lineDir.normalized();
                const rotation = Math.PI * 0.5;
                const perp = STMathLib.rotateVector(normalizedDir, rotation);

                const topLeft = lineStart.add(perp.scale(halfThickness));
                const bottomLeft = lineStart.add(perp.scale(-halfThickness));

                return {
                    topLeft: topLeft,
                    topRight: topLeft.add(lineDir),
                    bottomLeft: bottomLeft,
                    bottomRight: bottomLeft.add(lineDir),

                    getSATAxis: function () {
                        return [perp, normalizedDir];
                    },

                    getVerticesAsArray() {
                        return [
                            this.topLeft,
                            this.topRight,
                            this.bottomRight,
                            this.bottomLeft
                        ];
                    }
                };
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
                        const horizontalSpacing = hexInfo.horizontalSpacing;
                        const verticalSpacing = 2 * hexInfo.inRadius;

                        const isEvenColumn = (this.c + 1) % 2 == 1;

                        if (isEvenColumn) {
                            return STMathLib.createVector2D(this.c * (horizontalSpacing), this.r * (verticalSpacing + hexInfo.verticalGap));
                        }
                        else {
                            const oddOffset = hexInfo.oddOffset;
                            return STMathLib.createVector2D(this.c * (horizontalSpacing), oddOffset + this.r * (verticalSpacing + hexInfo.verticalGap));
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

            createHexInfo: function (size, inRadius, horizontalSpacing, verticalGap, oddOffset) {
                return {
                    size: size,
                    inRadius: inRadius,
                    horizontalSpacing: horizontalSpacing,
                    verticalGap: verticalGap,
                    oddOffset: oddOffset
                }
            }
            //------------------------------------------------------------------------------------
        };
    });