/**
 * Event Location Manager
 * Handles map-based location selection with auto-fill and cross-barangay detection
 */

class EventLocationManager {
    constructor(options = {}) {
        this.venueInput = options.venueInput || document.getElementById('venue');
        this.barangayInput = options.barangayInput || document.getElementById('barangay');
        this.cityInput = options.cityInput || document.getElementById('city');
        this.latitudeInput = options.latitudeInput || document.getElementById('latitude');
        this.longitudeInput = options.longitudeInput || document.getElementById('longitude');
        this.mapButton = options.mapButton || document.getElementById('map-button');
        this.mapModal = options.mapModal || document.getElementById('map-modal');
        this.crossBarangayToggle = options.crossBarangayToggle || document.getElementById('cross-barangay-toggle');
        this.crossBarangaySection = options.crossBarangaySection || document.getElementById('cross-barangay-section');
        this.venueModeToggle = options.venueModeToggle || document.getElementById('venue-mode-toggle');
        this.venueModeLabel = options.venueModeLabel || document.getElementById('venue-mode-label');
        
        this.userBarangay = options.userBarangay || '';
        this.userCity = options.userCity || '';
        
        this.map = null;
        this.marker = null;
        this.selectedLocation = null;
        this.isManualMode = false;
        
        this.init();
    }
    
    init() {
        // Set initial values from session
        if (this.userBarangay && !this.barangayInput.value) {
            this.barangayInput.value = this.userBarangay;
        }
        if (this.userCity && !this.cityInput.value) {
            this.cityInput.value = this.userCity;
        }
        
        // CRITICAL: Remove any required attributes that might exist in HTML
        console.log('🧹 INIT: Removing any existing required attributes...');
        this.venueInput?.removeAttribute('required');
        this.cityInput?.removeAttribute('required');
        this.barangayInput?.removeAttribute('required');
        this.venueModeToggle?.removeAttribute('required');
        console.log('✅ INIT: All required attributes removed');
        
        // DON'T style inputs - allow manual entry
        // this.styleLocationInputs(); // REMOVED
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Set initial venue mode (automatic by default)
        if (this.venueModeToggle) {
            this.toggleVenueMode();
        }
        
        // Check initial cross-barangay status
        this.checkCrossBarangayStatus();
    }
    
    styleLocationInputs() {
        // Make barangay and city inputs look disabled but keep them functional
        [this.barangayInput, this.cityInput].forEach(input => {
            if (input) {
                input.style.backgroundColor = '#f0f0f0';
                input.style.cursor = 'not-allowed';
                input.setAttribute('readonly', 'true');
                input.setAttribute('title', 'Select venue from map to update');
            }
        });
    }
    
