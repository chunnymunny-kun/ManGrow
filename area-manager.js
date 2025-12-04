class AreaManager {
    constructor() {
        this.areas = {
            type: "FeatureCollection",
            features: []
        };
        this.currentArea = null;
        this.drawnItems = new L.FeatureGroup();
        this.editMode = false;
        this.AreaStore = {};
        this.isAdmin = document.getElementById('areaControlPanel') !== null;
        this.userRole = window.currentUser?.role || '';
        this.userCity = window.currentUser?.city || '';
        this.cityBoundaries = null;
        this.boundaryLayer = null;
        this.cityBoundaryLayer = null;
        this.currentCityBoundary = null;
        
        // Track pending activity logs to batch them
        this.pendingLogs = [];
        this.pendingNavigationUrl = null;
        
        // UI state management
        this.uiEnabled = true;
    }

    init(map) {
        this.map = map;
        map.addLayer(this.drawnItems);
        this.loadAreas();
        
        // Load city boundaries if user is Barangay Official
        if (this.userRole === 'Barangay Official' && this.userCity) {
            this.loadCityBoundaries();
        }
        
        if (this.isAdmin) {
            this.setupEventListeners();
            this.updateButtonStates();
        }
    }

    async logActivity(actionType, identifier, areaId, details, shouldQueue = true) {
        console.log(`logActivity called: ${actionType}, shouldQueue: ${shouldQueue}, hasUnsavedChanges: ${this.hasUnsavedChanges}`);
        
        // If we should queue this activity (during unsaved changes), add to pending logs
        // Queue all activities except save_changes (which is the final summary log)
        if (shouldQueue && this.hasUnsavedChanges && actionType !== 'save_changes') {
            console.log(`Queuing activity: ${actionType} for ${identifier}`);
            this.pendingLogs.push({
                actionType,
                identifier,
                areaId,
                details,
                timestamp: new Date().toISOString()
            });
            return true; // Return success for queued logs
        }
        
        // Otherwise, log immediately (for save_changes or when not queuing)
        console.log(`Logging immediately: ${actionType} for ${identifier}`);
        try {
            const formData = new FormData();
            formData.append('action_type', actionType);
            formData.append('area_no', identifier);  // Always use identifier for area_no
            formData.append('id', areaId);
            
            // For save_changes action, use a generic city_municipality value
            if (actionType === 'save_changes') {
                formData.append('city_municipality', 'Multiple Areas');
            } else if (actionType !== 'delete_area') {
                // For all actions except delete_area, use city_municipality from the area's properties
                const area = this.areas.features.find(f => f.properties.id === areaId);
                if (area) {
                    formData.append('city_municipality', area.properties.city_municipality || '');
                } else {
                    formData.append('city_municipality', '');
                }
            } else {
                // For delete_area, use the identifier (which is area_no) as before
                formData.append('city_municipality', identifier);
            }
            
            formData.append('details', details);

            const response = await fetch('log_activity.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            if (result.status !== 'success') {
                console.error('Activity log failed:', {
                    actionType,
                    error: result.message,
                    debugInfo: result.debug_info
                });
                return false;
            }
            return true;
        } catch (error) {
            console.error('Error logging activity:', {
                actionType,
                error: error.message,
                stack: error.stack
            });
            return false;
        }
    }

    async flushPendingLogs() {
        try {
            console.log('=== FLUSHING PENDING LOGS ===');
            console.log('Pending logs count:', this.pendingLogs.length);
            console.log('Pending logs:', this.pendingLogs);
            
            let successful = 0;
            let failed = 0;
            const originalLogCount = this.pendingLogs.length;
            
            // Process each log immediately to ensure they all get recorded
            if (this.pendingLogs.length > 0) {
                const results = [];
                for (const log of this.pendingLogs) {
                    const result = await this.logActivity(
                        log.actionType, 
                        log.identifier, 
                        log.areaId, 
                        log.details, 
                        false // Don't queue these
                    );
                    results.push(result);
                }
                
                // Count successful and failed logs
                successful = results.filter(r => r).length;
                failed = results.filter(r => !r).length;
                
                // Clear pending logs after processing
                this.pendingLogs = [];
            }
            
            // Always log a save_changes action when saving (whether there were pending logs or not)
            const summary = originalLogCount > 0 || successful > 0 || failed > 0
                ? `Batch operation completed: ${successful} actions logged successfully${failed > 0 ? `, ${failed} failed` : ''}`
                : 'Save operation completed';
            
            // Log the save_changes action with summary
            const saveResult = await this.logActivity(
                'save_changes',
                `batch_${Date.now()}`, // Use timestamp as identifier 
                0, // Generic ID for batch operations
                summary,
                false // Don't queue this
            );

            console.log(`Flush completed: ${successful}/${successful + failed} logs successful, save_changes logged:`, saveResult);
            
            return (failed === 0) && saveResult; // Success if no failures and save_changes logged
        } catch (error) {
            console.error('Error flushing pending logs:', error);
            // Don't clear pending logs if there was an error
            return false;
        }
    }

    // UI Access Control Methods
    setUIState(enabled) {
        // Main area management buttons
        const buttons = [
            'addAreaBtn',
            'editAreasBtn', 
            'deleteAreaBtn',
            'saveAreasBtn'
        ];
        
        buttons.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = !enabled;
                if (enabled) {
                    btn.classList.remove('disabled');
                } else {
                    btn.classList.add('disabled');
                }
            }
        });
        
        // Navigation buttons (logout, return home, report page)
        const navButtons = document.querySelectorAll('[name="logoutbtn"], [name="returnbtn"], .action-btn');
        navButtons.forEach(btn => {
            btn.disabled = !enabled;
            if (enabled) {
                btn.classList.remove('disabled');
            } else {
                btn.classList.add('disabled');
            }
        });
        
        // Disable map interactions during operations
        if (this.map) {
            if (enabled) {
                this.map.dragging.enable();
                this.map.touchZoom.enable();
                this.map.doubleClickZoom.enable();
                this.map.scrollWheelZoom.enable();
                this.map.boxZoom.enable();
                this.map.keyboard.enable();
            } else {
                this.map.dragging.disable();
                this.map.touchZoom.disable();
                this.map.doubleClickZoom.disable();
                this.map.scrollWheelZoom.disable();
                this.map.boxZoom.disable();
                this.map.keyboard.disable();
            }
        }
        
        // Store UI state
        this.uiEnabled = enabled;
        
        console.log(`UI ${enabled ? 'enabled' : 'disabled'}`);
    }
    
    disableUI() {
        this.setUIState(false);
    }
    
    enableUI() {
        this.setUIState(true);
    }

    async loadCityBoundaries() {
        try {
            const response = await fetch('bataancm1.geojson');
            const data = await response.json();
            
            const userCityBoundary = data.features.find(feature => 
                feature.properties.city_municipality === this.userCity
            );
            
            if (userCityBoundary) {
                this.cityBoundaries = userCityBoundary;
                // Create boundary layer with pulsing class
                this.boundaryLayer = L.geoJSON(userCityBoundary, {
                    style: {
                        color: '#007bff',
                        weight: 2,
                        fillOpacity: 0.1
                    },
                    className: 'pulsing-boundary' // Add this line
                });
                console.log(`Loaded boundaries for ${this.userCity}`);
            } else {
                console.warn(`No boundaries found for ${this.userCity}`);
            }
        } catch (error) {
            console.error('Error loading city boundaries:', error);
        }
    }

    // Modified method to check if a point is within the allowed area for Barangay Officials
    isLocationAllowed(latlng) {
        // Admins and other roles can add markers anywhere
        if (this.userRole !== 'Barangay Official') {
            return true;
        }
        
        // If no city boundaries loaded, restrict adding markers
        if (!this.cityBoundaries) {
            alert(`You are restricted to ${this.userCity}, but no boundaries were found.`);
            return false;
        }
        
        // Check if the point is within the user's city boundaries
        const point = turf.point([latlng.lng, latlng.lat]);
        const polygon = turf.polygon(this.cityBoundaries.geometry.coordinates);
        const isInside = turf.booleanPointInPolygon(point, polygon);
        
        if (!isInside) {
            alert(`You can only add tree pins within ${this.userCity} boundaries.`);
            return false;
        }
        
        return true;
    }

    async loadBarangaysForAreaEdit(cityMunicipality, container, selectedBarangays = []) {
        try {
            container.innerHTML = '<div class="loading-placeholder">Loading barangays...</div>';
            
            const response = await fetch(`getdropdown.php?city=${encodeURIComponent(cityMunicipality)}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            container.innerHTML = '';
            
            if (data.length === 0) {
                container.innerHTML = '<div class="loading-placeholder">No barangays found for this city/municipality</div>';
                return;
            }
            
            data.forEach(barangay => {
                const checkboxItem = document.createElement('div');
                checkboxItem.className = 'checkbox-item';
                
                const checkboxId = `edit-area-barangay-${barangay.barangay.replace(/\s+/g, '-').toLowerCase()}`;
                const isChecked = selectedBarangays.includes(barangay.barangay);
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = checkboxId;
                checkbox.value = barangay.barangay;
                checkbox.checked = isChecked;
                
                const label = document.createElement('label');
                label.htmlFor = checkboxId;
                label.textContent = barangay.barangay;
                
                checkboxItem.appendChild(checkbox);
                checkboxItem.appendChild(label);
                
                container.appendChild(checkboxItem);
            });
            
        } catch (error) {
            console.error('Error fetching barangay data:', error);
            container.innerHTML = '<div class="loading-placeholder">Error loading barangays</div>';
        }
    }

    async loadBarangaysForArea(cityMunicipality, container) {
        try {
            container.innerHTML = '<div class="loading-placeholder">Loading barangays...</div>';
            
            const response = await fetch(`getdropdown.php?city=${encodeURIComponent(cityMunicipality)}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            container.innerHTML = '';
            
            if (data.length === 0) {
                container.innerHTML = '<div class="loading-placeholder">No barangays found for this city/municipality</div>';
                return;
            }
            
            data.forEach(barangay => {
                const checkboxItem = document.createElement('div');
                checkboxItem.className = 'checkbox-item';
                
                const checkboxId = `area-barangay-${barangay.barangay.replace(/\s+/g, '-').toLowerCase()}`;
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = checkboxId;
                checkbox.value = barangay.barangay;
                
                const label = document.createElement('label');
                label.htmlFor = checkboxId;
                label.textContent = barangay.barangay;
                
                checkboxItem.appendChild(checkbox);
                checkboxItem.appendChild(label);
                
                container.appendChild(checkboxItem);
            });
            
        } catch (error) {
            console.error('Error fetching barangay data:', error);
            container.innerHTML = '<div class="loading-placeholder">Error loading barangays</div>';
        }
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
        // Clear existing layers and store
        extendedmangrovelayer.clearLayers();
        this.areaStore = {};

        // Removed: Get current user info from PHP session (passed to JS)
        // const currentUser = window.currentUser || {
        //     role: '',
        //     city: ''
        // };

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

            // Store the layer reference in our area store
            if (!feature.properties.id) {
                feature.properties.id = 'area_' + Date.now();
            }
            this.areaStore[feature.properties.id] = layer;

            // Store the feature data on the layer
            layer.feature = feature;

            // Create popup content
            function formatDateTime(dt) {
                if (!dt) return 'N/A';
                const date = new Date(dt);
                if (isNaN(date)) return dt;
                const options = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                };
                return date.toLocaleString('en-US', options);
            }

            const popupContent = `
                <div class="mangrove-popup">
                    <h4>${feature.properties.area_no || 'Mangrove Area'}</h4>
                    <table>
                        <tr><th>Location:</th><td>${feature.properties.city_municipality || 'N/A'}</td></tr>
                        ${feature.properties.barangays ? `<tr><th>Barangays:</th><td>${Array.isArray(feature.properties.barangays) ? feature.properties.barangays.join(', ') : feature.properties.barangays}</td></tr>` : ''}
                        <tr><th>Size:</th><td>${feature.properties.area_m2?.toLocaleString() || 'N/A'} m²</td></tr>
                        <tr><th>Size (Ha):</th><td>${feature.properties.area_ha || 'N/A'} hectares</td></tr>
                        <tr><th>Created:</th><td>${formatDateTime(feature.properties.date_created)}</td></tr>
                        <tr><th>Updated:</th><td>${formatDateTime(feature.properties.date_updated)}</td></tr>
                    </table>
                    <div class="popup-actions">
                        ${this.shouldShowEditButtons(feature) ? `
                        <button class="btn btn-sm btn-primary" onclick="areaManager.editAreaDetails('${feature.properties.id}')">
                            <i class="fas fa-edit"></i> Edit Details
                        </button>
                        ` : `
                        <div class="text-muted">Editing restricted to your city/municipality</div>
                        `}
                    </div>
                </div>
            `;

            // Bind popup with proper options
            layer.bindPopup(popupContent, {
                className: 'mangrove-popup',
                maxWidth: 400,
                minWidth: 300
            });

            // Click handler for the area
            layer.on('click', (e) => {
                const checkbox = document.getElementById('addMarkerCheckbox');

                if (checkbox?.checked) {
                    // Check if location is allowed for this user
                    if (!this.isLocationAllowed(e.latlng)) {
                        return false;
                    }

                    e.originalEvent.preventDefault();
                    e.originalEvent.stopPropagation();

                    // Show the popup for adding a pin at the clicked location
                    const formDiv = L.DomUtil.create('div', 'marker-form');
                    formDiv.innerHTML = `
                        <h3>Add Mangrove Marker</h3>
                        <div class="form-group">
                            <label>Area Number:</label>
                            <input type="text" id="areaNo" class="form-control" value="${feature.properties.area_no || ''}">
                        </div>
                        <div class="form-group">
                            <label>Mangrove Type:</label>
                            <select id="mangroveType" class="form-control">
                                <option value="Rhizophora apiculata">Bakawan lalake</option>
                                <option value="Rhizophora mucronata">Bakawan babae</option>
                                <option value="Avicennia marina">Bungalon</option>
                                <option value="Sonneratia alba">Palapat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status:</label>
                            <select id="status" class="form-control">
                                <option value="Healthy">Alive</option>
                                <option value="Growing">Growing</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Dead">Dead</option>
                            </select>
                        </div>
                        <div class="form-buttons">
                            <button id="saveMarker" class="btn btn-primary">Save</button>
                            <button id="cancelMarker" class="btn btn-secondary">Cancel</button>
                        </div>
                    `;

                    const popup = L.popup()
                        .setLatLng(e.latlng)
                        .setContent(formDiv)
                        .openOn(this.map);

                    // Save marker logic - Updated to include tree area creation
                    L.DomEvent.on(formDiv.querySelector('#saveMarker'), 'click', async () => {
                        const areaNo = formDiv.querySelector('#areaNo').value;
                        const mangroveType = formDiv.querySelector('#mangroveType').value;
                        const status = formDiv.querySelector('#status').value;

                        this.map.closePopup();

                        try {
                            const saveResponse = await fetch('save_marker.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    latitude: e.latlng.lat,
                                    longitude: e.latlng.lng,
                                    area_no: areaNo,
                                    mangrove_type: mangroveType,
                                    status: status
                                })
                            });

                            const saveData = await saveResponse.json();

                            if (saveData.success) {
                                // Create the tree marker
                                const newMarker = L.marker(e.latlng, { 
                                    icon: customMangroveIcon 
                                }).addTo(treelayer);
                                
                                // Create the 100 sqm orange area for this tree
                                const treeArea = createTreeArea(e.latlng);
                                treeArea.addTo(treelayer);

                                // Store references
                                newMarker.feature = {
                                    type: 'Feature',
                                    properties: {
                                        mangrove_id: saveData.mangrove_id,
                                        area_no: areaNo,
                                        mangrove_type: mangroveType,
                                        status: status,
                                        date_added: new Date().toISOString()
                                    },
                                    geometry: {
                                        type: 'Point',
                                        coordinates: [e.latlng.lng, e.latlng.lat]
                                    }
                                };

                                // Store both marker and area
                                markerStore[saveData.mangrove_id] = newMarker;
                                treeAreaStore[saveData.mangrove_id] = treeArea;

                                // Create popup content with working edit/delete buttons
                                const popupContent = `
                                    <div class="marker-popup">
                                        <h4>Tree Details</h4>
                                        <table>
                                            <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                                                <td>${saveData.mangrove_id}</td></tr>
                                            <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                                                <td>${e.latlng.lat.toFixed(5)}, ${e.latlng.lng.toFixed(5)}</td></tr>
                                            <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                                                <td>${areaNo}</td></tr>
                                            <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                                                <td>${mangroveType}</td></tr>
                                            <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                                                <td><span class="status-${status.toLowerCase()}">${status}</span></td></tr>
                                        </table>
                                        <div class="marker-actions" style="margin-top:0.8rem;">
                                            <button class="btn btn-warning btn-sm" onclick="editMarker('${saveData.mangrove_id}')">Edit</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteMarker('${saveData.mangrove_id}')">Delete</button>
                                        </div>
                                    </div>
                                `;

                                newMarker.bindPopup(popupContent).openPopup();

                                // Connect the area to the marker
                                treeArea.bindPopup(popupContent);

                                if (typeof areaManager !== 'undefined' && typeof areaManager.expandAreaForTree === 'function') {
                                    try {
                                        await areaManager.expandAreaForTree(e.latlng);
                                    } catch (error) {
                                        console.error("Area expansion error:", error);
                                    }
                                }
                            } else {
                                throw new Error(saveData.message || 'Failed to save marker');
                            }
                        } catch (error) {
                            console.error("Error:", error);
                            alert("Operation failed: " + error.message);
                        }
                    });

                    L.DomEvent.on(formDiv.querySelector('#cancelMarker'), 'click', () => {
                        this.map.closePopup();
                    });

                    return false;
                } else {
                    if (!this.editMode) {
                        const popup = L.popup()
                            .setLatLng(e.latlng)
                            .setContent(popupContent)
                            .openOn(this.map);

                        popup.on('add', () => {
                            const popupElement = popup.getElement();
                            popupElement.querySelector('.edit-area')?.addEventListener('click', (evt) => {
                                evt.preventDefault();
                                this.showEditForm(feature, layer);
                            });
                        });
                    } else {
                        this.selectArea(layer, feature);
                    }
                }
            });

            // Store the popup content on the layer for later use
            layer._popupContent = popupContent;

            // display the total area in the map
            this.updateTotalArea();
        });
    }

    async loadAndDisplayCityBoundary(cityName) {
        try {
            // Clear any existing boundary layer
            if (this.cityBoundaryLayer) {
                this.map.removeLayer(this.cityBoundaryLayer);
                this.cityBoundaryLayer = null;
            }

            // Load city boundaries data
            const response = await fetch('bataancm1.geojson');
            const data = await response.json();
            
            // Find the selected city's boundary
            const cityFeature = data.features.find(feature => 
                feature.properties.city_municipality === cityName
            );
            
            if (cityFeature) {
                // Store the current boundary
                this.currentCityBoundary = cityFeature;
                
                // Create and display the boundary layer with pulsing effect
                this.cityBoundaryLayer = L.geoJSON(cityFeature, {
                    style: {
                        color: '#007bff',
                        weight: 2,
                        fillColor: '#007bff',
                        fillOpacity: 0.1
                    },
                    className: 'pulsing-boundary'
                }).addTo(this.map);
                
                // Get the bounds of the city
                const bounds = this.cityBoundaryLayer.getBounds();
                
                // Zoom to the boundary with some padding
                this.map.fitBounds(bounds, { padding: [50, 50] });
                
                // Return the center point of the bounds
                return bounds.getCenter();
            } else {
                console.warn(`No boundaries found for ${cityName}`);
                return null;
            }
        } catch (error) {
            console.error('Error loading city boundaries:', error);
            return null;
        }
    }

    // Add this method to check if a drawn area is within boundaries
    isAreaWithinBoundaries(layer) {
        if (!this.currentCityBoundary) return true; // No boundary set
        
        const drawnPolygon = turf.polygon(layer.toGeoJSON().geometry.coordinates);
        const cityPolygon = turf.polygon(this.currentCityBoundary.geometry.coordinates);
        
        // Check if the drawn polygon is completely within the city boundary
        return turf.booleanWithin(drawnPolygon, cityPolygon);
    }

    isPointInUserBoundary(latlng) {
        // Admins can edit/delete anything
        if (this.userRole === 'Administrator') {
            return true;
        }
        
        // Non-Barangay Officials can edit/delete anything
        if (this.userRole !== 'Barangay Official') {
            return true;
        }
        
        // If no city boundaries loaded, restrict editing
        if (!this.cityBoundaries) {
            console.warn(`No boundaries loaded for ${this.userCity}`);
            return false;
        }
        
        // Check if the point is within the user's city boundaries
        const point = turf.point([latlng.lng, latlng.lat]);
        const polygon = turf.polygon(this.cityBoundaries.geometry.coordinates);
        return turf.booleanPointInPolygon(point, polygon);
    }

    shouldShowEditButtons(feature) {
        // Admins can edit everything
        if (this.userRole === 'Administrator') {
            return true;
        }
        
        // Non-Barangay Officials can edit everything
        if (this.userRole !== 'Barangay Official') {
            return true;
        }
        
        // If no city boundaries loaded, restrict editing
        if (!this.cityBoundaries) {
            return false;
        }
        
        // For polygons, check if the centroid is within the boundary
        try {
            const centroid = turf.centroid(turf.polygon(feature.geometry.coordinates));
            return this.isPointInUserBoundary({
                lat: centroid.geometry.coordinates[1],
                lng: centroid.geometry.coordinates[0]
            });
        } catch (e) {
            console.error("Error calculating centroid:", e);
            return false;
        }
    }

    updateTotalArea() {
        let totalArea = 0;
        this.areas.features.forEach(feature => {
            if (feature.properties.area_m2) {
                totalArea += feature.properties.area_m2;
            }
        });
        // Update the total area display
        const totalAreaElem = document.getElementById('total-area');
        totalAreaElem.textContent = totalArea.toLocaleString() + ' m²';

        // Add 'active' class to stat-card if totalArea is set (nonzero)
        const statCard = totalAreaElem.closest('.stat-card');
        if (statCard) {
            if (totalArea > 0) {
                statCard.classList.add('active');
            } else {
                statCard.classList.remove('active');
            }
        }
        return totalArea;
    }

    setupEventListeners() {
        // Only proceed if admin controls exist
        const addBtn = document.getElementById('addAreaBtn');
        const editBtn = document.getElementById('editAreasBtn');
        const deleteBtn = document.getElementById('deleteAreaBtn');
        const saveBtn = document.getElementById('saveAreasBtn');
        
        if (!addBtn || !editBtn || !deleteBtn || !saveBtn) {
            return; // Exit if controls don't exist (non-admin)
        }

        // Add Area Button
        addBtn.addEventListener('click', () => {
            this.startDrawingNewArea();
        });

        // Edit Areas Button
        editBtn.addEventListener('click', () => {
            this.toggleEditMode();
        });

        // Delete Area Button
        deleteBtn.addEventListener('click', () => {
            this.deleteSelectedArea();
        });

        // Save Areas Button
        saveBtn.addEventListener('click', () => {
            this.saveAreas();
        });

        const markerCheckbox = document.getElementById('addMarkerCheckbox');
        if (markerCheckbox) {
            markerCheckbox.addEventListener('change', (e) => {
                this.updateButtonStates();
            });
        }
    }

    startDrawingNewArea() {
        this.exitEditMode();
        this.addingArea = true;
        this.updateButtonStates();
        
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
            <div class="form-group">
                <label>Barangays (Select all that apply):</label>
                <div id="barangayCheckboxContainer" class="checkbox-container" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 5px;">
                    <div class="loading-placeholder">Select a city/municipality first</div>
                </div>
            </div>
            <div class="form-buttons">
                <button id="startDrawingBtn" class="btn btn-primary">Start Drawing</button>
                <button id="cancelDrawingBtn" class="btn btn-secondary">Cancel</button>
            </div>
        `;
        
        const popup = L.popup({
                closeButton: false,
                autoClose: false, 
                closeOnClick: false 
            })
            .setLatLng(this.map.getCenter())
            .setContent(formDiv)
            .openOn(this.map);
        
        // Add event listener for city selection change
        const citySelect = formDiv.querySelector('#cityMunicipalityInput');
        citySelect.addEventListener('change', async (e) => {
            if (e.target.value) {
                const center = await this.loadAndDisplayCityBoundary(e.target.value);
                if (center) {
                    // Reposition the popup to the center of the selected city
                    popup.setLatLng(center);
                }
                // Load barangays for the selected city
                await this.loadBarangaysForArea(e.target.value, formDiv.querySelector('#barangayCheckboxContainer'));
            } else {
                // Clear barangays if no city selected
                formDiv.querySelector('#barangayCheckboxContainer').innerHTML = '<div class="loading-placeholder">Select a city/municipality first</div>';
            }
        });
        
        L.DomEvent.on(formDiv.querySelector('#startDrawingBtn'), 'click', async () => {
            const areaNo = formDiv.querySelector('#areaNoInput').value;
            const cityMunicipality = formDiv.querySelector('#cityMunicipalityInput').value;
            
            // Get selected barangays
            const selectedBarangays = [];
            const checkboxes = formDiv.querySelectorAll('#barangayCheckboxContainer input[type="checkbox"]:checked');
            checkboxes.forEach(cb => selectedBarangays.push(cb.value));
            
            if (!areaNo || !cityMunicipality) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (selectedBarangays.length === 0) {
                alert('Please select at least one barangay');
                return;
            }
            
            this.currentAreaDetails = {
                area_no: areaNo,
                city_municipality: cityMunicipality,
                barangays: selectedBarangays
            };
            
            this.map.closePopup();
            this.initializeDrawing();
        });
        
        L.DomEvent.on(formDiv.querySelector('#cancelDrawingBtn'), 'click', () => {
            // Remove the boundary layer if it exists
            if (this.cityBoundaryLayer) {
                this.map.removeLayer(this.cityBoundaryLayer);
                this.cityBoundaryLayer = null;
            }
            this.map.closePopup();
            this.addingArea = false;
            this.updateButtonStates();
        });
    }

    initializeDrawing() {
        // Close any existing popup
        this.map.closePopup();
        
        // Initialize the draw control
        const drawControl = new L.Draw.Polygon(this.map, {
            shapeOptions: {
                color: '#3d9970',
                fillOpacity: 0.5
            },
            guideLayers: this.cityBoundaryLayer ? [this.cityBoundaryLayer] : []
        });
        
        drawControl.enable();
        
        // Handle when a polygon is drawn
        this.map.once('draw:created', (e) => {
            const layer = e.layer;
            
            // Validate the drawn area is within boundaries
            if (this.currentCityBoundary && !this.isAreaWithinBoundaries(layer)) {
                alert('The area must be completely within the selected city/municipality boundaries');
                this.map.removeLayer(layer);
                drawControl.disable();
                return;
            }
            
            this.addingArea = false;
            this.updateButtonStates();
            this.addNewArea(layer);
            drawControl.disable();
            
            // Remove the boundary layer after successful drawing
            if (this.cityBoundaryLayer) {
                this.map.removeLayer(this.cityBoundaryLayer);
                this.cityBoundaryLayer = null;
            }
        });
        
        this.map.once('draw:drawstop', () => {
            this.addingArea = false;
            this.updateButtonStates();
            
            // Remove the boundary layer if drawing was canceled
            if (this.cityBoundaryLayer) {
                this.map.removeLayer(this.cityBoundaryLayer);
                this.cityBoundaryLayer = null;
            }
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

        await this.logActivity(
            'add_area',
            newArea.properties.area_no,
            newArea.properties.id,
            `Added new mangrove area (${newArea.properties.area_no}) with size ${newArea.properties.area_m2} m²`
        );
        this.renderAreas();
    }

    findIntersectingAreas(geoJSONOrLatLng) {
        let geometry;
        
        if (geoJSONOrLatLng.geometry) {
            // It's a GeoJSON feature
            geometry = geoJSONOrLatLng.geometry;
        } else if (geoJSONOrLatLng.lat && geoJSONOrLatLng.lng) {
            // It's a LatLng object - convert to point
            geometry = {
                type: "Point",
                coordinates: [geoJSONOrLatLng.lng, geoJSONOrLatLng.lat]
            };
        } else {
            // Assume it's a geometry object
            geometry = geoJSONOrLatLng;
        }

        return this.areas.features.filter(existingArea => {
            try {
                const existingPolygon = turf.polygon(existingArea.geometry.coordinates);
                
                if (geometry.type === "Point") {
                    // Check if point is inside polygon
                    return turf.booleanPointInPolygon(
                        turf.point(geometry.coordinates),
                        existingPolygon
                    );
                } else {
                    // Check polygon intersection
                    const newPolygon = turf.polygon(geometry.coordinates);
                    return turf.booleanIntersects(existingPolygon, newPolygon);
                }
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
                    
                    await this.logActivity(
                        'merge_area',
                        newAreaName,
                        oldestArea.properties.id,
                        `Merged ${intersectingAreas.length + 1} areas into ${newAreaName} (${mergedAreaSize} m²)`
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
        // Only proceed if we're admin
        if (!this.isAdmin) return;

        this.editMode = !this.editMode;
        this.addingArea = false;
        
        const editBtn = document.getElementById('editAreasBtn');
        if (editBtn) {
            if (this.editMode) {
                editBtn.classList.add('active');
                alert("Edit mode: Click on an area to select it, then you can delete or edit its vertices");
            } else {
                editBtn.classList.remove('active');
                this.exitEditMode();
            }
        }
        
        this.updateButtonStates();
    }

    editAreaDetails(areaId) {
        const areaLayer = this.areaStore[areaId];
        if (!areaLayer) {
            alert("Area not found!");
            return;
        }
        
        // Check permissions
        if (!this.shouldShowEditButtons(areaLayer.feature)) {
            alert("You are not authorized to edit areas outside your city/municipality.");
            return;
        }

        const feature = areaLayer.feature;
        
        // Create a form for editing details
        const formDiv = L.DomUtil.create('div', 'area-details-form');
        formDiv.innerHTML = `
            <h3>Edit Area Details</h3>
            <div class="form-group">
                <label>Area Number:</label>
                <input type="text" id="editAreaNo" class="form-control" 
                    value="${feature.properties.area_no || ''}">
            </div>
            <div class="form-group">
                <label>City/Municipality:</label>
                <select id="editCityMunicipality" class="form-control">
                    <option value="">Select City/Municipality</option>
                    <option value="Abucay" ${feature.properties.city_municipality === 'Abucay' ? 'selected' : ''}>Abucay</option>
                    <option value="Bagac" ${feature.properties.city_municipality === 'Bagac' ? 'selected' : ''}>Bagac</option>
                    <option value="Balanga" ${feature.properties.city_municipality === 'Balanga' ? 'selected' : ''}>Balanga</option>
                    <option value="Dinalupihan" ${feature.properties.city_municipality === 'Dinalupihan' ? 'selected' : ''}>Dinalupihan</option>
                    <option value="Hermosa" ${feature.properties.city_municipality === 'Hermosa' ? 'selected' : ''}>Hermosa</option>
                    <option value="Limay" ${feature.properties.city_municipality === 'Limay' ? 'selected' : ''}>Limay</option>
                    <option value="Mariveles" ${feature.properties.city_municipality === 'Mariveles' ? 'selected' : ''}>Mariveles</option>
                    <option value="Morong" ${feature.properties.city_municipality === 'Morong' ? 'selected' : ''}>Morong</option>
                    <option value="Orani" ${feature.properties.city_municipality === 'Orani' ? 'selected' : ''}>Orani</option>
                    <option value="Orion" ${feature.properties.city_municipality === 'Orion' ? 'selected' : ''}>Orion</option>
                    <option value="Pilar" ${feature.properties.city_municipality === 'Pilar' ? 'selected' : ''}>Pilar</option>
                    <option value="Samal" ${feature.properties.city_municipality === 'Samal' ? 'selected' : ''}>Samal</option>
                </select>
            </div>
            <div class="form-group">
                <label>Barangays (Select all that apply):</label>
                <div id="editBarangayCheckboxContainer" class="checkbox-container" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 5px;">
                    <div class="loading-placeholder">Loading barangays...</div>
                </div>
            </div>
            <div class="form-buttons">
                <button id="saveAreaDetails" class="btn btn-primary">Save</button>
                <button id="cancelAreaDetails" class="btn btn-secondary">Cancel</button>
            </div>
        `;

        const popup = L.popup()
            .setLatLng(areaLayer.getBounds().getCenter())
            .setContent(formDiv)
            .openOn(this.map);

        // Load existing barangays if city is already selected
        const citySelect = formDiv.querySelector('#editCityMunicipality');
        const barangayContainer = formDiv.querySelector('#editBarangayCheckboxContainer');
        
        if (feature.properties.city_municipality) {
            this.loadBarangaysForAreaEdit(
                feature.properties.city_municipality, 
                barangayContainer, 
                feature.properties.barangays || []
            );
        }
        
        // Add event listener for city change
        citySelect.addEventListener('change', async (e) => {
            if (e.target.value) {
                await this.loadBarangaysForAreaEdit(e.target.value, barangayContainer, []);
            } else {
                barangayContainer.innerHTML = '<div class="loading-placeholder">Select a city/municipality first</div>';
            }
        });

        L.DomEvent.on(formDiv.querySelector('#saveAreaDetails'), 'click', () => {
            const newAreaNo = formDiv.querySelector('#editAreaNo').value;
            const newCityMunicipality = formDiv.querySelector('#editCityMunicipality').value;
            
            // Get selected barangays
            const selectedBarangays = [];
            const checkboxes = formDiv.querySelectorAll('#editBarangayCheckboxContainer input[type="checkbox"]:checked');
            checkboxes.forEach(cb => selectedBarangays.push(cb.value));
            
            if (!newAreaNo || !newCityMunicipality) {
                alert('Please fill in all required fields');
                return;
            }
            
            if (selectedBarangays.length === 0) {
                alert('Please select at least one barangay');
                return;
            }

            const oldAreaNo = feature.properties.area_no;
            const oldCity = feature.properties.city_municipality;
            const oldBarangays = feature.properties.barangays || [];

            // Update properties in the main areas collection
            const areaIndex = this.areas.features.findIndex(f => f.properties.id === feature.properties.id);
            if (areaIndex !== -1) {
                this.areas.features[areaIndex].properties.area_no = newAreaNo;
                this.areas.features[areaIndex].properties.city_municipality = newCityMunicipality;
                this.areas.features[areaIndex].properties.barangays = selectedBarangays;
                this.areas.features[areaIndex].properties.date_updated = new Date().toISOString();
            }
            
            // Also update the feature object that was passed in
            feature.properties.area_no = newAreaNo;
            feature.properties.city_municipality = newCityMunicipality;
            feature.properties.barangays = selectedBarangays;
            feature.properties.date_updated = new Date().toISOString();
            
            this.logActivity(
                'edit_area_details',
                newAreaNo,
                feature.properties.id,
                `Updated area details from ${oldAreaNo} (${oldCity}) to ${newAreaNo} (${newCityMunicipality}). Barangays: ${selectedBarangays.join(', ')}`
            );

            this.map.closePopup();
            this.hasUnsavedChanges = true;
            this.updateButtonStates();
            
            alert("Area details updated successfully! Don't forget to save your changes.");
            this.renderAreas();
        });

        L.DomEvent.on(formDiv.querySelector('#cancelAreaDetails'), 'click', () => {
            this.map.closePopup();
        });
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
            
            // Store the original popup content
            editableLayer._popupContent = layer._popupContent;

            // Make the layer editable
            editableLayer.eachLayer((layer) => {
                if (layer.editing) {
                    layer.editing.enable();
                } else if (layer instanceof L.Polygon) {
                    layer.editing = new L.Handler.PolyEdit(this.map, layer);
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
                
                // Update geometry and calculated properties
                feature.geometry = editedGeoJSON.geometry;
                feature.properties.area_m2 = editedAreaSize;
                feature.properties.area_ha = editedAreaHectares;
                feature.properties.date_updated = new Date().toISOString();
                
                // Update the original layer's geometry
                if (layer.setLatLngs) {
                    const latLngs = editableLayer.getLayers()[0].getLatLngs();
                    layer.setLatLngs(latLngs);
                }
                
                // Update the popup content with new size
                const updatedPopupContent = `
                    <div class="mangrove-popup">
                        <h4>${feature.properties.area_no || 'Mangrove Area'}</h4>
                        <table>
                            <tr><th>Location:</th><td>${feature.properties.city_municipality || 'N/A'}</td></tr>
                            <tr><th>Size:</th><td>${editedAreaSize.toLocaleString()} m² (${editedAreaHectares} ha)</td></tr>
                            <tr><th>Created:</th><td>${feature.properties.date_created_display || 'N/A'}</td></tr>
                            <tr><th>Updated:</th><td>${new Date().toLocaleString()}</td></tr>
                        </table>
                        <div class="popup-actions">
                            <button class="btn btn-sm btn-primary" onclick="areaManager.editAreaDetails('${feature.properties.id}')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="areaManager.deleteArea('${feature.properties.id}')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;

                layer.setPopupContent(updatedPopupContent);
                layer._popupContent = updatedPopupContent;
                
                this.hasUnsavedChanges = true;
            });
        }

        // Add this method for deleting areas
        async deleteArea(areaId) {
            try {
                // Disable UI during operation
                this.disableUI();
                
                const areaLayer = this.areaStore[areaId];
                if (!areaLayer) {
                    alert("Area not found!");
                    this.enableUI();
                    return false;
                }
                
                // Get area details before deletion
                const areaDetails = areaLayer.feature.properties;
                const areaNo = areaDetails.area_no || 'Unnamed Area';
                const cityMunicipality = areaDetails.city_municipality || 'Unknown Location';
                const areaSize = areaDetails.area_m2 || 0;
                
                if (!confirm(`Are you sure you want to delete area ${areaNo} (${cityMunicipality}, ${areaSize} m²)?`)) {
                    this.enableUI();
                    return false;
                }

                // Set unsaved changes flag before logging (so the activity gets queued properly)
                this.hasUnsavedChanges = true;

                // Log the activity before deletion (queue it since we now have unsaved changes)
                const logSuccess = await this.logActivity(
                    'delete_area',
                    areaNo, // identifier (area_no)
                    areaId, // area ID
                    `Deleted mangrove area: ${areaNo} in ${cityMunicipality} (${areaSize} m²)`,
                    true // Always queue delete activities for batch processing
                );

                if (!logSuccess) {
                    console.error('Failed to log delete activity');
                    // Optionally notify user or handle failed logging
                }

                // Remove from map and store
                this.areas.features = this.areas.features.filter(
                    area => area.properties.id !== areaId
                );
                areaLayer.remove();
                delete this.areaStore[areaId];

                // If this was the currently selected area, clear selection
                if (this.currentArea && this.currentArea.feature.properties.id === areaId) {
                    this.currentArea = null;
                    this.drawnItems.clearLayers();
                }

                this.updateButtonStates();
                
                // Re-enable UI after operation
                this.enableUI();
                
                alert("Area deleted successfully!");
                return true;
            } catch (error) {
                console.error('Error in deleteArea:', error);
                // Re-enable UI on error
                this.enableUI();
                this.updateButtonStates();
                alert("An error occurred while deleting the area");
                return false;
            }
        }

    async deleteSelectedArea() {
        if (this.currentArea) {
            const feature = this.currentArea.feature;
            const areaId = feature.properties.id;
            const areaNo = feature.properties.area_no || 'Unnamed Area';
            const cityMunicipality = feature.properties.city_municipality || 'Unknown Location';
            const areaSize = feature.properties.area_m2 || 0;
            
            if (!confirm(`Are you sure you want to delete area ${areaNo} (${cityMunicipality}, ${areaSize} m²)?`)) {
                return false;
            }

            // Set unsaved changes flag before logging (so the activity gets queued properly)
            this.hasUnsavedChanges = true;

            // Log the activity before deletion (queue it since we now have unsaved changes)
            const logSuccess = await this.logActivity(
                'delete_area',
                areaNo, // identifier (area_no)
                areaId, // area ID
                `Deleted mangrove area: ${areaNo} in ${cityMunicipality} (${areaSize} m²)`,
                true // Always queue delete activities for batch processing
            );

            if (!logSuccess) {
                console.error('Failed to log delete activity');
            }

            this.areas.features = this.areas.features.filter(area => area.properties.id !== areaId);
            this.renderAreas();
            this.currentArea = null;
            this.drawnItems.clearLayers();
            this.updateButtonStates();
            
            alert("Area deleted successfully!");
            return true;
        } else {
            alert("No area selected. Enter edit mode and click an area first.");
            return false;
        }
    }

    exitEditMode() {
        this.editMode = false;
        this.addingArea = false;
        
        // Only try to modify the edit button if it exists
        const editBtn = document.getElementById('editAreasBtn');
        if (editBtn) {
            editBtn.classList.remove('active');
        }
        
        this.drawnItems.clearLayers();
        this.currentArea = null;
        
        // Only update button states if we're admin (buttons exist)
        if (this.isAdmin) {
            this.updateButtonStates();
        }
    }

    async saveAreas() {
        try {
            // Disable UI during save operation
            this.disableUI();
            
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
                // Flush all pending activity logs first
                await this.flushPendingLogs();
                
                // Update barangay profiles based on new area data
                try {
                    const profileUpdateResponse = await fetch('update_barangay_profiles.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    
                    if (profileUpdateResponse.ok) {
                        const profileResult = await profileUpdateResponse.json();
                        console.log('Barangay profiles updated:', profileResult);
                    }
                } catch (profileError) {
                    console.warn('Failed to update barangay profiles:', profileError);
                }
                
                this.hasUnsavedChanges = false;
                
                // Only call exitEditMode if we're in edit mode
                if (this.editMode) {
                    this.exitEditMode();
                } else {
                    // For non-admin users or when not in edit mode,
                    // just ensure the state is clean
                    this.drawnItems.clearLayers();
                    this.currentArea = null;
                }
                
                // If we have a pending navigation URL, redirect there after saving
                if (this.pendingNavigationUrl) {
                    const targetUrl = this.pendingNavigationUrl;
                    this.pendingNavigationUrl = null;
                    window.location.href = targetUrl;
                    return true;
                }
                
                // Re-enable UI and set proper button states after successful save
                this.enableUI();
                this.updateButtonStates();
                
                alert("Areas saved successfully!");
                return true;
            }
        } catch (error) {
            console.error("Save failed:", error);
            // Re-enable UI on error
            this.enableUI();
            this.updateButtonStates();
        }
        
        alert("Failed to save areas");
        return false;
    }


    async expandAreaForTree(latlng) {
        // Create a 100 sq meter square centered at the pin's coordinates
        const side = Math.sqrt(100); // 10 meters
        const halfSide = side / 2; // 5 meters

        // Calculate the four corners of the square using turf's destination
        const center = turf.point([latlng.lng, latlng.lat]);
        // Directions: 0 = north, 90 = east, 180 = south, 270 = west
        const north = turf.destination(center, halfSide, 0, { units: 'meters' });
        const south = turf.destination(center, halfSide, 180, { units: 'meters' });
        const east = turf.destination(center, halfSide, 90, { units: 'meters' });
        const west = turf.destination(center, halfSide, 270, { units: 'meters' });

        // Corners: NW, NE, SE, SW (clockwise)
        const nw = turf.destination(north, halfSide, 270, { units: 'meters' });
        const ne = turf.destination(north, halfSide, 90, { units: 'meters' });
        const se = turf.destination(south, halfSide, 90, { units: 'meters' });
        const sw = turf.destination(south, halfSide, 270, { units: 'meters' });

        const squareCoords = [
            [
                nw.geometry.coordinates,
                ne.geometry.coordinates,
                se.geometry.coordinates,
                sw.geometry.coordinates,
                nw.geometry.coordinates // close the polygon
            ]
        ];

        const treeArea = turf.polygon(squareCoords);

        // Find intersecting mangrove areas
        const intersectingAreas = this.findIntersectingAreas(treeArea);

        if (intersectingAreas.length > 0) {
            // Merge with all intersecting areas
            const merged = await this.mergeTreeAreaWithMangroveAreas(treeArea, intersectingAreas);
            if (merged) {
                this.hasUnsavedChanges = true;
                
                // Log the expansion activity
                const oldestArea = intersectingAreas[0];
                const mergedAreaSize = oldestArea.properties.area_m2;
                await this.logActivity(
                    'expand_area',
                    oldestArea.properties.area_no,
                    oldestArea.properties.id,
                    `Expanded area by merging with tree pin (new size: ${mergedAreaSize} m²)`
                );
                
                await this.saveAreas();
                this.renderAreas();
                return true;
            }
            return false;
        } else {
            // Create a new mangrove area for this tree
            // Try to inherit barangay information from nearest areas
            let nearestBarangays = [];
            let nearestCity = '';
            
            // Find the closest mangrove area to inherit barangays from
            if (this.areas.features.length > 0) {
                const treePoint = turf.point([latlng.lng, latlng.lat]);
                let closestArea = null;
                let minDistance = Infinity;
                
                this.areas.features.forEach(feature => {
                    try {
                        const areaCentroid = turf.centroid(feature);
                        const distance = turf.distance(treePoint, areaCentroid, { units: 'meters' });
                        if (distance < minDistance) {
                            minDistance = distance;
                            closestArea = feature;
                        }
                    } catch (e) {
                        // Skip if centroid calculation fails
                    }
                });
                
                if (closestArea && minDistance < 1000) { // Within 1km
                    nearestCity = closestArea.properties.city_municipality || '';
                    nearestBarangays = closestArea.properties.barangays || [];
                }
            }
            
            const newArea = {
                type: "Feature",
                properties: {
                    area_no: 'TREE-' + Date.now().toString().slice(-6),
                    city_municipality: nearestCity,
                    barangays: nearestBarangays,
                    id: 'treearea_' + Date.now(),
                    area_m2: 100,
                    area_ha: "0.01",
                    date_created: new Date().toISOString(),
                    date_updated: new Date().toISOString(),
                    is_tree_area: true // Mark as tree-generated area
                },
                geometry: treeArea.geometry
            };

            this.areas.features.push(newArea);
            this.hasUnsavedChanges = true;
            
            // Log the new area creation from tree pin
            await this.logActivity(
                'expand_area',
                newArea.properties.area_no,
                newArea.properties.id,
                `Created new mangrove area from tree pin (100 m²)${nearestBarangays.length > 0 ? ' in barangay(s): ' + nearestBarangays.join(', ') : ''}`
            );
            
            await this.saveAreas();
            this.renderAreas();
            return true;
        }
    }

    async mergeTreeAreaWithMangroveAreas(treeArea, mangroveAreas) {
        try {
            // Sort by date (oldest first)
            mangroveAreas.sort((a, b) => 
                new Date(a.properties.date_created) - new Date(b.properties.date_created));
            
            const oldestArea = mangroveAreas[0];
            const otherAreas = mangroveAreas.slice(1);
            
            // Combine all geometries (oldest mangrove area + tree area + other mangrove areas)
            const polygons = [
                turf.polygon(oldestArea.geometry.coordinates),
                turf.polygon(treeArea.geometry.coordinates),
                ...otherAreas.map(area => turf.polygon(area.geometry.coordinates))
            ];
            
            let combined = polygons[0];
            for (let i = 1; i < polygons.length; i++) {
                combined = turf.union(combined, polygons[i]);
                if (!combined) {
                    throw new Error(`Failed to merge with area ${i}`);
                }
            }
            
            // Update the oldest area with merged properties
            const mergedAreaSize = Math.round(turf.area(combined));
            oldestArea.geometry = combined.geometry;
            oldestArea.properties.area_m2 = mergedAreaSize;
            oldestArea.properties.area_ha = (mergedAreaSize / 10000).toFixed(2);
            oldestArea.properties.date_updated = new Date().toISOString();
            
            // Merge barangays from all areas involved
            const allBarangays = new Set();
            
            // Add barangays from the oldest area
            if (oldestArea.properties.barangays) {
                if (Array.isArray(oldestArea.properties.barangays)) {
                    oldestArea.properties.barangays.forEach(b => allBarangays.add(b));
                } else if (typeof oldestArea.properties.barangays === 'string') {
                    oldestArea.properties.barangays.split(',').forEach(b => allBarangays.add(b.trim()));
                }
            }
            
            // Add barangays from other areas
            otherAreas.forEach(area => {
                if (area.properties.barangays) {
                    if (Array.isArray(area.properties.barangays)) {
                        area.properties.barangays.forEach(b => allBarangays.add(b));
                    } else if (typeof area.properties.barangays === 'string') {
                        area.properties.barangays.split(',').forEach(b => allBarangays.add(b.trim()));
                    }
                }
            });
            
            // Update the oldest area with merged barangays
            oldestArea.properties.barangays = Array.from(allBarangays).filter(b => b.length > 0);
            
            // Remove other areas that were merged
            const otherAreaIds = otherAreas.map(a => a.properties.id);
            this.areas.features = this.areas.features.filter(area => 
                !otherAreaIds.includes(area.properties.id)
            );
            
            return true;
        } catch (error) {
            console.error("Error merging tree area:", error);
            return false;
        }
    }
    
    async subtractAreaFromMangrove(pinAreaGeoJSON, mangroveArea) {
        try {
            // Convert to Turf.js polygons
            const mangrovePolygon = turf.polygon(mangroveArea.geometry.coordinates);
            const pinPolygon = turf.polygon(pinAreaGeoJSON.coordinates);
            
            // Calculate the difference (mangrove area minus pin area)
            const difference = turf.difference(mangrovePolygon, pinPolygon);
            
            if (!difference) {
                console.log("No difference after subtraction - deleting the entire area");
                // If the pin area completely covers the mangrove area, remove it
                this.deleteArea(mangroveArea.properties.id);
                return;
            }
            
            // Update the mangrove area's geometry
            mangroveArea.geometry = difference.geometry;
            
            // Recalculate the area size
            const newAreaSize = Math.round(turf.area(difference));
            const newAreaHectares = (newAreaSize / 10000).toFixed(2);
            
            // Update properties
            mangroveArea.properties.area_m2 = newAreaSize;
            mangroveArea.properties.area_ha = newAreaHectares;
            mangroveArea.properties.date_updated = new Date().toISOString();
            
            this.hasUnsavedChanges = true;
            return true;
        } catch (error) {
            console.error("Error subtracting area:", error);
            return false;
        }
    }

    setupBeforeUnloadListener() {
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }

    setupNavigationListeners() {
        // Intercept all navigation attempts
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                if (this.hasUnsavedChanges) {
                    e.preventDefault();
                    this.showNavigationConfirmation(e.target.href);
                }
            });
        });
    }

    showNavigationConfirmation(targetUrl) {
        const modal = document.createElement('div');
        modal.className = 'unsaved-changes-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>Unsaved Changes</h3>
                <p>You have unsaved changes to your mangrove areas. What would you like to do?</p>
                <div class="modal-buttons">
                    <button id="saveAndContinue" class="btn btn-primary">Save and Continue</button>
                    <button id="discardAndContinue" class="btn btn-danger">Discard and Continue</button>
                    <button id="cancelNavigation" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        document.getElementById('saveAndContinue').addEventListener('click', async () => {
            // Store the target URL for redirect after saving
            this.pendingNavigationUrl = targetUrl;
            const success = await this.saveAreas();
            document.body.removeChild(modal);
            
            // The redirect is now handled in saveAreas()
            if (!success) {
                // If save failed, clear the pending URL
                this.pendingNavigationUrl = null;
            }
        });
        
        document.getElementById('discardAndContinue').addEventListener('click', () => {
            this.hasUnsavedChanges = false;
            this.pendingLogs = []; // Clear pending logs since we're discarding
            document.body.removeChild(modal);
            window.location.href = targetUrl;
        });
        
        document.getElementById('cancelNavigation').addEventListener('click', () => {
            document.body.removeChild(modal);
        });
    }

    // Add this to the AreaManager class
    updateButtonStates() {
        const addBtn = document.getElementById('addAreaBtn');
        const editBtn = document.getElementById('editAreasBtn');
        const deleteBtn = document.getElementById('deleteAreaBtn');
        const saveBtn = document.getElementById('saveAreasBtn');
        const markerCheckbox = document.getElementById('addMarkerCheckbox');

        // Reset all buttons to default state first
        addBtn.disabled = false;
        editBtn.disabled = false;
        deleteBtn.disabled = true; // Delete is only active in edit mode
        saveBtn.disabled = !this.hasUnsavedChanges;
        markerCheckbox.disabled = false;

        // Handle special cases
        if (this.addingArea) {
            // When adding an area, disable other controls
            editBtn.disabled = true;
            deleteBtn.disabled = true;
            saveBtn.disabled = true;
            markerCheckbox.disabled = true;
        } 
        else if (this.editMode) {
            // In edit mode, enable delete button
            deleteBtn.disabled = false;
            // Disable other controls
            addBtn.disabled = true;
            markerCheckbox.disabled = true;
        }
        else if (markerCheckbox.checked) {
            // When adding markers, disable area controls
            addBtn.disabled = true;
            editBtn.disabled = true;
            deleteBtn.disabled = true;
            saveBtn.disabled = true;
        }
    }

}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const areaManager = new AreaManager();
    areaManager.init(map);
    areaManager.setupNavigationListeners();
    window.areaManager = areaManager;
});