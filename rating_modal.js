/**
 * Rating Modal JavaScript for Illegal Activity Reports
 * Handles the rating interface when reports are marked as resolved
 */

// Global rating modal object
const ratingModal = {
    isOpen: false,
    currentRating: 0,
    currentReportData: null,
    onSubmitCallback: null,
    
    // Initialize the modal (creates HTML if needed)
    init() {
        // Check if modal already exists
        if (document.getElementById('ratingModal')) {
            return;
        }

        // Create modal HTML
        const modalHTML = `
            <div id="ratingModal" class="rating-modal">
                <div class="rating-modal-content">
                    <div class="rating-modal-header">
                        <i class="fas fa-star modal-icon"></i>
                        <h3>Rate Report Resolution</h3>
                    </div>
                    <div class="rating-modal-body">
                        <div class="report-info">
                            <h4>Report Details</h4>
                            <div class="report-info-grid">
                                <div class="report-info-item">
                                    <strong>Report ID:</strong> <span id="modal-report-id">-</span>
                                </div>
                                <div class="report-info-item">
                                    <strong>Type:</strong> <span id="modal-incident-type">-</span>
                                </div>
                                <div class="report-info-item">
                                    <strong>Priority:</strong> <span id="modal-priority">-</span>
                                </div>
                                <div class="report-info-item">
                                    <strong>Location:</strong> <span id="modal-location">-</span>
                                </div>
                                <div class="report-info-item">
                                    <strong>Reporter:</strong> <span id="modal-reporter">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <p>Please rate the accuracy and quality of this report:</p>
                        
                        <div class="star-rating" id="starRating">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        
                        <div class="rating-description" id="ratingDescription">
                            Please select a rating
                        </div>
                        
                        <div class="rating-success" id="ratingSuccess">
                            Rating submitted successfully!
                        </div>
                    </div>
                    <div class="rating-modal-footer">
                        <button type="button" class="btn-cancel" id="cancelRating">Cancel</button>
                        <button type="button" class="btn-submit" id="submitRating" disabled>Submit Rating</button>
                    </div>
                </div>
            </div>
        `;

        // Add modal to document
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Initialize event listeners
        this.initEventListeners();
    },

    // Set up event listeners
    initEventListeners() {
        const modal = document.getElementById('ratingModal');
        const stars = document.querySelectorAll('#starRating .star');
        const submitBtn = document.getElementById('submitRating');
        const cancelBtn = document.getElementById('cancelRating');
        const closeBtn = document.querySelector('#ratingModal .rating-modal-close');

        // Star rating interactions
        stars.forEach(star => {
            star.addEventListener('mouseenter', () => {
                if (!modal.classList.contains('loading')) {
                    this.highlightStars(parseInt(star.dataset.rating));
                }
            });

            star.addEventListener('mouseleave', () => {
                if (!modal.classList.contains('loading')) {
                    this.highlightStars(this.currentRating);
                }
            });

            star.addEventListener('click', () => {
                if (!modal.classList.contains('loading')) {
                    this.setRating(parseInt(star.dataset.rating));
                }
            });
        });

        // Submit button
        submitBtn.addEventListener('click', () => {
            this.submitRating();
        });

        // Cancel button
        cancelBtn.addEventListener('click', () => {
            this.close();
        });

        // Close button (X)
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                this.close();
            });
        }

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.close();
            }
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
    },

    // Open the modal with report ID (will fetch details automatically)
    open(reportIdOrData, onSubmitCallback) {
        this.init(); // Ensure modal exists

        this.onSubmitCallback = onSubmitCallback;
        this.currentRating = 0;

        // Show modal first with loading state
        const modal = document.getElementById('ratingModal');
        modal.style.display = 'block';
        modal.classList.add('loading');
        this.isOpen = true;

        // Reset modal state
        this.resetModal();

        // Check if we received just a report ID or full data
        if (typeof reportIdOrData === 'number' || (typeof reportIdOrData === 'string' && !isNaN(reportIdOrData))) {
            // We got a report ID, fetch the details
            this.fetchAndDisplayReportDetails(reportIdOrData);
        } else if (reportIdOrData && typeof reportIdOrData === 'object') {
            // We got full report data (fallback)
            this.currentReportData = reportIdOrData;
            this.displayReportData(reportIdOrData);
            modal.classList.remove('loading');
        } else {
            console.error('Invalid report data provided to rating modal');
            this.close();
        }
    },

    // Fetch report details from server
    async fetchAndDisplayReportDetails(reportId) {
        try {
            const response = await fetch(`get_report_details.php?report_id=${reportId}&for_rating=true`);
            const result = await response.json();

            if (result.success && result.report) {
                this.currentReportData = result.report;
                this.displayReportData(result.report);
            } else {
                throw new Error(result.message || 'Failed to fetch report details');
            }
        } catch (error) {
            console.error('Error fetching report details:', error);
            // Show error state
            document.getElementById('modal-report-id').textContent = 'Error loading';
            document.getElementById('modal-incident-type').textContent = 'Error loading data';
            document.getElementById('modal-priority').textContent = 'Error';
            document.getElementById('modal-location').textContent = 'Error loading';
            document.getElementById('modal-reporter').textContent = 'Error loading';
        } finally {
            const modal = document.getElementById('ratingModal');
            modal.classList.remove('loading');
        }
    },

    // Display report data in modal
    displayReportData(reportData) {
        document.getElementById('modal-report-id').textContent = reportData.reportId || 'Unknown';
        document.getElementById('modal-incident-type').textContent = reportData.incidentType || 'Unknown';
        document.getElementById('modal-priority').textContent = reportData.priority || 'Normal';
        document.getElementById('modal-location').textContent = reportData.location || 'Unknown';
        document.getElementById('modal-reporter').textContent = reportData.reporter || 'Anonymous';

        // Focus first star for accessibility
        setTimeout(() => {
            const firstStar = document.querySelector('#starRating .star');
            if (firstStar) firstStar.focus();
        }, 100);
    },

    // Close the modal
    close() {
        const modal = document.getElementById('ratingModal');
        if (modal) {
            modal.style.display = 'none';
        }
        this.isOpen = false;
        this.currentRating = 0;
        this.currentReportData = null;
        this.onSubmitCallback = null;
    },

    // Reset modal to initial state
    resetModal() {
        const modal = document.getElementById('ratingModal');
        const submitBtn = document.getElementById('submitRating');
        const successDiv = document.getElementById('ratingSuccess');

        modal.classList.remove('loading');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submit Rating';
        successDiv.classList.remove('show');

        this.highlightStars(0);
        this.updateDescription(0);
    },

    // Highlight stars up to rating
    highlightStars(rating) {
        const stars = document.querySelectorAll('#starRating .star');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
    },

    // Set the current rating
    setRating(rating) {
        this.currentRating = rating;
        this.highlightStars(rating);
        this.updateDescription(rating);

        const submitBtn = document.getElementById('submitRating');
        submitBtn.disabled = rating === 0;
    },

    // Update the rating description
    updateDescription(rating) {
        const descriptions = {
            0: 'Please select a rating',
            1: '<strong style="color: #dc3545;">Poor:</strong> Report had significant inaccuracies or was misleading',
            2: '<strong style="color: #fd7e14;">Fair:</strong> Report had some issues but provided useful information',
            3: '<strong style="color: #ffc107;">Good:</strong> Report was mostly accurate and helpful',
            4: '<strong style="color: #20c997;">Very Good:</strong> Report was accurate and well-detailed',
            5: '<strong style="color: #28a745;">Excellent:</strong> Report was highly accurate and extremely helpful'
        };

        const descriptionDiv = document.getElementById('ratingDescription');
        descriptionDiv.innerHTML = descriptions[rating] || descriptions[0];
    },

    // Submit the rating
    async submitRating() {
        if (this.currentRating === 0 || !this.currentReportData) {
            return;
        }

        const modal = document.getElementById('ratingModal');
        const submitBtn = document.getElementById('submitRating');
        
        try {
            // Show loading state
            modal.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            // Send rating to server
            const response = await fetch('submit_report_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    report_id: this.currentReportData.reportId,
                    rating: this.currentRating
                })
            });

            const result = await response.json();

            if (result.success) {
                // Show success message
                const successDiv = document.getElementById('ratingSuccess');
                successDiv.classList.add('show');

                // Call callback with result
                if (this.onSubmitCallback) {
                    this.onSubmitCallback(this.currentRating, result);
                }

                // Close modal after delay
                setTimeout(() => {
                    this.close();
                }, 1500);

            } else {
                throw new Error(result.message || 'Failed to submit rating');
            }

        } catch (error) {
            console.error('Error submitting rating:', error);
            alert('Error submitting rating: ' + error.message);
            
            // Reset loading state
            modal.classList.remove('loading');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Rating';
        }
    }
};

// Global rating descriptions for display
const RatingDescriptions = {
    1: { text: 'Poor Quality', color: '#dc3545' },
    2: { text: 'Below Average', color: '#fd7e14' },
    3: { text: 'Average', color: '#ffc107' },
    4: { text: 'Good Quality', color: '#20c997' },
    5: { text: 'Excellent', color: '#28a745' }
};

// Function to display rating stars (for use in report lists)
function displayRatingStars(rating, container) {
    if (!rating || rating === 0) {
        container.innerHTML = '<span class="no-rating">No rating</span>';
        return;
    }

    const ratingInfo = RatingDescriptions[rating];
    let starsHTML = '';
    
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            starsHTML += `<span class="star filled" style="color: ${ratingInfo.color}">★</span>`;
        } else {
            starsHTML += `<span class="star empty">☆</span>`;
        }
    }
    
    starsHTML += `<span class="rating-text" style="color: ${ratingInfo.color}"> ${ratingInfo.text}</span>`;
    container.innerHTML = starsHTML;
}

// Initialize rating modal when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Make ratingModal globally available
    window.ratingModal = ratingModal;
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ratingModal, displayRatingStars, RatingDescriptions };
}
