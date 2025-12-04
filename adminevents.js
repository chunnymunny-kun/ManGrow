document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('event-modal');
    const modalBody = document.getElementById('modal-body');
    const closeBtn = document.querySelector('.close-modal');
    
    // Function to handle view button clicks
    function handleViewButtonClick(eventId) {
        fetchEventDetails(eventId);
    }

    // Function to fetch and display event details
    async function fetchEventDetails(eventId) {
        try {
            // Show loading state
            modalBody.innerHTML = '<div class="loading">Loading event details...</div>';
            modal.style.display = 'block';
            
            // Fetch event data
            const response = await fetch(`getevents.php?event_id=${eventId}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const event = await response.json();
            
            const eventDate = new Date(event.start_date);
            const endDate = new Date(event.end_date);
            const postedDate = new Date(event.created_at);
            
            const formattedDate = eventDate.toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            }) + ' ' + eventDate.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            const formattedEndDate = endDate.toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            }) + ' ' + endDate.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            const formattedTime = eventDate.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            const formattedAnnouncementDate = postedDate.toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });
            const formattedAnnouncementTime = postedDate.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            
            // Generate modal content
            let modalContent = '';
            
            if (event.program_type === 'Announcement') {
                modalContent = `
                <div class="event-header">
                    <span class="event-author">Posted by: ${event.organization}</span>
                </div>
                    <h3>${event.subject}</h3>
                <div class="event-image">
                    <img src="${event.thumbnail}" alt="${event.thumbnail_data}">
                </div>
                <p class="event-desc">${event.description}</p>
                <div class="event-footer">
                    <p><strong>Program Type:</strong> ${event.program_type}</p>
                </div>`;
            } else {
                modalContent = `
                    <div class="event-header">
                        <span class="event-author">Posted by: ${event.organization}</span>
                    </div>
                    <h3>${event.subject}</h3>
                    <div class="event-image">
                        <img src="${event.thumbnail}" alt="${event.thumbnail_data}">
                    </div>
                    <p class="event-desc">${event.description}</p>
                    ${event.barangay ? `<p class="event-desc"><strong>Brgy: </strong>${event.barangay}</p>` : ''}
                    ${event.city_municipality ? `<p class="event-desc"><strong>City/Municipality: </strong>${event.city_municipality}</p>` : ''}
                    <p class="event-desc"><strong>Start of Event: </strong>${formattedDate}</p>
                    <p class="event-desc"><strong>End of Event: </strong>${formattedEndDate}</p>
                    ${event.eco_points ? `<p><strong>Reward: ${event.eco_points} </strong>Eco-Points</p>` : ''}
                    <div class="event-footer">
                        ${event.venue ? `<p><strong>Venue:</strong> ${event.venue}</p>` : ''}
                        ${event.area_no ? `<p><strong>Area No:</strong> ${event.area_no}</p>` : ''}
                        <p><strong>Program Type:</strong> ${event.program_type}</p>
                        ${event.participants ? `<p><strong>Participants:</strong> ${event.participants}</p>` : ''}
                    </div>
                `;
            }
            
            modalBody.innerHTML = modalContent;
            
            // Add admin-specific controls if needed
            if (document.body.classList.contains('admin-page')) {
                const adminControls = `
                    <div class="admin-controls">
                        <button class="edit-btn">Edit Event</button>
                        <button class="delete-btn">Delete Event</button>
                    </div>
                `;
                modalBody.insertAdjacentHTML('beforeend', adminControls);
            }
            
        } catch (error) {
            modalBody.innerHTML = `
                <div class="error">
                    <p>Error loading event details.</p>
                    <p>${error.message}</p>
                </div>
            `;
            console.error('Fetch error:', error);
        }
    }

    // Set up event listeners for view buttons in Mangrow Events section
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            handleViewButtonClick(eventId);
        });
    });

    // Set up event listeners for view buttons in All Events section
    document.querySelectorAll('.view-btn-small').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            handleViewButtonClick(eventId);
        });
    });
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
});