/**
 * Enhanced Event Location System - JavaScript
 * Handles map selection, geocoding, and cross-barangay detection
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        defaultCenter: [14.64852, 120.47318], // Balanga, Bataan
        defaultZoom: 12,
        markerZoom: 16,
        geocodingAPI: 'enhanced_geocoding_api.php',
        userBarangay: window.userBarangay || '',
        userCity: window.userCity || ''
    };

    // State
    let map = null;
    let marker = null;
    let currentLocationData = null;

    // DOM Elements
    const elements = {
        mapButton: document.getElementById('map-button'),
        mapModal: document.getElementById('map-modal'),
        closeModal: document.querySelector('.close-modal'),
        confirmBtn: document.getElementById('confirm-location'),
        venueInput: document.getElementById('venue'),
        barangayInput: document.getElementById('barangay'),
        cityInput: document.getElementById('city'),
        latitudeInput: document.getElementById('latitude'),
        longitudeInput: document.getElementById('longitude'),
        addressSearchInput: document.getElementById('address-search-input'),
        addressSearchBtn: document.getElementById('address-search-btn'),
        searchResults: document.getElementById('search-results'),
        locationInfoPanel: document.getElementById('location-info-panel'),
        coordinateDisplay: document.getElementById('coordinate-display'),
        crossBarangayToggle: document.getElementById('cross-barangay-toggle'),
        crossBarangaySection: document.getElementById('cross-barangay-section'),
        crossBarangayContent: document.getElementById('cross-barangay-content'),
        crossBarangayWarning: document.getElementById('cross-barangay-warning')
    };

    /**
     * Initialize the location system
     */
    function init() {
        // Set user location from session
        CONFIG.userBarangay = document.body.dataset.userBarangay || '';
        CONFIG.userCity = document.body.dataset.userCity || '';

        // Event listeners
        if (elements.mapButton) {
            elements.mapButton.addEventListener('click', openMapModal);
        }

        if (elements.closeModal) {
            elements.closeModal.addEventListener('click', closeMapModal);
        }

        if (elements.confirmBtn) {
            elements.confirmBtn.addEventListener('click', confirmLocation);
        }

        if (elements.addressSearchBtn) {
            elements.addressSearchBtn.addEventListener('click', searchAddress);
        }

        if (elements.addressSearchInput) {
            elements.addressSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchAddress();
                }
            });
        }

        if (elements.crossBarangayToggle) {
            elements.crossBarangayToggle.addEventListener('change', toggleCrossBarangaySection);
        }

        // Close modal on outside click
        window.addEventListener('click', function(e) {
            if (e.target === elements.mapModal) {
                closeMapModal();
            }
        });

        // Initialize cross-barangay section based on toggle
        toggleCrossBarangaySection();
    }

    /**
     * Open map modal
     */
    function openMapModal(e) {
        e.preventDefault();
        elements.mapModal.style.display = 'block';
        setTimeout(initializeMap, 100);
    }

    /**
     * Close map modal
     */
    function closeMapModal() {
        elements.mapModal.style.display = 'none';
        if (map) {
            map.invalidateSize();
        }
    }

    /**
     * Initialize Leaflet map
     */
    function initializeMap() {
        if (map) {
            map.remove();
        }

        // Create map
        map = L.map('map').setView(CONFIG.defaultCenter, CONFIG.defaultZoom);

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }).addTo(map);

        // Check for existing coordinates
        const existingLat = parseFloat(elements.latitudeInput.value);
        const existingLng = parseFloat(elements.longitudeInput.value);

        if (existingLat && existingLng && !isNaN(existingLat) && !isNaN(existingLng)) {
            placeMarker([existingLat, existingLng]);
            map.setView([existingLat, existingLng], CONFIG.markerZoom);
        }

        // Map click event
        map.on('click', function(e) {
            placeMarker([e.latlng.lat, e.latlng.lng]);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });
    }

    /**
     * Place or update marker on map
     */
    function placeMarker(latlng) {
        if (marker) {
            map.removeLayer(marker);
        }

        marker = L.marker(latlng, {
            draggable: true
        }).addTo(map);

        // Update coordinates display
        updateCoordinateDisplay(latlng[0], latlng[1]);

        // Marker drag event
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            updateCoordinateDisplay(pos.lat, pos.lng);
            reverseGeocode(pos.lat, pos.lng);
        });

        // Enable confirm button
        if (elements.confirmBtn) {
            elements.confirmBtn.disabled = false;
        }
    }

    /**
     * Update coordinate display
     */
    function updateCoordinateDisplay(lat, lng) {
        const roundedLat = parseFloat(lat.toFixed(6));
        const roundedLng = parseFloat(lng.toFixed(6));

        if (elements.coordinateDisplay) {
            elements.coordinateDisplay.innerHTML = `
                <strong>Coordinates:</strong> ${roundedLat}, ${roundedLng}
            `;
        }
    }

    /**
     * Reverse geocode coordinates
     */
    function reverseGeocode(lat, lng) {
        showLoading(true);

        fetch(`${CONFIG.geocodingAPI}?action=reverse_geocode&lat=${lat}&lng=${lng}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentLocationData = data;
                    updateLocationInfo(data);
                    checkCrossBarangay(data);
                } else {
                    showError('Unable to determine address for this location');
                }
            })
            .catch(error => {
                console.error('Geocoding error:', error);
                showError('Failed to fetch address. Please try again.');
            })
            .finally(() => {
                showLoading(false);
            });
    }

    /**
     * Update location information panel
     */
    function updateLocationInfo(data) {
        if (!elements.locationInfoPanel) return;

        const barangayConfidence = getConfidenceClass(data.barangay_confidence || 0);
        const cityConfidence = getConfidenceClass(data.city_confidence || 0);

        elements.locationInfoPanel.innerHTML = `
            <h4>📍 Detected Location</h4>
            <div class="location-detail">
                <label>Address:</label>
                <span>${data.formatted_address || 'N/A'}</span>
            </div>
            <div class="location-detail">
                <label>Barangay:</label>
                <span>
                    ${data.barangay || 'Unknown'}
                    <span class="confidence-badge confidence-${barangayConfidence}">
                        ${Math.round((data.barangay_confidence || 0) * 100)}% match
                    </span>
                </span>
            </div>
            <div class="location-detail">
                <label>City/Municipality:</label>
                <span>
                    ${data.city_municipality || 'Unknown'}
                    <span class="confidence-badge confidence-${cityConfidence}">
                        ${Math.round((data.city_confidence || 0) * 100)}% match
                    </span>
                </span>
            </div>
            <div class="location-detail">
                <label>Province:</label>
                <span>${data.province || 'Unknown'}</span>
            </div>
            ${!data.is_bataan ? '<div class="location-detail" style="color: #dc3545;"><strong>⚠️ Warning: Location is outside Bataan province</strong></div>' : ''}
        `;

        elements.locationInfoPanel.style.display = 'block';
    }

    /**
     * Get confidence level class
     */
    function getConfidenceClass(confidence) {
        if (confidence >= 0.8) return 'high';
        if (confidence >= 0.6) return 'medium';
        return 'low';
    }

    /**
     * Check if location is cross-barangay
     */
    function checkCrossBarangay(data) {
        if (!elements.crossBarangayWarning) return;

        const isCrossBarangay = (
            data.barangay && 
            data.city_municipality &&
            CONFIG.userBarangay &&
            CONFIG.userCity &&
            (
                data.barangay.toLowerCase() !== CONFIG.userBarangay.toLowerCase() ||
                data.city_municipality.toLowerCase() !== CONFIG.userCity.toLowerCase()
            )
        );

        if (isCrossBarangay) {
            // Highlight toggle section
            const toggleSection = document.querySelector('.cross-barangay-toggle-section');
            if (toggleSection) {
                toggleSection.classList.add('highlighted');
            }

            // Show warning
            elements.crossBarangayWarning.innerHTML = `
                <p><strong>⚠️ Cross-Barangay Event Detected</strong></p>
                <p>Selected Location: <strong>${data.barangay}, ${data.city_municipality}</strong></p>
                <p>Your Location: <strong>${CONFIG.userBarangay}, ${CONFIG.userCity}</strong></p>
                <p style="margin-top: 8px; color: #ff9800;">
                    <em>Please enable the cross-barangay toggle and attach required documents.</em>
                </p>
            `;
            elements.crossBarangayWarning.style.display = 'block';

            // Auto-enable toggle
            if (elements.crossBarangayToggle && !elements.crossBarangayToggle.checked) {
                elements.crossBarangayToggle.checked = true;
                toggleCrossBarangaySection();
            }
        } else {
            // Remove highlight
            const toggleSection = document.querySelector('.cross-barangay-toggle-section');
            if (toggleSection) {
                toggleSection.classList.remove('highlighted');
            }

            elements.crossBarangayWarning.style.display = 'none';
        }
    }

    /**
     * Confirm location selection
     */
    function confirmLocation() {
        if (!marker || !currentLocationData) {
            alert('Please select a location on the map first');
            return;
        }

        const pos = marker.getLatLng();
        const roundedLat = parseFloat(pos.lat.toFixed(6));
        const roundedLng = parseFloat(pos.lng.toFixed(6));

        // Update form fields
        elements.latitudeInput.value = roundedLat;
        elements.longitudeInput.value = roundedLng;
        elements.venueInput.value = currentLocationData.formatted_address || `Location at ${roundedLat}, ${roundedLng}`;

        // Auto-fill and lock barangay and city
        if (currentLocationData.barangay && currentLocationData.barangay_confidence >= 0.6) {
            elements.barangayInput.value = currentLocationData.barangay;
            elements.barangayInput.classList.add('input-locked');
            elements.barangayInput.readOnly = true;
            elements.barangayInput.closest('.form-group')?.classList.add('locked');
        }

        if (currentLocationData.city_municipality && currentLocationData.city_confidence >= 0.6) {
            elements.cityInput.value = currentLocationData.city_municipality;
            elements.cityInput.classList.add('input-locked');
            elements.cityInput.readOnly = true;
            elements.cityInput.closest('.form-group')?.classList.add('locked');
        }

        // Show success message
        showSuccess('Location selected successfully!');

        // Close modal
        closeMapModal();
    }

    /**
     * Search for address
     */
    function searchAddress() {
        const query = elements.addressSearchInput.value.trim();

        if (!query) {
            alert('Please enter a search term');
            return;
        }

        elements.addressSearchBtn.disabled = true;
        elements.addressSearchBtn.textContent = 'Searching...';
        elements.searchResults.innerHTML = '<div class="search-result-item loading">Searching for locations...</div>';
        elements.searchResults.style.display = 'block';

        fetch(`${CONFIG.geocodingAPI}?action=search_address&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results.length > 0) {
                    displaySearchResults(data.results);
                } else {
                    elements.searchResults.innerHTML = '<div class="search-result-item loading">No results found. Try different keywords.</div>';
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                elements.searchResults.innerHTML = '<div class="search-result-item loading">Search failed. Please try again.</div>';
            })
            .finally(() => {
                elements.addressSearchBtn.disabled = false;
                elements.addressSearchBtn.textContent = 'Search';
            });
    }

    /**
     * Display search results
     */
    function displaySearchResults(results) {
        elements.searchResults.innerHTML = '';

        results.forEach(result => {
            const item = document.createElement('div');
            item.className = 'search-result-item';
            item.textContent = result.display_name;
            item.addEventListener('click', () => selectSearchResult(result));
            elements.searchResults.appendChild(item);
        });
    }

    /**
     * Select a search result
     */
    function selectSearchResult(result) {
        map.setView([result.lat, result.lng], CONFIG.markerZoom);
        placeMarker([result.lat, result.lng]);
        reverseGeocode(result.lat, result.lng);
        elements.searchResults.style.display = 'none';
        elements.addressSearchInput.value = '';
    }

    /**
     * Toggle cross-barangay section
     */
    function toggleCrossBarangaySection() {
        if (!elements.crossBarangayToggle || !elements.crossBarangayContent) return;

        if (elements.crossBarangayToggle.checked) {
            elements.crossBarangayContent.style.display = 'block';
            // Set hidden input
            let hiddenInput = document.getElementById('is_cross_barangay_input');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'is_cross_barangay_input';
                hiddenInput.name = 'is_cross_barangay';
                elements.crossBarangaySection.appendChild(hiddenInput);
            }
            hiddenInput.value = '1';
        } else {
            elements.crossBarangayContent.style.display = 'none';
            const hiddenInput = document.getElementById('is_cross_barangay_input');
            if (hiddenInput) {
                hiddenInput.remove();
            }
        }
    }

    /**
     * Show loading overlay
     */
    function showLoading(show) {
        const loadingOverlay = document.querySelector('.loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.classList.toggle('active', show);
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        alert('Error: ' + message);
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        // You can implement a toast notification here
        console.log('Success:', message);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
