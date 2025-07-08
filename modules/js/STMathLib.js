define([], function () {

    function createVector2D(x, y) {
        return {
            x : x,
            y : y
        }
    }

    return {
        crossProduct2D: function (a, b) {
            return a.x * b.y - a.y * b.x;
        },

        dotProduct: function (a, b) {
            return a.x * b.x + a.y * b.y;
        },

        vectorSize: function (vec) {
            return Math.sqrt(vec.x * vec.x + vec.y * vec.y);
        },

        pointFromLineDistance: function (point, lineStart, lineDir) {
            if (this.vectorSize(lineDir) === 0)
                return 0;
            else
                return this.vectorSize(this.crossProduct(lineDir, this.vectorSubtract(point, lineStart))) / this.vectorSize(lineDir);
        },

        vectorSubtract: function (a, b) {
            return createVector2D(a.x - b.x, a.y - b.y);
        },

        vectorAdd: function (a, b) {
            return createVector2D(a.x + b.x, a.y + b.y);
        },

        vectorScale: function (vector, scale) {
            return createVector2D(vector.x * scale, vector.y * scale);
        },

        pointOntoLineProjection: function (point, lineStart, lineDir) {
            let projSize = this.pointOntoLineProjectionSize(point, lineStart, lineDir);

            return this.vectorScale(lineDir, projSize);
        },

        pointOntoLineProjectionSize: function (point, lineStart, lineDir) {
            let lineDirSize = this.vectorSize(lineDir);

            return this.dotProduct(lineDir, this.vectorSubtract(point, lineStart)) / (lineDirSize * lineDirSize);
        },

        radianToDegree: function (radian) {
            return 360 * radian / (2 * Math.PI);
        },
    };
});