define([], function () {

    return {

        //------------------------------------------------------------------------------------
        lerp: function (a, b, t) {
            return a + (b - a) * t;
        },

        //------------------------------------------------------------------------------------

        createVector2D: function (x, y) {
            const factory = this;

            return {
                x: x,
                y: y,

                toString: function () {
                    return '(' + this.x + ', ' + this.y + ')';
                },

                equals: function (other, ep = 1e-6) {
                    return Math.abs(this.x - other.x) < eps &&
                        Math.abs(this.y - other.y) < eps;
                },

                normalized: function () {
                    const size = this.magnitude();

                    if (size === 0)
                        return factory.createVector2D(0, 0);

                    return factory.createVector2D(this.x / size, this.y / size);
                },

                magnitude: function () {
                    return Math.sqrt(this.x * this.x + this.y * this.y);
                },

                squaredMagnitude: function () {
                    return this.x * this.x + this.y * this.y;
                },

                scale: function (factor) {
                    return factory.createVector2D(this.x * factor, this.y * factor);
                },

                add: function (other) {
                    return factory.createVector2D(this.x + other.x, this.y + other.y);
                },

                subtract: function (other) {
                    return factory.createVector2D(this.x - other.x, this.y - other.y);
                }
            }
        },

        //------------------------------------------------------------------------------------

        crossProduct2D: function (a, b) {
            return a.x * b.y - a.y * b.x;
        },

        //------------------------------------------------------------------------------------

        dotProduct: function (a, b) {
            return a.x * b.x + a.y * b.y;
        },

        //------------------------------------------------------------------------------------

        pointFromLineDistance: function (point, lineStart, lineDir) {
            if (lineDir.squaredMagnitude() === 0)
                return 0;
            else
                return Math.abs(this.crossProduct2D(lineDir, point.subtract(lineStart))) / lineDir.magnitude();
        },

        //------------------------------------------------------------------------------------

        pointOntoLineProjection: function (point, lineStart, lineDir) {
            let projSize = this.pointOntoLineProjectionSize(point, lineStart, lineDir);

            return lineStart.add(lineDir.scale(projSize));
        },

        //------------------------------------------------------------------------------------

        rotateVector: function (vector, radian) {
            const sinTheta = -Math.sin(radian);
            const cosTheta = Math.cos(radian);

            return this.createVector2D(cosTheta * vector.x - sinTheta * vector.y, sinTheta * vector.x + cosTheta * vector.y);
        },

        //------------------------------------------------------------------------------------

        pointOntoLineProjectionSize: function (point, lineStart, lineDir) {
            const lineDirSquaredMagnitude = lineDir.squaredMagnitude();

            if (lineDirSquaredMagnitude === 0)
                return 0;

            return this.dotProduct(lineDir, point.subtract(lineStart)) / lineDirSquaredMagnitude;
        },

        //------------------------------------------------------------------------------------

        radianToDegree: function (radian) {
            return 360 * radian / (2 * Math.PI);
        },

    };
});