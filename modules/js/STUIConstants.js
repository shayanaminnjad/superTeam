define([], function(){
    return {
        HORIZONTAL_MARGIN_PERCENT: 0.1015,
        TOP_MARGIN_ODD_PERCENT: 0.14134,
        TOP_MARGIN_EVEN_PERCENT: 0.1759,

        HEX_WIDTH_PERCENT: 0.05461,
        HEX_HEIGHT_PERCENT: 0.05961,
        HEX_VERTICAL_GAP_PERCENT: 0.00865, 
        HEX_HORIZONTAL_SPACE_PERCENT: 0.0472, 
        
        HEX_LOGICAL_WIDTH_PERCENT: 0.0630769,//hex width without any space.
        HEX_LOGICAL_HEIGHT_PERCENT: 0.0693,

        ODD_COLUMN_ROWS: 10, //odd columns have less hexes
        EVEN_COLUMN_ROWS: 11,
        COLUMNS: 17,

        //note: the hexagons doesn't seem to be perfectly matched with it's geometrical model, there are slight offsets.
        NORMALIZED_HEX_SIZE: 1,
        NORMALIZED_HEX_INRADIUS: 0.87326553953829431744613677666376,
        NORMALIZED_HEX_HORIZONTAL_SPACING: 1.7286585080610422657044814569086,
        NORMALIZED_HORIZONTAL_GAP: 1.7286585080610422657044814569086 - 1 * (3/2),
        NORMALIZED_VERTICALL_GAP: 0.25343848875351839516158698753931
    };
});