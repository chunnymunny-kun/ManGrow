// Utility to fix unclosed polygons in your JSON data
function repairPolygons(geojson) {
    return {
        ...geojson,
        features: geojson.features.map(feature => {
            const coords = feature.geometry.coordinates;
            
            // Handle Polygon type
            if (feature.geometry.type === 'Polygon') {
                const first = coords[0][0];
                const last = coords[0][coords[0].length-1];
                if (first[0] !== last[0] || first[1] !== last[1]) {
                    coords[0].push(first);
                }
            }
            // Handle MultiPolygon
            else if (feature.geometry.type === 'MultiPolygon') {
                coords.forEach(polygon => {
                    const first = polygon[0][0];
                    const last = polygon[0][polygon[0].length-1];
                    if (first[0] !== last[0] || first[1] !== last[1]) {
                        polygon[0].push(first);
                    }
                });
            }
            
            return feature;
        })
    };
}

// Run this once to fix your existing data
fetch('extendedmangroveareas.json')
    .then(r => r.json())
    .then(data => {
        const repaired = repairPolygons(data);
        // Save repaired data (see save_areas.php below)
    });