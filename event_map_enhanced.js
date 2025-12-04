/**
 * Enhanced Map Location Selector with Free Geocoding
 * Includes: Address search, auto-fill locked fields, cross-barangay detection
 */

// Global variables
let map, marker;
let selectedLocationData = null;
const sessionBarangay = document.getElementById('user-location')?.textContent.split(',')[0]?.trim() || '';
const sessionCity = document.getElementById('user-location')?.textContent.split(',')[1]?.trim() || '';

document.addEventListener('DOMContentLoaded', function() {
    initMapControls();
});

function initMapControls() {
    const mapButton = document.getElementById('map-button');
    const mapModal = document.getElementById('map-modal');
    const closeModal = document.querySelector('.close-modal');
    const confirmBtn = document.getElementById('confirmLocationBtn');
    const searchBtn = document.querySelector('.map-search-btn');
    
    if (!mapButton || !mapModal) return;
    
    // Open modal
    mapButton.addEventListener('click', function(e) {
        e.preventDefault();
        mapModal.style.display = 'block';
        setTimeout(initMap, 100);
    });
    
    // Close modal
    if (closeModal) {
        closeModal.addEventListener('click', () => closeMapModal());
    }
    window.addEventListener('click', function(event) {
        if (event.target == mapModal) closeMapModal();
    });
    
    // Confirm location
    if (confirmBtn) {
        confirmBtn.addEventListener('click', confirmLocation);
    }
    
    // Search address
    if (searchBtn) {
        searchBtn.addEventListener('click', searchAddress);
    }
    
    // Allow Enter key in search input
    const searchInput = document.getElementById('mapSearchInput');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAddress();
            }
        });
    }
}

function initMap() {
    const mapContainer = document.getElementById('map');
    if (!mapContainer) return;
    
    if (map) {
        map.remove();
    }
    
    // Create map centered on Bataan
    map = L.map('map').setView([14.6417, 120.4818], 11);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Check for existing coordinates
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    
    if (latInput?.value && lngInput?.value) {
        const lat = parseFloat(latInput.value);
        const lng = parseFloat(lngInput.value);
        placeMarker(L.latLng(lat, lng));
        map.setView([lat, lng], 16);
    }
    
    // Add click event
    map.on('click', function(e) {
        placeMarker(e.latlng);
    });
}

function placeMarker(latlng) {
    if (marker) {
        map.removeLayer(marker);
    }
    
    marker = L.marker(latlng, { draggable: true }).addTo(map);
    
    // Update on drag
    marker.on('dragend', function(e) {
        const newLatLng = e.target.getLatLng();
        reverseGeocode(newLatLng.lat, newLatLng.lng);
    });
    
    // Geocode immediately
    reverseGeocode(latlng.lat, latlng.lng);
}

async function reverseGeocode(lat, lng) {
    const locationInfo = document.getElementById('location-info');
    const confirmBtn = document.getElementById('confirmLocationBtn');
    
    if (!locationInfo || !confirmBtn) return;
    
    // Show loading
    locationInfo.style.display = 'block';
    locationInfo.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading location data...</div>';
    confirmBtn.disabled = true;
    
    try {
        const response = await fetch('geocoding_helper_free.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reverse_geocode',
                lat: lat,
                lng: lng
            })
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        const data = await response.json();
        
        if (data.success) {
            selectedLocationData = data;
            updateLocationDisplay(data);
            confirmBtn.disabled = false;
        } else {
            showError(locationInfo, data.error || 'Failed to geocode location');
            confirmBtn.disabled = true;
        }
    } catch (error) {
        console.error('Geocoding error:', error);
        showError(locationInfo, 'Failed to load location data. Please try again.');
        confirmBtn.disabled = true;
    }
}

function updateLocationDisplay(data) {
    const locationInfo = document.getElementById('location-info');
    const crossWarning = document.getElementById('cross-warning-modal');
    
    if (!locationInfo) return;
    
    locationInfo.style.display = 'block';
    locationInfo.innerHTML = `
        <div class="location-info-content">
            <div class="location-item">
                <i class="fas fa-map-pin"></i>
                <div>
                    <strong>Venue</strong>
                    <span>${escapeHtml(data.venue)}</span>
                </div>
            </div>
            <div class="location-item">
                <i class="fas fa-home"></i>
                <div>
                    <strong>Barangay</strong>
                    <span>${escapeHtml(data.matched_barangay)} 
                        <span class="confidence-badge ${getConfidenceClass(data.barangay_confidence)}">
                            ${Math.round(data.barangay_confidence * 100)}% match
                        </span>
                    </span>
                </div>
            </div>
            <div class="location-item">
                <i class="fas fa-city"></i>
                <div>
                    <strong>City/Municipality</strong>
                    <span>${escapeHtml(data.matched_city)}
                        <span class="confidence-badge ${getConfidenceClass(data.city_confidence)}">
                            ${Math.round(data.city_confidence * 100)}% match
                        </span>
                    </span>
                </div>
            </div>
        </div>
    `;
    
    // Check if cross-barangay
    if (crossWarning && sessionBarangay && sessionCity) {
        const isDifferent = data.matched_barangay.toLowerCase() !== sessionBarangay.toLowerCase() || 
                           data.matched_city.toLowerCase() !== sessionCity.toLowerCase();
        
        crossWarning.style.display = isDifferent ? 'flex' : 'none';
        if (isDifferent) {
            locationInfo.appendChild(crossWarning);
        }
    }
}

