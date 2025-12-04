document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('event-modal');
    const modalBody = document.getElementById('modal-body');
    const readMoreBtns = document.querySelectorAll('.read-more-btn');
    const closeBtn = document.querySelector('.close-modal');
    
    readMoreBtns.forEach(btn => {
        btn.addEventListener('click', async function() {
            const eventId = this.value; // Get the actual event_id from button value
            
            try {
                // Show loading state
                modalBody.innerHTML = '<div class="loading">Loading event details...</div>';
                modal.style.display = 'block';
                
                // Fetch event data from PHP endpoint
                const response = await fetch(`getevents.php?event_id=${eventId}`);
                if (!response.ok) throw new Error('Network response was not ok');
                
                const event = await response.json();
                
                // Format the date like in your PHP
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

                let modalContent = '';

                // Populate modal with same format as your PHP template
                if (event.program_type === 'Announcement') {
                    modalContent = `
                    <div class="event-header">
                        <span class="event-author">Posted by: ${event.organization}</span>
                    </div>
                        <h3>${event.title}</h3>
                    <div class="event-image">
                        <img src="${event.thumbnail}" alt="${event.thumbnail_data}">
                    </div>
                    <p class="event-desc">${event.description}</p>
                    <div class="event-footer">
                        <p><strong>Program Type:</strong> ${event.program_type}</p>
                    </div>
                `;
                } else {
                    modalContent = `
                    <div class="event-header">
                        <span class="event-author">Posted by: ${event.organization}</span>
                    </div>
                        <h3>${event.title}</h3>
                    <div class="event-image">
                        <img src="${event.thumbnail}" alt="${event.thumbnail_data}">
                    </div>
                    <p class="event-desc">${event.description}</p>
                    <p class="event-desc"><strong>Brgy: </strong>${event.barangay}</p>
                    <p class="event-desc"><strong>City/Municipality: </strong>${event.city_municipality}</p>
                    <p class="event-desc"><strong>Start of Event: </strong>${formattedDate}</p>
                    <p class="event-desc"><strong>End of Event: </strong>${formattedEndDate}</p>
                    <p><strong>Note: </strong>Participants should comply with their reports posted on this <a href="save_eventurl.php?redirect_url=${encodeURIComponent(buildReportUrl(event))}" target="_blank" rel="noopener noreferrer">link</a> or by using the QR code below.</p>
                    ${event.qr_image ? `<a href="save_eventurl.php?redirect_url=${encodeURIComponent(buildReportUrl(event))}" target="_blank" rel="noopener noreferrer"><img src="${event.qr_image}" alt="Event QR Code" style="width:300px;height:300px;"></a>` : ''}
                    <p><strong>Reward: ${event.eco_points} </strong>Eco-Points</p>
                    <div class="event-footer">
                        <p><strong>Venue:</strong> ${event.venue}</p>
                        <p><strong>Area No:</strong> ${event.area_no}</p>
                        <p><strong>Program Type:</strong> ${event.program_type}</p>
                        <p><strong>Participants:</strong> ${event.participants}</p>
                    </div>
                `;
                }

                modalBody.innerHTML = modalContent;
            } catch (error) {
                modalBody.innerHTML = `
                    <div class="error">
                        <p>Error loading event details.</p>
                        <p>${error.message}</p>
                    </div>
                `;
                console.error('Fetch error:', error);
            }
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

function buildReportUrl(event) {
    const baseUrl = event.qr_url || 'https://mangrow.42web.io/reportform.php'; // Fallback if qr_url not set //set to localhost:3000 if running localhost
    const params = new URLSearchParams({
        event_id: event.event_id,
        start_date: event.start_date,
        end_date: event.end_date
    });
    return `${baseUrl}&${params.toString()}`;
}

document.getElementById('thumbnail').addEventListener('change', function(e) {
    const preview = document.getElementById('thumbnail-preview');
    const file = e.target.files[0];
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Thumbnail Preview">`;
        }
        
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
});

// Form Validation
document.getElementById('event-creation-form').addEventListener('submit', function(e) {
    console.log('⏰ ===== DATE VALIDATION (events.js) =====');
    
    const programType = document.getElementById('program-type')?.value;
    console.log('Program type:', programType);
    
    // Skip date validation for Announcements
    if (programType === 'Announcement') {
        console.log('⏭️ Skipping date validation (Announcement type)');
        console.log('===== DATE VALIDATION END =====\n');
        return;
    }
    
    const endDateInput = document.getElementById('end-date');
    const startDateInput = document.getElementById('start-date');
    
    console.log('Start date input:', startDateInput?.value);
    console.log('End date input:', endDateInput?.value);
    
    // If dates are empty, skip validation (handled by required attribute or other validation)
    if (!startDateInput?.value || !endDateInput?.value) {
        console.log('⏭️ Date fields empty, skipping validation');
        console.log('===== DATE VALIDATION END =====\n');
        return;
    }
    
    const endDate = new Date(endDateInput.value);
    const startDate = new Date(startDateInput.value);
    
    console.log('Start date parsed:', startDate);
    console.log('End date parsed:', endDate);
    console.log('End date <= Start date?', endDate <= startDate);
    
    if (endDate <= startDate) {
        console.log('❌ DATE VALIDATION FAILED - Preventing form submission');
        e.preventDefault();
        alert('End date must be after start date');
    } else {
        console.log('✅ Date validation passed');
    }
    console.log('===== DATE VALIDATION END =====\n');
});

document.addEventListener('DOMContentLoaded', function() {
    const programTypeSelect = document.getElementById('program-type');
    
    // Get all elements to toggle by their IDs for more reliability
    const fieldsToToggle = [
        'venue', 'barangay', 'city', 'area-no', 
        'start-date', 'end-date', 'bounty'
    ].map(id => {
        const input = document.getElementById(id);
        return input ? input.closest('.form-group') : null;
    }).filter(Boolean);

    // Initial check on page load
    toggleFields();

    // Add event listener for changes
    programTypeSelect.addEventListener('change', toggleFields);

    function toggleFields() {
        const isAnnouncement = programTypeSelect.value === 'Announcement';
        
        fieldsToToggle.forEach(field => {
            if (!field) return; // Skip if element not found
            
            if (isAnnouncement) {
                field.style.display = 'none';
                // Remove required attribute when hidden
                const inputs = field.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.removeAttribute('required');
                    // Store original required state
                    if (input.hasAttribute('required')) {
                        input.dataset.wasRequired = 'true';
                    }
                });
            } else {
                field.style.display = 'block';
                // Restore required attribute if it was originally required
                const inputs = field.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (input.dataset.wasRequired === 'true' || input.id !== 'bounty') {
                        input.setAttribute('required', '');
                    }
                });
            }
        });
    }
});