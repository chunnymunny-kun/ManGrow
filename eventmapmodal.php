                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        <?php
                                        // Set default coordinates
                                        $eventLat = 14.64852;
                                        $eventLng = 120.47318;
                                        $venue = 'Event Location';
                                        
                                        // Override with database values if available
                                        if (isset($items['latitude']) && isset($items['longitude'])) {
                                            $eventLat = floatval($items['latitude']);
                                            $eventLng = floatval($items['longitude']);
                                        }
                                        $venue = !empty($items['venue']) ? $items['venue'] : 'Event Location';
                                        ?>
                                        
                                        if (document.getElementById('map')) {
                                            var map = L.map('map').setView([<?= $eventLat ?>, <?= $eventLng ?>], 15);
                                            
                                            L.tileLayer('https://api.maptiler.com/maps/openstreetmap/{z}/{x}/{y}.jpg?key=w1gk7TVN9DDwIGdvJ31q', {
                                                attribution: ''
                                            }).addTo(map);

                                            // Event location marker
                                            var eventMarker = L.marker([<?= $eventLat ?>, <?= $eventLng ?>]).addTo(map)
                                                .bindPopup(`<p><?= htmlspecialchars($venue, ENT_QUOTES) ?></p>`).openPopup();

                                            // Add Leaflet Routing Machine
                                            L.Routing.control({
                                                waypoints: [
                                                    // Start point will be set when user clicks "Get Directions"
                                                    L.latLng(<?= $eventLat ?>, <?= $eventLng ?>) // Event location
                                                ],
                                                routeWhileDragging: true,
                                                showAlternatives: true,
                                                addWaypoints: false,
                                                draggableWaypoints: false,
                                                fitSelectedRoutes: true,
                                                show: false, // Initially hidden
                                                collapsible: true,
                                                position: 'topright',
                                                createMarker: function() { return null; }, // Don't create default markers
                                                lineOptions: {
                                                    styles: [{color: '#3a7bd5', opacity: 0.7, weight: 5}]
                                                }
                                            }).addTo(map);

                                            // Custom control for getting directions
                                            var routeControl = L.Control.extend({
                                                options: {
                                                    position: 'topright'
                                                },
                                                onAdd: function(map) {
                                                    var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                                                    var link = L.DomUtil.create('a', 'leaflet-control-get-route', container);
                                                    link.href = '#';
                                                    link.title = 'Get Directions';
                                                    link.innerHTML = '<span>🚗</span>';
                                                    
                                                    L.DomEvent.on(link, 'click', function(e) {
                                                        L.DomEvent.stop(e);
                                                        
                                                        // Get user's current location
                                                        map.locate({setView: false, maxZoom: 16}).on('locationfound', function(e) {
                                                            var routingControl = L.Routing.control({
                                                                waypoints: [
                                                                    L.latLng(e.latitude, e.longitude), // User's location
                                                                    L.latLng(<?= $eventLat ?>, <?= $eventLng ?>) // Event location
                                                                ],
                                                                routeWhileDragging: true,
                                                                showAlternatives: true,
                                                                fitSelectedRoutes: true,
                                                                lineOptions: {
                                                                    styles: [{color: '#3a7bd5', opacity: 0.7, weight: 5}]
                                                                }
                                                            }).addTo(map);
                                                            
                                                            // Add marker for user's location
                                                            L.marker([e.latitude, e.longitude])
                                                                .addTo(map)
                                                                .bindPopup("Your Location")
                                                                .openPopup();
                                                        }).on('locationerror', function(e) {
                                                            alert("Could not get your location. Please enable location services.");
                                                        });
                                                    });
                                                    
                                                    return container;
                                                }
                                            });
                                            
                                            map.addControl(new routeControl());

                                            // Existing locate control
                                            if (typeof L.control.locate === 'function') {
                                                L.control.locate({
                                                    position: 'topright',
                                                    flyTo: true,
                                                    showPopup: false,
                                                    strings: {
                                                        title: "Show me where I am"
                                                    }
                                                }).addTo(map);
                                            }
                                        }
                                    });
                                    </script>