function confirmLocation() {
    if (!selectedLocationData || !selectedLocationData.success) {
        alert('Please wait for location data to load or select a different location.');
        return;
    }
    
    // Fill form fields
    const venueInput = document.getElementById('venue');
    const barangayInput = document.getElementById('barangay');
    const cityInput = document.getElementById('city');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    
    if (venueInput) venueInput.value = selectedLocationData.venue;
    if (barangayInput) barangayInput.value = selectedLocationData.matched_barangay;
    if (cityInput) cityInput.value = selectedLocationData.matched_city;
    if (latInput) latInput.value = selectedLocationData.lat;
    if (lngInput) lngInput.value = selectedLocationData.lng;
    
    // Check cross-barangay
    checkAndHighlightCrossBarangay(selectedLocationData);
    
    // Close modal
    closeMapModal();
    
    // Show success
    showSuccessToast('Location confirmed successfully!');
}

function checkAndHighlightCrossBarangay(data) {
    const toggleContainer = document.getElementById('cross-barangay-toggle-container');
    const toggleCheckbox = document.getElementById('isCrossBarangay');
    const detectedLocation = document.getElementById('detected-location');
    const container = document.querySelector('.toggle-container');
    
    if (!toggleContainer || !sessionBarangay || !sessionCity) return;
    
    const isDifferent = data.matched_barangay.toLowerCase() !== sessionBarangay.toLowerCase() || 
                       data.matched_city.toLowerCase() !== sessionCity.toLowerCase();
    
    if (isDifferent) {
        // Show toggle
        toggleContainer.style.display = 'block';
        if (detectedLocation) {
            detectedLocation.textContent = `${data.matched_barangay}, ${data.matched_city}`;
        }
        
        // Highlight animation
        if (container) {
            setTimeout(() => {
                container.classList.add('highlight-toggle');
                setTimeout(() => container.classList.remove('highlight-toggle'), 4500);
            }, 100);
        }
        
        // Scroll to toggle
        toggleContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        // Hide toggle
        toggleContainer.style.display = 'none';
        if (toggleCheckbox) toggleCheckbox.checked = false;
    }
}

async function searchAddress() {
    const searchInput = document.getElementById('mapSearchInput');
    if (!searchInput) return;
    
    const query = searchInput.value.trim();
    
    if (!query) {
        alert('Please enter a location to search');
        return;
    }
    
    // Show loading in button
    const searchBtn = document.querySelector('.map-search-btn');
    const originalText = searchBtn.innerHTML;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
    searchBtn.disabled = true;
    
    try {
        const response = await fetch('geocoding_helper_free.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'search_address',
                address: query
            })
        });
        
        const data = await response.json();
        
        if (data.success && map) {
            const latlng = L.latLng(data.lat, data.lng);
            map.setView([data.lat, data.lng], 16);
            placeMarker(latlng);
            
            selectedLocationData = data;
            updateLocationDisplay(data);
            document.getElementById('confirmLocationBtn').disabled = false;
        } else {
            alert('Location not found: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Search error:', error);
        alert('Search failed. Please try again.');
    } finally {
        searchBtn.innerHTML = originalText;
        searchBtn.disabled = false;
    }
}

function closeMapModal() {
    const mapModal = document.getElementById('map-modal');
    if (mapModal) {
        mapModal.style.display = 'none';
    }
    if (map) {
        map.invalidateSize();
    }
}

function getConfidenceClass(confidence) {
    if (confidence >= 0.8) return 'high';
    if (confidence >= 0.6) return 'medium';
    return 'low';
}

function showError(container, message) {
    container.innerHTML = `<div class="result error" style="color: #721c24; background: #f8d7da; padding: 15px; border-radius: 5px;">
        <i class="fas fa-exclamation-circle"></i> ${escapeHtml(message)}
    </div>`;
}

function showSuccessToast(message) {
    const toast = document.createElement('div');
    toast.className = 'success-toast';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${escapeHtml(message)}`;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
