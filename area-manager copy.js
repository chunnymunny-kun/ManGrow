class AreaManager {
    constructor() {
        this.areas = {
            type: "FeatureCollection",
            features: []
        };
        this.currentArea = null;
        this.drawnItems = new L.FeatureGroup();
        this.editMode = false;
    }

    init(map) {
        this.map = map;
        map.addLayer(this.drawnItems);
        this.loadAreas();
        this.setupEventListeners();
    }

    async loadAreas() {
    try {
        const response = await fetch('mangroveareas.json');
        if (!response.ok) {
            // If file doesn't exist, use empty FeatureCollection
            this.areas = {
                type: "FeatureCollection",
                features: []
            };
        } else {
            const data = await response.json();
            this.areas.features = data.features || [];
        }
        this.renderAreas();
    } catch (error) {
        console.log("Starting with empty areas:", error);
        this.areas = {
            type: "FeatureCollection",
            features: []
        };
        this.renderAreas();
    }
}

    renderAreas() {
        // Clear existing layers
        extendedmangrovelayer.clearLayers();

        // Add all areas to the extendedmangrovelayer
        this.areas.features.forEach(feature => {
            const layer = L.geoJSON(feature, {
                style: {
                    fillColor: '#3d9970',
                    weight: 1,
                    opacity: 1,
                    color: '#2d7561',
                    fillOpacity: 0.5
                }
            }).addTo(extendedmangrovelayer);

            // Store the popup content but don't bind it yet
            const popupContent = `
                <div class="mangrove-popup"
                    data-area-no="${feature.properties.area_no || ''}"
                    data-location="${feature.properties.city_municipality || ''}"
                    data-created-date="${feature.properties.date_created || ''}"
                    data-updated-date="${feature.properties.date_updated || ''}"
                >
                    <h4>
                        <i class="fas fa-tree" style="color:#3d9970"></i>
                        ${feature.properties.area_no || 'Mangrove Area'}
                    </h4>
                    <table>
                        <tr>
                            <th><i class="fas fa-map-marker-alt" title="Location"></i> Location: </th>
                                <td>${feature.properties.city_municipality || 'N/A'}</td>
                        </tr>
                        <tr>
                            <th>
                                <i class="fas fa-ruler-combined" title="Size"></i> Size: </th>
                            <td>${feature.properties.area_m2?.toLocaleString() || 'N/A'} m² (${feature.properties.area_ha || 'N/A'} ha)</td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-calendar-plus" title="Created"></i> Created: </th>
                            <td>${feature.properties.date_created ? new Date(feature.properties.date_created).toLocaleString() : 'N/A'}</td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-calendar-check" title="Updated"></i> Updated: </th>
                            <td>${feature.properties.date_updated ? new Date(feature.properties.date_updated).toLocaleString() : 'N/A'}</td>
                        </tr>
                    </table>
                    <div class="popup-actions">
                        <button class="btn btn-sm btn-primary edit-area" 
                            data-id="${feature.properties.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                </div>
            `;

            // For edit mode selection
            layer.on('click', (e) => {
                const checkbox = document.getElementById('addMarkerCheckbox');
                console.log('Layer clicked. Checkbox exists:', !!checkbox, 'Checked:', checkbox?.checked);
                
                if (checkbox?.checked) {
                    console.log('Click ignored because addMarkerCheckbox is checked.');
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopPropagation();
                    return false;
                } else {
                    // Only open popup if not in edit mode
                    if (!this.editMode) {
                        L.popup()
                            .setLatLng(e.latlng)
                            .setContent(popupContent)
                            .openOn(this.map);
                    } else {
                        this.selectArea(layer, feature);
                    }
                }
            });

            // Store the popup content on the layer for later use
            layer._popupContent = popupContent;
        });
    }

    setupEventListeners() {
        // Add Area Button
        document.getElementById('addAreaBtn').addEventListener('click', () => {
            this.startDrawingNewArea();
        });

        // Edit Areas Button
        document.getElementById('editAreasBtn').addEventListener('click', () => {
            this.toggleEditMode();
        });

        // Delete Area Button
        document.getElementById('deleteAreaBtn').addEventListener('click', () => {
            this.deleteSelectedArea();
        });

        // Save Areas Button
        document.getElementById('saveAreasBtn').addEventListener('click', () => {
            this.saveAreas();
        });
    }

    startDrawingNewArea() {
        this.exitEditMode();
        
        // Clear any existing drawn items
        this.drawnItems.clearLayers();
        
        // Show the form before drawing
        const formDiv = L.DomUtil.create('div', 'area-form');
        formDiv.innerHTML = `
            <h3>Add New Mangrove Area</h3>
            <div class="form-group">
                <label>Area Number:</label>
                <input type="text" id="areaNoInput" class="form-control" required>
            </div>
            <div class="form-group">
                <label>City/Municipality:</label>
                <select id="cityMunicipalityInput" class="form-control" required>
                    <option value="">Select City/Municipality</option>
                    <option value="Abucay">Abucay</option>
                    <option value="Bagac">Bagac</option>
                    <option value="Balanga">Balanga</option>
                    <option value="Dinalupihan">Dinalupihan</option>
                    <option value="Hermosa">Hermosa</option>
                    <option value="Limay">Limay</option>
                    <option value="Mariveles">Mariveles</option>
                    <option value="Morong">Morong</option>
                    <option value="Orani">Orani</option>
                    <option value="Orion">Orion</option>
                    <option value="Pilar">Pilar</option>
                    <option value="Samal">Samal</option>
                </select>
            </div>
            <div class="form-buttons">
                <button id="startDrawingBtn" class="btn btn-primary">Start Drawing</button>
                <button id="cancelDrawingBtn" class="btn btn-secondary">Cancel</button>
            </div>
        `;
        
        const popup = L.popup()
            .setLatLng(this.map.getCenter())
            .setContent(formDiv)
            .openOn(this.map);
        
        L.DomEvent.on(formDiv.querySelector('#startDrawingBtn'), 'click', () => {
            const areaNo = formDiv.querySelector('#areaNoInput').value;
            const cityMunicipality = formDiv.querySelector('#cityMunicipalityInput').value;
            
            if (!areaNo || !cityMunicipality) {
                alert('Please fill in all fields');
                return;
            }
            
            this.currentAreaDetails = {
                area_no: areaNo,
                city_municipality: cityMunicipality
            };
            
            this.map.closePopup();
            this.initializeDrawing();
        });
        
        L.DomEvent.on(formDiv.querySelector('#cancelDrawingBtn'), 'click', () => {
            this.map.closePopup();
        });
    }

    initializeDrawing() {
        // Initialize the draw control
        const drawControl = new L.Draw.Polygon(this.map, {
            shapeOptions: {
                color: '#3d9970',
                fillOpacity: 0.5
            }
        });
        
        drawControl.enable();
        
        // Handle when a polygon is drawn
        this.map.once('draw:created', (e) => {
            const layer = e.layer;
            this.addNewArea(layer);
            drawControl.disable();
        });
    }

    async addNewArea(layer) {
        const geoJSON = layer.toGeoJSON();
        const areaSize = Math.round(turf.area(geoJSON));
        const areaHectares = (areaSize / 10000).toFixed(2); // Calculate hectares
        const phTime = new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" });
        
        // Find intersecting areas
        const intersectingAreas = this.findIntersectingAreas(geoJSON);
        
        if (intersectingAreas.length > 0) {
            const shouldMerge = confirm(`This area intersects with ${intersectingAreas.length} existing area(s). Do you want to merge them?`);
            
            if (shouldMerge) {
                const mergeSuccess = await this.handleAreaMerge(geoJSON, intersectingAreas);
                if (mergeSuccess) {
                    this.currentAreaDetails = null;
                    return;
                }
            }
        }
        
        // Proceed with normal area addition if no merge
        const newArea = {
            type: "Feature",
            properties: {
                ...this.currentAreaDetails,
                id: Date.now(),
                area_m2: areaSize,
                area_ha: areaHectares, // Store hectares
                date_created: new Date(phTime).toISOString(),
                date_updated: new Date(phTime).toISOString()
            },
            geometry: geoJSON.geometry
        };
        
        this.areas.features.push(newArea);
        this.currentAreaDetails = null;
        this.hasUnsavedChanges = true;
        this.renderAreas();
    }

     findIntersectingAreas(newGeoJSON) {
        return this.areas.features.filter(existingArea => {
            try {
                const existingPolygon = turf.polygon(existingArea.geometry.coordinates);
                const newPolygon = turf.polygon(newGeoJSON.geometry.coordinates);
                return turf.booleanIntersects(existingPolygon, newPolygon);
            } catch (e) {
                console.error("Error checking intersection:", e);
                return false;
            }
        });
    }

    async handleAreaMerge(newGeoJSON, intersectingAreas) {
        // Sort by date_created (oldest first)
        intersectingAreas.sort((a, b) => 
            new Date(a.properties.date_created) - new Date(b.properties.date_created));
        
        const oldestArea = intersectingAreas[0];
        const otherAreas = intersectingAreas.slice(1);
        
        // Create a modal for better UX
        const mergeModal = this.createMergeModal(oldestArea, otherAreas);
        document.body.appendChild(mergeModal);
        
        return new Promise((resolve) => {
            document.getElementById('confirmMergeBtn').addEventListener('click', async () => {
                const newAreaName = document.getElementById('mergedAreaName').value;
                if (!newAreaName) {
                    alert('Please enter an area name');
                    return;
                }
                
                try {
                    // Combine geometries
                    const mergedGeometry = await this.combineGeometries(oldestArea, newGeoJSON, otherAreas);
                    const mergedAreaSize = Math.round(turf.area(turf.polygon(mergedGeometry.coordinates)));
                    const mergedAreaHectares = (mergedAreaSize / 10000).toFixed(2);
                    
                    // Update the oldest area
                    oldestArea.geometry = mergedGeometry;
                    oldestArea.properties.area_no = newAreaName;
                    oldestArea.properties.area_m2 = mergedAreaSize;
                    oldestArea.properties.area_ha = mergedAreaHectares;
                    oldestArea.properties.date_updated = new Date().toISOString();
                    
                    // Remove other areas that were merged
                    this.areas.features = this.areas.features.filter(area => 
                        !otherAreas.some(a => a.properties.id === area.properties.id)
                    );
                    
                    document.body.removeChild(mergeModal);
                    this.hasUnsavedChanges = true;
                    this.renderAreas();
                    alert("Areas merged successfully!");
                    resolve(true);
                } catch (error) {
                    console.error("Error merging areas:", error);
                    alert("Failed to merge areas. Please try again.");
                    resolve(false);
                }
            });
            
            document.getElementById('cancelMergeBtn').addEventListener('click', () => {
                document.body.removeChild(mergeModal);
                resolve(false);
            });
        });
    }

    createMergeModal(oldestArea, otherAreas) {
        const modal = document.createElement('div');
        modal.className = 'merge-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>Merge Areas</h3>
                <p>The new area intersects with ${otherAreas.length + 1} existing area(s):</p>
                <div class="merge-info">
                    <h4>Primary Area (will be kept):</h4>
                    <p>${oldestArea.properties.area_no} (created ${new Date(oldestArea.properties.date_created).toLocaleDateString()})</p>
                    
                    <h4>Areas to be merged:</h4>
                    <ul class="merge-list">
                        ${otherAreas.map(area => `
                            <li>${area.properties.area_no} (created ${new Date(area.properties.date_created).toLocaleDateString()})</li>
                        `).join('')}
                    </ul>
                    
                    <div class="form-group">
                        <label for="mergedAreaName">New Area Name:</label>
                        <input type="text" id="mergedAreaName" value="${oldestArea.properties.area_no}" required>
                    </div>
                </div>
                <div class="modal-buttons">
                    <button id="confirmMergeBtn" class="btn btn-primary">Confirm Merge</button>
                    <button id="cancelMergeBtn" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        `;
        return modal;
    }

    async combineGeometries(oldestArea, newGeoJSON, otherAreas) {
        try {
            // Create Turf polygons
            const polygons = [
                turf.polygon(oldestArea.geometry.coordinates),
                turf.polygon(newGeoJSON.geometry.coordinates)
            ];
            
            // Add other areas if they exist
            otherAreas.forEach(area => {
                polygons.push(turf.polygon(area.geometry.coordinates));
            });
            
            // Combine all polygons
            let combined = polygons[0];
            for (let i = 1; i < polygons.length; i++) {
                combined = turf.union(combined, polygons[i]);
                if (!combined) {
                    throw new Error(`Failed to merge with area ${i}`);
                }
            }
            
            return combined.geometry;
        } catch (error) {
            console.error("Error in combineGeometries:", error);
            throw error;
        }
    }

    toggleEditMode() {
        this.editMode = !this.editMode;
        
        if (this.editMode) {
            document.getElementById('editAreasBtn').classList.add('active');
            alert("Edit mode: Click on an area to select it, then you can delete or edit its vertices");
        } else {
            document.getElementById('editAreasBtn').classList.remove('active');
            this.exitEditMode();
        }
    }

    selectArea(layer, feature) {
        // Clear previous selection
        this.drawnItems.clearLayers();
        
        // Create a new editable layer from the feature
        const editableLayer = L.geoJSON(feature, {
            style: {
                color: '#ff7800',
                weight: 3,
                opacity: 1,
                fillOpacity: 0.7
            }
        }).addTo(this.drawnItems);
        
        // Use the stored popup content
        editableLayer.bindPopup(layer._popupContent);
        
        layer.bindPopup(`
            <b>Mangrove Area</b><br>
            Area No: ${feature.properties.area_no}<br>
            City/Municipality: ${feature.properties.city_municipality}<br>
            Size: ${feature.properties.area_m2?.toLocaleString() || 'N/A'} m² (${feature.properties.area_ha || 'N/A'} ha)<br>
            Created: ${new Date(feature.properties.date_created).toLocaleString()}<br>
            Updated: ${new Date(feature.properties.date_updated).toLocaleString()}
        `);

        // Make the layer editable
        editableLayer.eachLayer((layer) => {
            if (layer.editing) {
                layer.editing.enable();
            } else if (layer instanceof L.Polygon) {
                layer.editing = new L.Handler.PolyEdit(map, layer);
                layer.editing.enable();
            }
        });

        // Save reference
        this.currentArea = {
            layer: editableLayer,
            feature: feature,
            originalLayer: layer
        };
        
        // Update on edit
        editableLayer.on('edit', () => {
            const editedGeoJSON = editableLayer.toGeoJSON();
            const editedAreaSize = Math.round(turf.area(editedGeoJSON));
            const editedAreaHectares = (editedAreaSize / 10000).toFixed(2);
            
            feature.geometry = editedGeoJSON.geometry;
            feature.properties.area_m2 = editedAreaSize;
            feature.properties.area_ha = editedAreaHectares;
            
            // Update the original layer's geometry
            if (layer.setLatLngs) {
                const latLngs = editableLayer.getLayers()[0].getLatLngs();
                layer.setLatLngs(latLngs);
            }
        });
    }

    deleteSelectedArea() {
        if (this.currentArea) {
            const id = this.currentArea.feature.properties.id;
            this.areas.features = this.areas.features.filter(area => area.properties.id !== id);
            this.renderAreas();
            this.currentArea = null;
            this.drawnItems.clearLayers();
        } else {
            alert("No area selected. Enter edit mode and click an area first.");
        }
    }

    exitEditMode() {
        this.editMode = false;
        document.getElementById('editAreasBtn').classList.remove('active');
        this.drawnItems.clearLayers();
        this.currentArea = null;
    }

    async saveAreas() {
        try {
            // Update date_updated for all features
            const phTime = new Date().toLocaleString("en-US", {
                timeZone: "Asia/Manila",
                hour12: false
            });
            const currentDate = new Date(phTime).toISOString();
            
            this.areas.features.forEach(feature => {
                feature.properties.date_updated = currentDate;
            });

            const response = await fetch('save_areas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.areas)
            });
            
            if (response.ok) {
                alert("Areas saved successfully!");
                return true;
            }
        } catch (error) {
            console.error("Save failed:", error);
        }
        
        alert("Failed to save areas");
        return false;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const areaManager = new AreaManager();
    areaManager.init(map);
});