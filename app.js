const ToggleButton = document.getElementById('toggle-btn')
const sidebar = document.getElementById('sidebar')
const loginbtn = document.getElementById('login')
const profileDetails = document.getElementById('profile-details');

function LoginToggle(){
    profileDetails.classList.toggle('close')
}

function SidebarToggle(){
    sidebar.classList.toggle('close')
    ToggleButton.classList.toggle('rotate')

    CloseAllSubMenus()                                                                                                                                
}

function DropDownToggle(button){
    if(!button.nextElementSibling.classList.contains('show')){

        CloseAllSubMenus()
    }

    button.nextElementSibling.classList.toggle('show')
    button.classList.toggle('rotate')

    if(sidebar.classList.contains('close')){
        sidebar.classList.toggle('close')
        ToggleButton.toggle('rotate')
    }
}

function CloseAllSubMenus(){
    Array.from(sidebar.getElementsByClassName('show')).forEach(ul => {
        ul.classList.remove('show')
        ul.previousElementSibling.classList.remove('rotate')
    })
}

function handleResize() {
    if (window.innerWidth <= 800) {
        if (sidebar.classList.contains('close')) {
            SidebarToggle();
        }
    }
}

handleResize();
window.addEventListener('resize', handleResize);

// Mobile navbar icon click functionality
document.addEventListener('DOMContentLoaded', function() {
    function handleMobileNavigation() {
        const navItems = document.querySelectorAll('.nav-list li');
        
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Only handle clicks on mobile (when text is hidden)
                if (window.innerWidth <= 800) {
                    e.preventDefault();
                    const link = this.querySelector('a');
                    if (link) {
                        window.location.href = link.getAttribute('href');
                    }
                }
            });
        });
    }
    
    handleMobileNavigation();
    
    // Re-initialize on window resize
    window.addEventListener('resize', function() {
        // Small delay to ensure CSS changes have taken effect
        setTimeout(handleMobileNavigation, 100);
    });
});

function toggleProfilePopup(e) {
    e.stopPropagation();
    const profileDetails = document.getElementById('profile-details');
    
    profileDetails.classList.toggle('close');
    
    if (!profileDetails.classList.contains('close')) {
        document.addEventListener('click', function closePopup(evt) {
            if (!profileDetails.contains(evt.target) && evt.target !== document.querySelector('.userbox')) {
                profileDetails.classList.add('close');
                document.removeEventListener('click', closePopup);
            }
        });
    }
}

function togglePasswordVisibility(inputId, iconClass = 'toggle-password') {
    const passwordInput = document.getElementById(inputId);
    const toggleBtn = document.querySelector(`.${iconClass} i`);
    
    if (!passwordInput || !toggleBtn) return;
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleBtn.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Verify elements exist
    const profileImageInput = document.getElementById('profile-image');
    if (profileImageInput) {
        profileImageInput.addEventListener('change', function() {
            startCropper(this);
        });
    }
    
    // Add similar checks for other interactive elements
});