    setupEventListeners() {
        // Venue mode toggle
        if (this.venueModeToggle) {
            this.venueModeToggle.addEventListener('change', () => {
                this.toggleVenueMode();
            });
        }
        
        // Map button click
        if (this.mapButton) {
            this.mapButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.openMapModal();
            });
        }
        
        // Cross-barangay toggle
        if (this.crossBarangayToggle) {
            this.crossBarangayToggle.addEventListener('change', () => {
                this.toggleCrossBarangaySection();
            });
        }
        
        // Listen for manual changes (for edge cases)
        [this.barangayInput, this.cityInput].forEach(input => {
            if (input) {
                input.addEventListener('change', () => {
                    this.checkCrossBarangayStatus();
                });
            }
        });
        
        // Clear coordinates when venue is manually edited (only in manual mode)
        if (this.venueInput) {
            this.venueInput.addEventListener('input', () => {
                // Only clear if user is typing in manual mode (not from map selection)
                if (this.isManualMode && !this.isUpdatingFromMap) {
                    this.latitudeInput.value = '';
                    this.longitudeInput.value = '';
                }
            });
        }
        
        // Initialize flag for tracking map updates
        this.isUpdatingFromMap = false;
    }
    
    toggleVenueMode() {
        this.isManualMode = this.venueModeToggle.checked;
        
        console.log('🔄 ===== VENUE MODE TOGGLE =====');
        console.log('Mode:', this.isManualMode ? '✋ MANUAL' : '🗺️ AUTOMATIC');
        
        const instructionText = document.getElementById('venue-instruction');
        const cityHint = document.getElementById('city-hint');
        const barangayHint = document.getElementById('barangay-hint');
        
        // NUCLEAR OPTION: NEVER EVER use required attributes on these fields!
        // ALWAYS remove them first, regardless of mode
        console.log('🧹 REMOVING all required and readonly attributes from location fields...');
        this.venueInput?.removeAttribute('required');
        this.venueInput?.removeAttribute('readonly');
        this.cityInput?.removeAttribute('required');
        this.cityInput?.removeAttribute('readonly');
        this.barangayInput?.removeAttribute('required');
        this.barangayInput?.removeAttribute('readonly');
        this.venueModeToggle?.removeAttribute('required');
        console.log('✅ All required and readonly attributes REMOVED');
        
        // Use pointer-events and visual styling to control interaction instead
        
        if (this.isManualMode) {
            // Manual mode - enable all inputs (NO required attribute!)
            this.venueInput.placeholder = 'Type venue name manually';
            this.venueInput.style.backgroundColor = '#ffffff';
            this.venueInput.style.pointerEvents = 'auto';
            this.venueInput.style.cursor = 'text';
            
            this.cityInput.style.backgroundColor = '#ffffff';
            this.cityInput.style.pointerEvents = 'auto';
            this.cityInput.style.cursor = 'text';
            
            this.barangayInput.style.backgroundColor = '#ffffff';
            this.barangayInput.style.pointerEvents = 'auto';
            this.barangayInput.style.cursor = 'text';
            
            console.log('✅ Manual mode setup (NO readonly, NO required):');
            console.log('  - Venue readonly:', this.venueInput.hasAttribute('readonly'), '(MUST BE FALSE)');
            console.log('  - Venue required:', this.venueInput.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - City readonly:', this.cityInput.hasAttribute('readonly'), '(MUST BE FALSE)');
            console.log('  - City required:', this.cityInput.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - Barangay readonly:', this.barangayInput.hasAttribute('readonly'), '(MUST BE FALSE)');
            console.log('  - Barangay required:', this.barangayInput.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - Toggle required:', this.venueModeToggle.hasAttribute('required'), '(MUST BE FALSE)');
            
            this.mapButton.style.display = 'block';
            this.mapButton.title = 'Optional: Pin location on map';
            
            this.venueModeLabel.textContent = '(Manual - type in)';
            this.venueModeLabel.style.color = '#2196F3';
            
            if (instructionText) instructionText.style.display = 'block';
            if (cityHint) cityHint.textContent = '(Enter manually)';
            if (barangayHint) barangayHint.textContent = '(Enter manually)';
            
        } else {
            // Automatic mode - visually disable but NO readonly/required!
            this.venueInput.placeholder = 'Click map button to select venue';
            this.venueInput.style.backgroundColor = '#f7fafc';
            this.venueInput.style.pointerEvents = 'none'; // Prevent clicking/typing
            this.venueInput.style.cursor = 'not-allowed';
            
            this.cityInput.style.backgroundColor = '#f7fafc';
            this.cityInput.style.pointerEvents = 'none';
            this.cityInput.style.cursor = 'not-allowed';
            
            this.barangayInput.style.backgroundColor = '#f7fafc';
            this.barangayInput.style.pointerEvents = 'none';
            this.barangayInput.style.cursor = 'not-allowed';
            
            console.log('✅ Automatic mode setup (NO readonly, NO required, using pointer-events):');
            console.log('  - Venue readonly:', this.venueInput.hasAttribute('readonly'), '(MUST BE FALSE)');
            console.log('  - Venue required:', this.venueInput.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - City readonly:', this.cityInput.hasAttribute('readonly'), '(MUST BE FALSE)');
            console.log('  - City required:', this.cityInput.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - Barangay readonly:', this.barangayInput.hasAttribute('readonly'), '(MUST BE FALSE)');
            console.log('  - Barangay required:', this.barangayInput.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - Toggle required:', this.venueModeToggle.hasAttribute('required'), '(MUST BE FALSE)');
            console.log('  - Current values:');
            console.log('    • Venue:', this.venueInput.value || '(empty)');
            console.log('    • City:', this.cityInput.value || '(empty)');
            console.log('    • Barangay:', this.barangayInput.value || '(empty)');
            
            this.mapButton.style.display = 'block';
            this.mapButton.title = 'Select location from map';
            
            this.venueModeLabel.textContent = '(Automatic - from map)';
            this.venueModeLabel.style.color = '#666';
            
            if (instructionText) instructionText.style.display = 'none';
            if (cityHint) cityHint.textContent = '(Auto-filled from map)';
            if (barangayHint) barangayHint.textContent = '(Auto-filled from map)';
        }
        
        // Clear uploaded files when toggle changes (only on create_event page)
        // Check if we're on create_event by looking for the absence of event_id hidden input
        const isCreatePage = !document.querySelector('input[name="event_id"]');
        if (isCreatePage) {
            console.log('📄 Page type: CREATE EVENT - clearing uploaded files');
            this.clearUploadedFiles();
        } else {
            console.log('📄 Page type: EDIT EVENT - keeping uploaded files');
        }
        console.log('===== VENUE MODE TOGGLE END =====\n');
    }
    
    clearUploadedFiles() {
        // Clear file preview container
        const filePreviewContainer = document.getElementById('file-preview-container');
        if (filePreviewContainer) {
            filePreviewContainer.innerHTML = '';
        }
        
        // Reset file input
        const attachmentsInput = document.getElementById('event-attachments');
        if (attachmentsInput) {
            attachmentsInput.value = '';
        }
        
        // Hide file counter
        const fileCounter = document.getElementById('file-counter');
        if (fileCounter) {
            fileCounter.style.display = 'none';
            fileCounter.textContent = '';
        }
        
        // Hide size warning
        const sizeWarning = document.getElementById('size-warning');
        if (sizeWarning) {
            sizeWarning.style.display = 'none';
        }
        
        // Reset total size (this is a global variable in the file upload script)
        // We'll dispatch a custom event to notify the file upload script
        window.dispatchEvent(new CustomEvent('clearFileUploads'));
    }
    
    openMapModal() {
        if (!this.mapModal) return;
        
        console.log('🗺️ ===== OPENING MAP MODAL =====');
        console.log('Current mode:', this.isManualMode ? '✋ MANUAL' : '🗺️ AUTOMATIC');
        
        this.mapModal.style.display = 'block';
        
        // Show/hide location info section based on mode
        const locationInfoSection = document.getElementById('location-info-section');
        if (locationInfoSection) {
            if (this.isManualMode) {
                // Manual mode - hide location info, just show map
                locationInfoSection.style.display = 'none';
                console.log('📋 Location info section: HIDDEN (manual mode)');
            } else {
                // Automatic mode - show location info
                locationInfoSection.style.display = 'block';
                console.log('📋 Location info section: VISIBLE (automatic mode)');
            }
        }
        
        // Initialize map if not already done
        setTimeout(() => {
            if (!this.map) {
                console.log('🗺️ Initializing map for the first time...');
                this.initializeMap();
            } else {
                console.log('🗺️ Map already initialized, refreshing size...');
                this.map.invalidateSize();
            }
        }, 100);
        console.log('===== MAP MODAL OPENED =====\n');
    }
    
    closeMapModal() {
        if (this.mapModal) {
            this.mapModal.style.display = 'none';
        }
    }
    
    initializeMap() {
        const mapContainer = document.getElementById('map-container');
        if (!mapContainer) return;
        
        // Default center: Bataan, Philippines
        const defaultLat = 14.6417;
        const defaultLng = 120.4818;
        
        // Use existing coordinates if available
        const lat = parseFloat(this.latitudeInput?.value) || defaultLat;
        const lng = parseFloat(this.longitudeInput?.value) || defaultLng;
        
        // Initialize Leaflet map
        this.map = L.map('map-container').setView([lat, lng], 13);
        
        // Add OpenStreetMap default tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(this.map);
        
        // Add existing marker if coordinates exist
        if (this.latitudeInput?.value && this.longitudeInput?.value) {
            this.placeMarker(lat, lng);
        }
        
        // Click event to place marker
        this.map.on('click', (e) => {
            this.placeMarker(e.latlng.lat, e.latlng.lng);
            this.reverseGeocode(e.latlng.lat, e.latlng.lng);
        });
        
        // Setup search functionality
        this.setupMapSearch();
        
        // Setup modal controls
        this.setupModalControls();
    }
    
    setupMapSearch() {
        const searchInput = document.getElementById('venue-search');
        const searchButton = document.getElementById('search-venue-btn');
        const searchResults = document.getElementById('search-results');
        
        if (!searchInput || !searchButton) return;
        
        searchButton.addEventListener('click', () => {
            this.searchVenue(searchInput.value);
        });
        
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.searchVenue(searchInput.value);
            }
        });
    }
    
    setupModalControls() {
        const closeBtn = document.querySelector('.close-modal');
        const confirmBtn = document.getElementById('confirm-location');
        const cancelBtn = document.getElementById('cancel-location');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeMapModal());
        }
        
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                if (this.selectedLocation) {
                    this.applySelectedLocation();
                    this.closeMapModal();
                } else {
                    alert('Please select a location on the map first');
                }
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeMapModal());
        }
        
        // Close when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === this.mapModal) {
                this.closeMapModal();
            }
        });
    }
    
    placeMarker(lat, lng) {
        console.log('📍 ===== PLACING MARKER =====');
        console.log('Coordinates:', { lat, lng });
        
        // Remove existing marker
        if (this.marker) {
            console.log('🗑️ Removing existing marker');
            this.map.removeLayer(this.marker);
        }
        
        // Add new marker
        this.marker = L.marker([lat, lng], {
            draggable: true
        }).addTo(this.map);
        console.log('✅ Marker added to map (draggable: true)');
        
        // Handle marker drag
        this.marker.on('dragend', () => {
            const position = this.marker.getLatLng();
            console.log('🔄 Marker dragged to:', { lat: position.lat, lng: position.lng });
            this.reverseGeocode(position.lat, position.lng);
        });
        
        // Center map on marker
        this.map.setView([lat, lng], this.map.getZoom());
        console.log('🎯 Map centered on marker');
        
        // Clear uploaded files on create_event page when location changes
        const isCreatePage = !document.querySelector('input[name="event_id"]');
        if (isCreatePage) {
            console.log('📄 CREATE page detected - clearing uploaded files');
            this.clearUploadedFiles();
        }
        
        // Reverse geocode to get location details
        console.log('🔍 Starting reverse geocoding...');
        this.reverseGeocode(lat, lng);
        console.log('===== MARKER PLACED =====\n');
    }
    
    async searchVenue(query) {
        console.log('🔍 ===== VENUE SEARCH =====');
        console.log('Search query:', query);
        
        if (!query.trim()) {
            console.log('❌ Empty search query');
            alert('Please enter a search term');
            return;
        }
        
        const searchResults = document.getElementById('search-results');
        if (searchResults) {
            searchResults.innerHTML = '<div class="search-loading">Searching...</div>';
        }
        
        try {
            const response = await fetch(`location_geocoding_api.php?action=search_venue&q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.success && data.results.length > 0) {
                this.displaySearchResults(data.results);
            } else {
                if (searchResults) {
                    searchResults.innerHTML = '<div class="search-error">No results found. Try a different search term.</div>';
                }
            }
        } catch (error) {
            console.error('Search error:', error);
            if (searchResults) {
                searchResults.innerHTML = '<div class="search-error">Search failed. Please try again.</div>';
            }
        }
    }
    
    displaySearchResults(results) {
        const searchResults = document.getElementById('search-results');
        if (!searchResults) return;
        
        searchResults.innerHTML = '';
        
        results.forEach(result => {
            const resultItem = document.createElement('div');
            resultItem.className = 'search-result-item';
            resultItem.innerHTML = `
                <div class="result-name">${result.display_name}</div>
                <div class="result-type">${result.type}</div>
            `;
            
            resultItem.addEventListener('click', () => {
                this.placeMarker(result.lat, result.lng);
                this.reverseGeocode(result.lat, result.lng);
                searchResults.innerHTML = '';
            });
            
            searchResults.appendChild(resultItem);
        });
    }
    
    async reverseGeocode(lat, lng) {
        console.log('🌍 ===== REVERSE GEOCODING =====');
        console.log('Coordinates:', { lat, lng });
        
        const locationInfo = document.getElementById('location-info');
        if (locationInfo) {
            locationInfo.innerHTML = '<div class="loading">Loading location details...</div>';
            console.log('📋 Location info display: Loading...');
        }
        
        try {
            console.log('🌐 Calling API: location_geocoding_api.php');
            const response = await fetch(`location_geocoding_api.php?action=reverse_geocode&lat=${lat}&lng=${lng}`);
            const data = await response.json();
            console.log('✅ API Response:', data);
            
            if (data.success) {
                this.selectedLocation = {
                    venue: data.venue,
                    barangay: data.barangay,
                    city_municipality: data.city_municipality,
                    latitude: data.latitude,
                    longitude: data.longitude,
                    matched: data.matched,
                    confidence: data.confidence
                };
                
                console.log('✅ Location data stored:', this.selectedLocation);
                console.log('  - Venue:', this.selectedLocation.venue);
                console.log('  - Barangay:', this.selectedLocation.barangay);
                console.log('  - City:', this.selectedLocation.city_municipality);
                console.log('  - Matched:', this.selectedLocation.matched);
                console.log('  - Confidence:', this.selectedLocation.confidence);
                
                this.displayLocationInfo(data);
            } else {
                console.log('❌ API returned error:', data.error);
                if (locationInfo) {
                    locationInfo.innerHTML = `<div class="error">${data.error}</div>`;
                }
                this.selectedLocation = null;
            }
        } catch (error) {
            console.error('❌ Geocoding error:', error);
            if (locationInfo) {
                locationInfo.innerHTML = '<div class="error">Failed to get location details</div>';
            }
            this.selectedLocation = null;
        }
        console.log('===== REVERSE GEOCODING END =====\n');
    }
    
    displayLocationInfo(data) {
        const locationInfo = document.getElementById('location-info');
        if (!locationInfo) return;
        
        let html = `
            <div class="location-details">
                <div class="location-field">
                    <strong>Venue:</strong> ${data.venue || 'N/A'}
                </div>
                <div class="location-field">
                    <strong>Barangay:</strong> ${data.barangay || 'N/A'}
                </div>
                <div class="location-field">
                    <strong>City/Municipality:</strong> ${data.city_municipality || 'N/A'}
                </div>
                <div class="location-field">
                    <strong>Coordinates:</strong> ${data.latitude.toFixed(6)}, ${data.longitude.toFixed(6)}
                </div>
        `;
        
        if (data.matched) {
            html += `
                <div class="location-status success">
                    <i class="fas fa-check-circle"></i> Matched with database (${Math.round(data.confidence * 100)}% confidence)
                </div>
            `;
        } else {
            html += `
                <div class="location-status warning">
                    <i class="fas fa-exclamation-triangle"></i> ${data.warning || 'Location not found in Bataan database'}
                </div>
            `;
        }
        
        html += '</div>';
        
        locationInfo.innerHTML = html;
    }
    
    applySelectedLocation() {
        console.log('✨ ===== APPLYING SELECTED LOCATION =====');
        
        if (!this.selectedLocation) {
            console.log('❌ No location selected to apply!');
            return;
        }
        
        console.log('📍 Location to apply:', this.selectedLocation);
        console.log('Current mode:', this.isManualMode ? '✋ MANUAL' : '🗺️ AUTOMATIC');
        
        // Set flag to indicate map update
        this.isUpdatingFromMap = true;
        
        // In automatic mode, update all fields
        // In manual mode, only update coordinates (user keeps their manual input)
        if (!this.isManualMode) {
            console.log('🗺️ AUTOMATIC MODE - Updating all fields...');
            
            // Automatic mode - update everything
            if (this.venueInput) {
                const oldValue = this.venueInput.value;
                this.venueInput.value = this.selectedLocation.venue;
                console.log('  ✅ Venue updated:', oldValue, '→', this.venueInput.value);
                console.log('     - readonly:', this.venueInput.hasAttribute('readonly'), '(should be false)');
                console.log('     - required:', this.venueInput.hasAttribute('required'), '(should be false)');
                
                // SAFETY: Remove any required attribute that might have appeared
                this.venueInput.removeAttribute('required');
            }
            if (this.barangayInput) {
                const oldValue = this.barangayInput.value;
                this.barangayInput.value = this.selectedLocation.barangay;
                console.log('  ✅ Barangay updated:', oldValue, '→', this.barangayInput.value);
                console.log('     - readonly:', this.barangayInput.hasAttribute('readonly'), '(should be false)');
                console.log('     - required:', this.barangayInput.hasAttribute('required'), '(should be false)');
                
                // SAFETY: Remove any required attribute that might have appeared
                this.barangayInput.removeAttribute('required');
            }
            if (this.cityInput) {
                const oldValue = this.cityInput.value;
                this.cityInput.value = this.selectedLocation.city_municipality;
                console.log('  ✅ City updated:', oldValue, '→', this.cityInput.value);
                console.log('     - readonly:', this.cityInput.hasAttribute('readonly'), '(should be false)');
                console.log('     - required:', this.cityInput.hasAttribute('required'), '(should be false)');
                
                // SAFETY: Remove any required attribute that might have appeared
                this.cityInput.removeAttribute('required');
            }
        } else {
            console.log('✋ MANUAL MODE - Keeping user input, only updating coordinates');
        }
        
        // Always update coordinates
        console.log('📍 Updating coordinates...');
        if (this.latitudeInput) {
            const oldLat = this.latitudeInput.value;
            this.latitudeInput.value = this.selectedLocation.latitude;
            console.log('  ✅ Latitude:', oldLat, '→', this.latitudeInput.value);
        }
        if (this.longitudeInput) {
            const oldLng = this.longitudeInput.value;
            this.longitudeInput.value = this.selectedLocation.longitude;
            console.log('  ✅ Longitude:', oldLng, '→', this.longitudeInput.value);
        }
        
        // Reset flag after a delay
        setTimeout(() => {
            this.isUpdatingFromMap = false;
            console.log('🔄 Update flag reset');
        }, 500);
        
        // Check cross-barangay status
        console.log('🔍 Checking cross-barangay status...');
        this.checkCrossBarangayStatus();
        
        // Notify area selector that venue coordinates are now set
        if (window.mangroveAreaSelector) {
            const eventTypeModeToggle = document.getElementById('event-type-mode-toggle');
            const isMangroveEvent = !eventTypeModeToggle || !eventTypeModeToggle.checked;
            console.log('🌳 Notifying mangrove area selector (enabled:', isMangroveEvent, ')');
            window.mangroveAreaSelector.setEnabled(isMangroveEvent, true);
        }
        
        // Show success message
        const message = this.isManualMode ? 'Location pinned on map!' : 'Location updated successfully!';
        console.log('✅ Success message:', message);
        this.showSuccessMessage(message);
        
        console.log('===== LOCATION APPLIED =====\n');
    }
    
    checkCrossBarangayStatus() {
        console.log('🔍 ===== CHECKING CROSS-BARANGAY STATUS =====');
        
        const currentBarangay = this.barangayInput?.value.toLowerCase().trim();
        const currentCity = this.cityInput?.value.toLowerCase().trim();
        const userBarangay = this.userBarangay.toLowerCase().trim();
        const userCity = this.userCity.toLowerCase().trim();
        
        console.log('Current location:', { barangay: currentBarangay, city: currentCity });
        console.log('User location:', { barangay: userBarangay, city: userCity });
        
        const isDifferentLocation = (
            (currentBarangay && currentBarangay !== userBarangay) ||
            (currentCity && currentCity !== userCity)
        );
        
        console.log('Is different location?', isDifferentLocation);
        
        if (this.crossBarangayToggle) {
            if (isDifferentLocation) {
                console.log('⚠️ CROSS-BARANGAY EVENT DETECTED');
                // Different location detected
                this.crossBarangayToggle.checked = true;
                this.crossBarangayToggle.required = true;
                this.highlightCrossBarangayToggle(true);
                this.toggleCrossBarangaySection();
                console.log('  - Toggle checked:', this.crossBarangayToggle.checked);
                console.log('  - Section visible: true');
            } else {
                console.log('✅ Same barangay event');
                // Same location - uncheck toggle and clear section
                this.crossBarangayToggle.checked = false;
                this.crossBarangayToggle.required = false;
                this.highlightCrossBarangayToggle(false);
                this.toggleCrossBarangaySection(); // This will hide the section
                this.clearCrossBarangayInputs(); // Clear all inputs
                console.log('  - Toggle checked:', this.crossBarangayToggle.checked);
                console.log('  - Section visible: false');
            }
        }
        console.log('===== CROSS-BARANGAY CHECK END =====\n');
    }
    
    highlightCrossBarangayToggle(highlight) {
        const toggleContainer = this.crossBarangayToggle?.closest('.toggle-container');
        if (!toggleContainer) return;
        
        if (highlight) {
            toggleContainer.classList.add('highlight');
            toggleContainer.setAttribute('title', 'Cross-barangay event detected - attachments required');
        } else {
            toggleContainer.classList.remove('highlight');
            toggleContainer.removeAttribute('title');
        }
    }
    
    toggleCrossBarangaySection() {
        if (!this.crossBarangaySection) return;
        
        if (this.crossBarangayToggle?.checked) {
            this.crossBarangaySection.style.display = 'block';
            // Add hidden inputs for backend processing
            this.addHiddenInputs();
        } else {
            this.crossBarangaySection.style.display = 'none';
            // Remove hidden inputs
            this.removeHiddenInputs();
        }
    }
    
    addHiddenInputs() {
        if (!document.querySelector('input[name="is_cross_barangay"]')) {
            const input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'is_cross_barangay';
            input1.value = '1';
            this.crossBarangaySection.appendChild(input1);
            
            const input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'requires_special_approval';
            input2.value = '1';
            this.crossBarangaySection.appendChild(input2);
        }
    }
    
    removeHiddenInputs() {
        const inputs = this.crossBarangaySection?.querySelectorAll('input[name="is_cross_barangay"], input[name="requires_special_approval"]');
        inputs?.forEach(input => input.remove());
    }
    
    clearCrossBarangayInputs() {
        if (!this.crossBarangaySection) return;
        
        // Clear file inputs
        const fileInputs = this.crossBarangaySection.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.value = '';
        });
        
        // Clear text inputs (authorization letter, notes)
        const textInputs = this.crossBarangaySection.querySelectorAll('input[type="text"], textarea');
        textInputs.forEach(input => {
            input.value = '';
        });
        
        // Clear any preview elements if they exist
        const previews = this.crossBarangaySection.querySelectorAll('.file-preview');
        previews.forEach(preview => {
            preview.innerHTML = '';
        });
    }
    
    showSuccessMessage(message) {
        // Create temporary success notification
        const notification = document.createElement('div');
        notification.className = 'location-success-notification';
        notification.innerHTML = `
            <i class="fas fa-check-circle"></i> ${message}
        `;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Auto-initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Get session values from PHP
    const userBarangay = window.eventFormUserBarangay || '';
    const userCity = window.eventFormUserCity || '';
    
    // Initialize location manager
    window.locationManager = new EventLocationManager({
        userBarangay: userBarangay,
        userCity: userCity
    });
});
