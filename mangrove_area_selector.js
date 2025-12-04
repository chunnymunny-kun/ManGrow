/**
 * Mangrove Area Selector
 * Handles the selection of mangrove areas for events conducted within mangrove areas
 */

class MangroveAreaSelector {
    constructor() {
        this.areaModal = document.getElementById('area-modal');
        this.areaMapButton = document.getElementById('area-map-button');
        this.closeAreaModal = document.querySelector('.close-area-modal');
        this.cancelAreaBtn = document.getElementById('cancel-area');
        this.confirmAreaBtn = document.getElementById('confirm-area');
        this.areaInput = document.getElementById('area-no');
        this.areaSearchInput = document.getElementById('area-search');
        this.searchAreaBtn = document.getElementById('search-area-btn');
        this.clearSearchBtn = document.getElementById('clear-area-search-btn');
        this.areaSearchResults = document.getElementById('area-search-results');
        this.areaInfo = document.getElementById('area-info');
        
        this.venueLatInput = document.getElementById('latitude');
        this.venueLngInput = document.getElementById('longitude');
        
        this.map = null;
        this.mangroveAreas = null;
        this.mangroveLayer = null;
        this.selectedArea = null;
        this.venueMarker = null;
        
        this.init();
    }
    
    init() {
        this.loadMangroveData();
        this.attachEventListeners();
    }
    
    async loadMangroveData() {
        try {
            const response = await fetch('mangroveareas.json');
            this.mangroveAreas = await response.json();
            console.log('Mangrove areas loaded:', this.mangroveAreas.features.length);
        } catch (error) {
            console.error('Error loading mangrove areas:', error);
        }
    }
    
