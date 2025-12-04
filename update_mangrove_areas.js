// Function to update mangrove areas with all required properties
function updateMangroveAreas(updatedAreas) {
    // First, we need to load the original extendedmangroveareas.json
    fetch('mangroveareas.json')
        .then(response => response.json())
        .then(originalData => {
            // Create a map of the original features by AreaNo
            const originalFeaturesMap = new Map();
            let maxAreaNo = 0;
            
            originalData.features.forEach(feature => {
                const areaNo = feature.properties.AreaNo;
                if (areaNo) {
                    originalFeaturesMap.set(areaNo, feature);
                    maxAreaNo = Math.max(maxAreaNo, areaNo);
                }
            });

            // Process each updated area
            const updatedFeatures = updatedAreas.map(updatedArea => {
                // Try to find the original feature by matching coordinates first
                let originalFeature = null;
                let areaNo = null;
                
                // Check if this is an existing feature by comparing coordinates
                for (const [no, feature] of originalFeaturesMap.entries()) {
                    if (JSON.stringify(feature.geometry.coordinates) === JSON.stringify(updatedArea.geometry.coordinates)) {
                        originalFeature = feature;
                        areaNo = no;
                        break;
                    }
                }
                
                // Calculate areas
                const areaM2 = turf.area(updatedArea);
                const areaHa = (areaM2 / 10000).toFixed(2);
                const currentDate = new Date().toISOString().split('T')[0];
                
                if (originalFeature) {
                    // This is an existing feature that was modified
                    return {
                        type: "Feature",
                        geometry: updatedArea.geometry,
                        properties: {
                            ...originalFeature.properties,
                            AreaNo: areaNo,
                            AreaM2: Math.round(areaM2),
                            AreaHa: areaHa,
                            Date_Updated: currentDate,
                            // Preserve other properties from original
                            ClassID: originalFeature.properties.ClassID || "UNKNOWN"
                        }
                    };
                } else {
                    // This is a new feature
                    maxAreaNo += 1;
                    return {
                        type: "Feature",
                        geometry: updatedArea.geometry,
                        properties: {
                            AreaNo: maxAreaNo,
                            AreaM2: Math.round(areaM2),
                            AreaHa: areaHa,
                            Date_Created: currentDate,
                            Date_Updated: currentDate,
                            ClassID: "NEW" // Default ClassID for new areas
                        }
                    };
                }
            });

            // Create the updated GeoJSON object
            const updatedGeoJSON = {
                type: "FeatureCollection",
                features: updatedFeatures
            };

            // Save the updated data
            saveUpdatedMangroveAreas(updatedGeoJSON);
        })
        .catch(error => {
            console.error('Error loading original mangrove areas:', error);
            alert('Error loading original mangrove areas data');
        });
}

// Function to save the updated mangrove areas
function saveUpdatedMangroveAreas(updatedGeoJSON) {
    // Create a download link for the updated JSON file
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(updatedGeoJSON, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", "extendedmangroveareas_updated.json");
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    document.body.removeChild(downloadAnchor);
    
    // Show success message
    alert('Mangrove areas updated successfully! Downloading updated file.');
    
    // In a real implementation, you would send this to your server:
    // saveToBackend(updatedGeoJSON);
}

// Function to handle saving to a real backend (example)
async function saveToBackend(geoJSONData) {
    try {
        const response = await fetch('/api/update-mangrove-areas', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(geoJSONData)
        });

        if (!response.ok) {
            throw new Error('Network response was not ok');
        }

        const result = await response.json();
        alert(result.message || 'Mangrove areas updated successfully on server!');
        return result;
    } catch (error) {
        console.error('Error saving mangrove areas:', error);
        alert('Error saving mangrove areas: ' + error.message);
        throw error;
    }
}