// JavaScript for Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Sample content for modals (in real app, you might fetch this)
    const modalContents = {
        'modal-one': {
            title: 'Community News',
            content: '<p>Here are the latest updates from our community...</p> '+
            '<div class="modal-inner-container" id="mic-one"></div>'+
            '<ul><li>New member introductions</li><li>Upcoming policy changes</li><li>Recent achievements</li></ul>'
        },
        'modal-two': {
            title: 'Upcoming Events',
            content: `
                <div class="calendar-container">
                <p class="instruction" style="margin:0; font-size:12px; color:gray;">Please click on the calendar days with green dots to reveal events</pdiv>
                    <div class="calendar-header">
                        <button class="nav-button prev-month">&lt;</button>
                        <h3 class="month-year-display"></h3>
                        <button class="nav-button next-month">&gt;</button>
                    </div>
                    <div class="calendar-grid">
                        <!-- Calendar will be generated here -->
                        
                    </div>
                </div>
                <div class="event-details-container" id="mic-two">
                    <h3>Event Details</h3>
                    <div class="selected-event">
                        <p>Select a date to view event details</p>
                    </div>
                </div>
            `
        },
        'modal-three': {
            title: 'Member Success Stories',
            content: '<p>Read inspiring stories from our members:</p>'+
            '<div class="modal-inner-container" id="mic-three"></div>'+
            '<div class="story"><h4>Jane Doe</h4><p>How she grew her business by 200% in one year...</p></div>'
        },
        'modal-four': {
            title: 'Community Resources',
            content: '<p>Helpful tools for members:</p>'+
            '<div class="modal-inner-container" id="mic-four"></div>'+
            '<ul><li>Starter Guide PDF</li><li>Video Tutorials</li><li>Expert Contacts</li></ul>'
        }
    };

    // Set up click handlers for flex items
    document.querySelectorAll('.flex-item').forEach(item => {
        item.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });

    // Modal open function
    function openModal(modalId) {
        const modalTemplate = document.getElementById('modal-template');
        const modalClone = modalTemplate.cloneNode(true);
        modalClone.id = modalId;
        document.body.appendChild(modalClone);
        
        const modalContent = modalContents[modalId];
        const modalBody = modalClone.querySelector('.modal-body');
        modalBody.innerHTML = `
            <h2>${modalContent.title}</h2>
            ${modalContent.content}
        `;
        
        modalClone.style.display = 'block';
        
        // Initialize calendar if it's modal-two
        if (modalId === 'modal-two') {
            initializeCalendar(modalClone);
        }
        
        // Close button
        modalClone.querySelector('.close-modal').addEventListener('click', () => {
            modalClone.style.display = 'none';
            setTimeout(() => modalClone.remove(), 300);
        });
        
        // Close when clicking outside content
        modalClone.addEventListener('click', (e) => {
            if (e.target === modalClone) {
                modalClone.style.display = 'none';
                setTimeout(() => modalClone.remove(), 300);
            }
        });
    }

    // Updated initializeCalendar function with PHP integration
    function initializeCalendar(modal) {
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();

        const monthYearDisplay = modal.querySelector('.month-year-display');
        const prevMonthBtn = modal.querySelector('.prev-month');
        const nextMonthBtn = modal.querySelector('.next-month');
        const calendarGrid = modal.querySelector('.calendar-grid');
        const eventDetailsContainer = modal.querySelector('.event-details-container .selected-event');

        // Set initial state for event details
        if (eventDetailsContainer) {
            eventDetailsContainer.innerHTML = '<p>Select a date to view event details</p>';
        }

        // Function to format date as YYYY-MM-DD
        function formatDateKey(dateString) {
            const date = new Date(dateString);
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // Function to format time from datetime string
        function formatTime(datetimeString) {
            const date = new Date(datetimeString);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Function to format display date
        function formatDisplayDate(startDate, endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            
            if (start.toDateString() === end.toDateString()) {
                // Same day event
                return start.toLocaleDateString([], { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
            } else {
                // Multi-day event
                return `${start.toLocaleDateString([], { month: 'short', day: 'numeric' })} - 
                        ${end.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' })}`;
            }
        }

        // Fetch events from server
        function fetchEvents() {
            return fetch('get_events_index.php')
                .then(response => response.json())
                .then(data => {
                    // Transform the data into our calendar format
                    const formattedEvents = {};
                    
                    data.forEach(event => {
                        const dateKey = formatDateKey(event.start_date);
                        
                        if (!formattedEvents[dateKey]) {
                            formattedEvents[dateKey] = [];
                        }
                        
                        const eventObj = {
                            title: event.subject,
                            time: formatTime(event.start_date),
                            date: formatDisplayDate(event.start_date, event.end_date),
                            rawStartDate: event.start_date,
                            rawEndDate: event.end_date,
                            eventId: event.event_id
                        };
                        if (event.venue && event.area_no) {
                            eventObj.location = `${event.venue} (Area ${event.area_no})`;
                        } else if (event.venue) {
                            eventObj.location = event.venue;
                        } else if (event.area_no) {
                            eventObj.location = `Area ${event.area_no}`;
                        }
                        if (event.description) {
                            eventObj.description = event.description;
                        }
                        if (event.organization) {
                            eventObj.organizer = event.organization;
                        }
                        formattedEvents[dateKey].push(eventObj);
                    });
                    
                    return formattedEvents;
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    return {};
                });
        }

        function renderCalendar(month, year, events) {
            // Clear previous calendar
            calendarGrid.innerHTML = '';

            // Set month and year display
            const monthNames = ["January", "February", "March", "April", "May", "June",
                                "July", "August", "September", "October", "November", "December"];
            monthYearDisplay.textContent = `${monthNames[month]} ${year}`;

            // Create day headers
            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayNames.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'day-header';
                dayHeader.textContent = day;
                calendarGrid.appendChild(dayHeader);
            });

            // Get first day of month and total days in month
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            // Create empty slots for days before the first day of the month
            for (let i = 0; i < firstDay; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.className = 'calendar-day empty';
                calendarGrid.appendChild(emptyDay);
            }

            // Create days of the current month
            const today = new Date();
            for (let day = 1; day <= daysInMonth; day++) {
                const dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                
                // Format date key
                const formattedDay = day.toString().padStart(2, '0');
                const formattedMonth = (month + 1).toString().padStart(2, '0');
                const dateKey = `${year}-${formattedMonth}-${formattedDay}`;
                
                // Highlight today
                if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                    dayElement.classList.add('today');
                }

                dayElement.innerHTML = `<div class="day-number">${day}</div>`;

                // Check if there are events on this day
                if (events[dateKey]) {
                    const eventCount = events[dateKey].length;
                    dayElement.classList.add('has-event');
                    
                    // Add multiple event indicators if more than one event
                    for (let i = 0; i < Math.min(eventCount, 3); i++) {
                        dayElement.innerHTML += `<div class="event-indicator" title="${events[dateKey][i].title}"></div>`;
                    }
                    
                    if (eventCount > 3) {
                        dayElement.innerHTML += `<div class="event-more">+${eventCount - 3}</div>`;
                    }

                    // Add click handler to show event details
                    dayElement.addEventListener('click', (e) => {
                        e.stopPropagation();
                        showEventDetails(events[dateKey]);
                    });

                    // Auto-select first event of the month
                    if (!renderCalendar.firstEventShown && 
                        month === currentDate.getMonth() && 
                        year === currentDate.getFullYear() && 
                        day === currentDate.getDate()) {
                        renderCalendar.firstEventShown = true;
                        setTimeout(() => showEventDetails(events[dateKey]), 0);
                    }
                } else {
                    // Add click handler for days without events
                    dayElement.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (eventDetailsContainer) {
                            eventDetailsContainer.innerHTML = '<p>No events scheduled for this date</p>';
                        }
                    });
                }

                calendarGrid.appendChild(dayElement);
            }
        }

        function showEventDetails(eventsForDate) {
            if (!eventDetailsContainer || !eventsForDate || eventsForDate.length === 0) return;
            
            let eventsHTML = '';
            
            if (eventsForDate.length === 1) {
                // Single event display
                const event = eventsForDate[0];
                // Escape HTML special characters and remove slashes
                function escapeHTML(str) {
                    if (typeof str !== 'string') return '';
                    return str.replace(/\\/g, '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                eventsHTML = `
                    <h4>${escapeHTML(event.title)}</h4>
                    <p><strong>Date:</strong> ${escapeHTML(event.date)}</p>
                    <p><strong>Time:</strong> ${escapeHTML(event.time)}</p>
                    <p><strong>Location:</strong> ${escapeHTML(event.location)}</p>
                    <p><strong>Organizer:</strong> ${escapeHTML(event.organizer)}</p>
                    <p>${escapeHTML(event.description)}</p>
                    <button class="read-more-btn" data-id="${escapeHTML(event.eventId)}">Read More</button>
                `;
            } else {
                // Multiple events display
                eventsHTML = `<h4>${eventsForDate.length} Events on ${eventsForDate[0].date.split(' - ')[0]}</h4>`;
                
                eventsForDate.forEach((event, index) => {
                    eventsHTML += `
                        <div class="multiple-event">
                            <h5>${index + 1}. ${event.title}</h5>
                            <p><strong>Time:</strong> ${event.time}</p>
                            <p><strong>Location:</strong> ${event.location}</p>
                            <button class="read-more-btn" data-id="${event.eventId}">Read More</button>
                        </div>
                    `;
                });
            }
            
            eventDetailsContainer.innerHTML = eventsHTML;

            // Add click handlers for all read more buttons
            eventDetailsContainer.querySelectorAll('.read-more-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const eventId = btn.dataset.id;
                    window.location.href = `event_details.php?id=${eventId}`;
                });
            });
        }

        // Initial load
        fetchEvents().then(events => {
            renderCalendar.firstEventShown = false;
            renderCalendar(currentMonth, currentYear, events);

            // Event listeners for navigation
            prevMonthBtn.addEventListener('click', () => {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                renderCalendar.firstEventShown = false;
                fetchEvents().then(events => {
                    renderCalendar(currentMonth, currentYear, events);
                });
            });

            nextMonthBtn.addEventListener('click', () => {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                renderCalendar.firstEventShown = false;
                fetchEvents().then(events => {
                    renderCalendar(currentMonth, currentYear, events);
                });
            });
        });
    }
});