    attachEventListeners() {
        // Open modal
        this.areaMapButton.addEventListener('click', () => this.openAreaModal());
        
        // Close modal
        this.closeAreaModal.addEventListener('click', () => this.closeModal());
        this.cancelAreaBtn.addEventListener('click', () => this.closeModal());
        
        // Confirm selection
        this.confirmAreaBtn.addEventListener('click', () => this.confirmSelection());
        
        // Search functionality
        this.searchAreaBtn.addEventListener('click', () => this.searchAreas());
        this.areaSearchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.searchAreas();
            }
        });
        
        // Clear search
        this.clearSearchBtn.addEventListener('click', () => this.clearSearch());
        
        // Close modal on outside click
        this.areaModal.addEventListener('click', (e) => {
            if (e.target === this.areaModal) {
                this.closeModal();
            }
        });
    }
    
    openAreaModal() {
        // Check if venue location is set
        const venueLat = parseFloat(this.venueLatInput.value);
        const venueLng = parseFloat(this.venueLngInput.value);
        
        if (!venueLat || !venueLng) {
            alert('Please set the venue location first before selecting a mangrove area.');
            return;
        }
        
        this.areaModal.style.display = 'block';
        
        // Initialize map if not already done
        setTimeout(() => {
            if (!this.map) {
                this.initializeMap(venueLat, venueLng);
            } else {
                // Re-center map on venue location
                this.map.setView([venueLat, venueLng], 13);
                this.updateVenueMarker(venueLat, venueLng);
            }
            this.map.invalidateSize();
        }, 100);
    }
    
    initializeMap(centerLat, centerLng) {
        // Create map centered on venue location
        this.map = L.map('area-map-container').setView([centerLat, centerLng], 13);
        
        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(this.map);
        
        // Add venue marker
        this.updateVenueMarker(centerLat, centerLng);
        
        // Load and display mangrove areas
        this.displayMangroveAreas();
    }
    
    updateVenueMarker(lat, lng) {
        // Remove existing venue marker
        if (this.venueMarker) {
            this.map.removeLayer(this.venueMarker);
        }
        
        // Create custom icon for venue
        const venueIcon = L.divIcon({
            className: 'venue-marker-icon',
            html: '<i class="fas fa-map-marker-alt" style="color: #dc3545; font-size: 30px;"></i>',
            iconSize: [30, 30],
            iconAnchor: [15, 30]
        });
        
        // Add venue marker
        this.venueMarker = L.marker([lat, lng], { icon: venueIcon })
            .addTo(this.map)
            .bindPopup('<strong>Event Venue Location</strong>')
            .openPopup();
    }
    
    displayMangroveAreas(filteredFeatures = null) {
        // Remove existing layer
        if (this.mangroveLayer) {
            this.map.removeLayer(this.mangroveLayer);
        }
        
        if (!this.mangroveAreas) {
            console.error('Mangrove areas not loaded yet');
            return;
        }
        
        const featuresToDisplay = filteredFeatures || this.mangroveAreas.features;
        
        // Create GeoJSON layer
        this.mangroveLayer = L.geoJSON(featuresToDisplay, {
            style: (feature) => ({
                fillColor: '#4CAF50',
                weight: 2,
                opacity: 1,
                color: '#2e7d32',
                fillOpacity: 0.4
            }),
            onEachFeature: (feature, layer) => {
                const props = feature.properties;
                const areaNo = props.area_no || 'N/A';
                const city = props.city_municipality || 'N/A';
                const barangays = props.barangays ? props.barangays.join(', ') : 'N/A';
                const areaHa = props.area_ha || 'N/A';
                
                // Popup content
                const popupContent = `
                    <div style="min-width: 200px;">
                        <h4 style="margin: 0 0 10px 0; color: #2e7d32;">
                            <i class="fas fa-leaf"></i> ${areaNo}
                        </h4>
                        <p style="margin: 5px 0;"><strong>City/Municipality:</strong> ${city}</p>
                        <p style="margin: 5px 0;"><strong>Barangay(s):</strong> ${barangays}</p>
                        <p style="margin: 5px 0;"><strong>Area:</strong> ${areaHa} hectares</p>
                    </div>
                `;
                
                layer.bindPopup(popupContent);
                
                // Click handler
                layer.on('click', () => {
                    this.selectArea(feature, layer);
                });
                
                // Hover effects
                layer.on('mouseover', function() {
                    this.setStyle({
                        fillOpacity: 0.7,
                        weight: 3
                    });
                });
                
                layer.on('mouseout', function() {
                    if (this !== mangroveAreaSelector.selectedLayer) {
                        this.setStyle({
                            fillOpacity: 0.4,
                            weight: 2
                        });
                    }
                });
            }
        }).addTo(this.map);
        
        // Fit bounds to show all areas
        if (featuresToDisplay.length > 0) {
            const bounds = this.mangroveLayer.getBounds();
            this.map.fitBounds(bounds, { padding: [50, 50] });
        }
    }
    
    selectArea(feature, layer) {
        // Reset previously selected layer
        if (this.selectedLayer) {
            this.selectedLayer.setStyle({
                fillOpacity: 0.4,
                weight: 2,
                fillColor: '#4CAF50'
            });
        }
        
        // Highlight selected layer
        layer.setStyle({
            fillOpacity: 0.8,
            weight: 4,
            fillColor: '#2196F3',
            color: '#1565c0'
        });
        
        this.selectedLayer = layer;
        this.selectedArea = feature.properties;
        
        // Update info panel
        this.updateAreaInfo(feature.properties);
        
        // Enable confirm button
        this.confirmAreaBtn.disabled = false;
    }
    
    updateAreaInfo(props) {
        const areaNo = props.area_no || 'N/A';
        const city = props.city_municipality || 'N/A';
        const barangays = props.barangays ? props.barangays.join(', ') : 'N/A';
        const areaHa = props.area_ha || 'N/A';
        const areaM2 = props.area_m2 || 'N/A';
        
        this.areaInfo.innerHTML = `
            <div style="padding: 15px;">
                <div style="background: #e8f5e9; padding: 10px; border-radius: 5px; margin-bottom: 10px;">
                    <h3 style="margin: 0; color: #2e7d32; font-size: 1.2em;">
                        <i class="fas fa-leaf"></i> ${areaNo}
                    </h3>
                </div>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <p style="margin: 0;"><strong><i class="fas fa-city"></i> City/Municipality:</strong><br>${city}</p>
                    <p style="margin: 0;"><strong><i class="fas fa-map-marked"></i> Barangay(s):</strong><br>${barangays}</p>
                    <p style="margin: 0;"><strong><i class="fas fa-ruler-combined"></i> Area:</strong><br>${areaHa} hectares (${areaM2} m²)</p>
                </div>
            </div>
        `;
    }
    
    searchAreas() {
        const searchTerm = this.areaSearchInput.value.trim().toLowerCase();
        
        if (!searchTerm) {
            alert('Please enter a search term (barangay or city/municipality)');
            return;
        }
        
        if (!this.mangroveAreas) {
            alert('Mangrove area data is still loading. Please try again.');
            return;
        }
        
        // Filter areas
        const filteredFeatures = this.mangroveAreas.features.filter(feature => {
            const props = feature.properties;
            const city = (props.city_municipality || '').toLowerCase();
            const barangays = props.barangays ? props.barangays.map(b => b.toLowerCase()) : [];
            const areaNo = (props.area_no || '').toLowerCase();
            
            return city.includes(searchTerm) || 
                   barangays.some(b => b.includes(searchTerm)) ||
                   areaNo.includes(searchTerm);
        });
        
        if (filteredFeatures.length === 0) {
            this.areaSearchResults.innerHTML = `
                <div style="padding: 10px; background: #fff3cd; border-radius: 4px; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle"></i> No mangrove areas found for "${searchTerm}"
                </div>
            `;
            return;
        }
        
        // Display filtered areas on map
        this.displayMangroveAreas(filteredFeatures);
        
        // Show results count
        this.areaSearchResults.innerHTML = `
            <div style="padding: 10px; background: #d1f2eb; border-radius: 4px; margin-top: 10px;">
                <i class="fas fa-check-circle"></i> Found ${filteredFeatures.length} area(s) matching "${searchTerm}"
            </div>
        `;
        
        // Show clear button
        this.clearSearchBtn.style.display = 'inline-block';
    }
    
    clearSearch() {
        this.areaSearchInput.value = '';
        this.areaSearchResults.innerHTML = '';
        this.clearSearchBtn.style.display = 'none';
        
        // Reset map to show all areas
        this.displayMangroveAreas();
        
        // Re-center on venue
        const venueLat = parseFloat(this.venueLatInput.value);
        const venueLng = parseFloat(this.venueLngInput.value);
        if (venueLat && venueLng) {
            this.map.setView([venueLat, venueLng], 13);
        }
    }
    
    confirmSelection() {
        if (!this.selectedArea) {
            alert('Please select a mangrove area first');
            return;
        }
        
        // Safely access area_no property
        const areaNo = this.selectedArea.area_no || this.selectedArea['area_no'] || '';
        
        if (!areaNo) {
            alert('Selected area does not have a valid area number');
            return;
        }
        
        // Set the area_no value
        this.areaInput.value = areaNo;
        
        // Close modal
        this.closeModal();
        
        // Show success message
        console.log('Selected area:', areaNo);
    }
    
    closeModal() {
        this.areaModal.style.display = 'none';
        
        // Reset selection highlights
        if (this.selectedLayer) {
            this.selectedLayer.setStyle({
                fillOpacity: 0.4,
                weight: 2,
                fillColor: '#4CAF50'
            });
        }
        
        this.selectedLayer = null;
        this.selectedArea = null;
        
        // Reset info panel
        this.areaInfo.innerHTML = `
            <p style="color: #718096; font-style: italic; text-align: center; padding: 20px;">
                Click on a mangrove area or search to select
            </p>
        `;
        
        // Disable confirm button
        this.confirmAreaBtn.disabled = true;
        
        // Clear search
        this.areaSearchInput.value = '';
        this.areaSearchResults.innerHTML = '';
        this.clearSearchBtn.style.display = 'none';
    }
    
    // Public method to enable/disable area selection based on event type
    setEnabled(enabled, venueSet = false) {
        this.areaMapButton.disabled = !enabled || !venueSet;
        
        const areaInstruction = document.getElementById('area-instruction');
        
        if (!enabled) {
            // Manual input mode (non-mangrove events)
            this.areaInput.value = 'N/A';
            this.areaInput.disabled = true;
            this.areaMapButton.disabled = true;
            if (areaInstruction) {
                areaInstruction.style.display = 'none';
            }
        } else {
            // List selection mode (mangrove area events)
            if (this.areaInput.value === 'N/A') {
                this.areaInput.value = '';
            }
            this.areaInput.disabled = false;
            
            if (!venueSet) {
                this.areaMapButton.disabled = true;
                if (areaInstruction) {
                    areaInstruction.style.display = 'block';
                }
            } else {
                this.areaMapButton.disabled = false;
                if (areaInstruction) {
                    areaInstruction.style.display = 'none';
                }
            }
        }
    }
}

// Initialize on DOM load and make it globally accessible
window.mangroveAreaSelector = null;
document.addEventListener('DOMContentLoaded', function() {
    window.mangroveAreaSelector = new MangroveAreaSelector();
});
