class MangroveAreaExpander {
    constructor() {
        this.areasData = null;
        this.currentLayer = null;
    }

    async init() {
        try {
            const response = await fetch('mangroveareas.json');
            this.areasData = await response.json();
            this.repairAllPolygons();
            this.validateFeatures();
            console.log("Mangrove areas loaded and validated");
        } catch (error) {
            console.error("Failed to load areas:", error);
        }
    }

    validateFeatures() {
        this.areasData.features = this.areasData.features.filter(feature => {
            try {
                // Basic GeoJSON validation
                if (!feature.geometry || !feature.geometry.coordinates) return false;
                
                // Check coordinate structure
                const coords = feature.geometry.coordinates;
                if (feature.geometry.type === 'Polygon') {
                    return coords[0].length >= 4 && 
                           coords[0][0][0] === coords[0][coords[0].length-1][0] &&
                           coords[0][0][1] === coords[0][coords[0].length-1][1];
                } else if (feature.geometry.type === 'MultiPolygon') {
                    return coords.every(poly => 
                        poly[0].length >= 4 &&
                        poly[0][0][0] === poly[0][poly[0].length-1][0] &&
                        poly[0][0][1] === poly[0][poly[0].length-1][1]
                    );
                }
                return true;
            } catch (e) {
                console.warn("Invalid feature removed:", feature, e);
                return false;
            }
        });
    }

    createSquareAroundPoint(centerPoint, sizeMeters = 10) {
        // Convert meters to degrees (approximate)
        const sizeDeg = sizeMeters / 111320;
        
        return turf.polygon([[
            [centerPoint.lng - sizeDeg, centerPoint.lat - sizeDeg],
            [centerPoint.lng + sizeDeg, centerPoint.lat - sizeDeg],
            [centerPoint.lng + sizeDeg, centerPoint.lat + sizeDeg],
            [centerPoint.lng - sizeDeg, centerPoint.lat + sizeDeg],
            [centerPoint.lng - sizeDeg, centerPoint.lat - sizeDeg] // Close the polygon
        ]]);
    }

    async expandAreaForNewTree(treePoint) {
        if (!this.areasData) {
            console.error("Areas data not loaded");
            return false;
        }

        try {
            // Create 10m x 10m square (100m²)
            const treeSquare = this.createSquareAroundPoint(treePoint);
            
            // Find intersecting or nearest area
            const targetArea = this.findTargetArea(treeSquare);
            
            if (targetArea) {
                // Merge the square with the target area
                const expanded = turf.union(targetArea.feature, treeSquare);
                
                // Update the feature
                targetArea.feature.geometry = expanded.geometry;
                
                // Save and refresh
                const saved = await this.saveAreas();
                if (saved) this.refreshMapLayer();
                return saved;
            }
            return false;
        } catch (error) {
            console.error("Expansion failed:", error);
            return false;
        }
    }

        findTargetArea(treeSquare) {
            let targetArea = null;
            let minDistance = Infinity;
            
            this.areasData.features.forEach(feature => {
                try {
                    // First check for intersection
                    if (turf.booleanOverlap(feature, treeSquare)) {
                        targetArea = { feature, distance: 0 };
                        return;
                    }
                    
                    // If no intersection, find nearest
                    const distance = turf.distance(
                        turf.centerOfMass(treeSquare),
                        turf.centerOfMass(feature)
                    );
                    
                    if (distance < minDistance) {
                        minDistance = distance;
                        targetArea = { feature, distance };
                    }
                } catch (error) {
                    console.warn("Skipping invalid feature:", feature, error);
                }
            });
            
            return targetArea;
        }

        repairAllPolygons() {
            this.areasData.features.forEach(feature => {
                const coords = feature.geometry.coordinates;
                
                if (feature.geometry.type === 'Polygon') {
                    const first = coords[0][0];
                    const last = coords[0][coords[0].length-1];
                    if (first[0] !== last[0] || first[1] !== last[1]) {
                        coords[0].push(first);
                    }
                }
                else if (feature.geometry.type === 'MultiPolygon') {
                    coords.forEach(polygon => {
                        const first = polygon[0][0];
                        const last = polygon[0][polygon[0].length-1];
                        if (first[0] !== last[0] || first[1] !== last[1]) {
                            polygon[0].push(first);
                        }
                    });
                }
            });
        }

        async expandAreaForNewTree(treePoint) {
            if (!this.areasData) {
                console.error("Areas data not loaded");
                return false;
            }

            // Convert Leaflet latlng to Turf.js format [lng, lat]
            const treeTurfPoint = turf.point([treePoint.lng, treePoint.lat]);
            const nearest = this.findNearestArea(treeTurfPoint);
            
            if (nearest) {
                try {
                    // Create buffer (100m radius)
                    const buffer = turf.buffer(nearest.feature, 0.001, {units: 'kilometers'});
                    const expanded = turf.union(nearest.feature, buffer);
                    
                    // Update the feature
                    nearest.feature.geometry = expanded.geometry;
                    
                    // Save and refresh
                    const saved = await this.saveAreas();
                    if (saved) this.refreshMapLayer();
                    return saved;
                } catch (error) {
                    console.error("Expansion failed:", error);
                    return false;
                }
            }
            return false;
        }

        findNearestArea(point) {
            let nearest = null;
            let minDistance = Infinity;
            
            this.areasData.features.forEach(feature => {
                try {
                    const distance = turf.distance(point, feature);
                    if (distance < minDistance) {
                        minDistance = distance;
                        nearest = { feature, distance };
                    }
                } catch (error) {
                    console.warn("Error calculating distance for feature:", feature, error);
                }
            });
            
            return nearest;
        }

        async saveAreas() {
            try {
                const response = await fetch('save_areas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.areasData)
                });
                return await response.json();
            } catch (error) {
                console.error("Save failed:", error);
                return false;
            }
        }

        refreshMapLayer() {
            if (this.currentLayer) {
                extendedmangrovelayer.removeLayer(this.currentLayer);
            }
            
            this.currentLayer = L.geoJSON(this.areasData, {
                style: {
                    fillColor: '#3d9970',
                    weight: 1,
                    opacity: 1,
                    color: '#2d7561',
                    fillOpacity: 0.5
                },
                onEachFeature: function(feature, layer) {
                    layer.bindPopup(`<b>Mangrove Area</b><br>Area: ${feature.properties.area_m2?.toLocaleString() || 'N/A'} m²`);
                }
            }).addTo(extendedmangrovelayer);
        }
    }

    // Initialize the expander when map loads
    const areaExpander = new MangroveAreaExpander();
    document.addEventListener('DOMContentLoaded', () => {
        areaExpander.init();
    });