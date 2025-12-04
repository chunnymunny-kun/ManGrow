class MangroveAreaManager {
    constructor() {
        this.areas = {
            type: "FeatureCollection",
            features: []
        };
        this.editMode = false;
        this.currentPolygon = null;
        this.drawnItems = new L.FeatureGroup();
    }

    init(map) {
        this.map = map;
        map.addLayer(this.drawnItems);
        this.setupControls();
        this.loadExistingAreas();
    }

    async loadExistingAreas() {
        try {
            const response = await fetch('mangroveareas.json');
            const data = await response.json();
            
            // Only load valid polygons
            this.areas.features = data.features.filter(feature => 
                feature.geometry && 
                (feature.geometry.type === 'Polygon' || feature.geometry.type === 'MultiPolygon')
            );
            
            this.renderAreas();
        } catch (e) {
            console.log("Starting with empty areas:", e);
            this.renderAreas();
        }
    }

    setupControls() {
        const container = L.DomUtil.create('div', 'area-controls');
        
        container.innerHTML = `
            <div class="btn-group-vertical">
                <button class="btn btn-primary btn-sm" id="addAreaBtn">
                    <i class="fas fa-draw-polygon"></i> Add Area
                </button>
                <button class="btn btn-warning btn-sm" id="editAreaBtn">
                    <i class="fas fa-edit"></i> Edit Areas
                </button>
                <button class="btn btn-danger btn-sm" id="deleteAreaBtn">
                    <i class="fas fa-trash"></i> Delete Area
                </button>
                <button class="btn btn-success btn-sm" id="saveAreasBtn">
                    <i class="fas fa-save"></i> Save All
                </button>
            </div>
        `;
        
        const control = L.control({position: 'topright'});
        control.onAdd = () => container;
        control.addTo(this.map);

        // Event bindings
        document.getElementById('addAreaBtn').addEventListener('click', () => this.startDrawing());
        document.getElementById('editAreaBtn').addEventListener('click', () => this.toggleEditMode());
        document.getElementById('deleteAreaBtn').addEventListener('click', () => this.deleteSelected());
        document.getElementById('saveAreasBtn').addEventListener('click', () => this.saveAreas());
    }

    startDrawing() {
        this.editMode = false;
        this.drawnItems.clearLayers();
        
        const drawControl = new L.Draw.Polygon(this.map, {
            shapeOptions: {
                color: '#3d9970',
                fillOpacity: 0.5
            },
            guideLayers: this.drawnItems
        });
        
        drawControl.enable();
        
        this.map.on('draw:created', (e) => {
            const layer = e.layer;
            this.addNewArea(layer);
            drawControl.disable();
            this.map.off('draw:created');
        });
    }

    addNewArea(layer) {
        const geoJSON = layer.toGeoJSON();
        const areaSize = Math.round(turf.area(geoJSON));
        
        const newFeature = {
            type: "Feature",
            properties: {
                id: Date.now(),
                area_m2: areaSize,
                date_created: new Date().toISOString()
            },
            geometry: geoJSON.geometry
        };
        
        this.areas.features.push(newFeature);
        this.renderAreas();
    }

    toggleEditMode() {
        this.editMode = !this.editMode;
        this.drawnItems.clearLayers();
        
        if (this.editMode) {
            this.areas.features.forEach(feat => {
                const layer = L.geoJSON(feat, {
                    style: {color: '#ff7800', weight: 3}
                }).addTo(this.drawnItems);
                
                layer.on('click', (e) => {
                    if (this.currentPolygon) {
                        this.currentPolygon.editing.disable();
                    }
                    this.currentPolygon = layer;
                    layer.editing.enable();
                    
                    layer.on('edit', () => {
                        feat.geometry = layer.toGeoJSON().geometry;
                        feat.properties.area_m2 = Math.round(turf.area(layer.toGeoJSON()));
                    });
                });
            });
        } else if (this.currentPolygon) {
            this.currentPolygon.editing.disable();
            this.currentPolygon = null;
        }
    }

    deleteSelected() {
        if (this.currentPolygon) {
            const id = this.currentPolygon.feature.properties.id;
            this.areas.features = this.areas.features.filter(f => f.properties.id !== id);
            this.renderAreas();
            this.currentPolygon = null;
        }
    }

    renderAreas() {
        // Clear existing layers
        this.drawnItems.clearLayers();
        extendedmangrovelayer.clearLayers();
        
        // Render all areas
        L.geoJSON(this.areas, {
            style: {
                fillColor: '#3d9970',
                weight: 1,
                opacity: 1,
                color: '#2d7561',
                fillOpacity: 0.5
            },
            onEachFeature: (feature, layer) => {
                layer.bindPopup(`
                    <b>Mangrove Area</b><br>
                    Size: ${feature.properties.area_m2?.toLocaleString() || 'N/A'} m²<br>
                    Created: ${new Date(feature.properties.date_created).toLocaleDateString()}
                `);
            }
        }).addTo(extendedmangrovelayer);
    }

    async saveAreas() {
        try {
            const response = await fetch('save_areas.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(this.areas)
            });
            
            if (response.ok) {
                alert("Areas saved successfully!");
                return true;
            }
        } catch (e) {
            console.error("Save failed:", e);
        }
        alert("Failed to save areas");
        return false;
    }

    async expandAreaForTree(treePoint) {
        if (!this.areas.features.length) return false;
        
        const treeSquare = this.createSquareAroundPoint(treePoint, 5); // 5m radius (10m x 10m)
        const targetArea = this.findTargetArea(treeSquare);
        
        if (targetArea) {
            try {
                const expanded = turf.union(targetArea.feature, treeSquare);
                targetArea.feature.geometry = expanded.geometry;
                targetArea.feature.properties.area_m2 = Math.round(turf.area(expanded));
                
                await this.saveAreas();
                this.renderAreas();
                return true;
            } catch (e) {
                console.error("Expansion failed:", e);
            }
        }
        return false;
    }

    createSquareAroundPoint(centerPoint, sizeMeters = 5) {
        const sizeDeg = sizeMeters / 111320; // Approximate meters to degrees
        
        return turf.polygon([[
            [centerPoint.lng - sizeDeg, centerPoint.lat - sizeDeg],
            [centerPoint.lng + sizeDeg, centerPoint.lat - sizeDeg],
            [centerPoint.lng + sizeDeg, centerPoint.lat + sizeDeg],
            [centerPoint.lng - sizeDeg, centerPoint.lat + sizeDeg],
            [centerPoint.lng - sizeDeg, centerPoint.lat - sizeDeg] // Close polygon
        ]]);
    }

    findTargetArea(treeSquare) {
        let targetArea = null;
        let minDistance = Infinity;
        
        this.areas.features.forEach(feature => {
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
}