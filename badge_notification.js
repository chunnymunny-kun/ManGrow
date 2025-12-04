/**
 * Badge Notification System
 * Shows immediate badge notifications on any page when badges are awarded
 */

// Check for new badge notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    // This will be called by PHP to show immediate notifications
    if (window.showBadgeNotification) {
        showBadgeNotification();
    }
});

// Function to show badge notification overlay
function showBadgeNotification() {
    // Check if notification already exists
    if (document.getElementById('badgeNotification')) {
        return; // Already showing
    }

    // Create notification overlay
    const overlay = document.createElement('div');
    overlay.id = 'badgeNotification';
    overlay.className = 'badge-notification-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        animation: fadeIn 0.3s ease-in;
    `;

    const modal = document.createElement('div');
    modal.className = 'badge-notification-modal';
    modal.style.cssText = `
        background: linear-gradient(135deg, #e67e22, #d35400);
        border-radius: 20px;
        padding: 2rem;
        text-align: center;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        color: white;
        animation: bounceIn 0.6s ease-out;
    `;

    modal.innerHTML = `
        <div class="badge-celebration">
            <div class="badge-icon" style="font-size: 4rem; margin-bottom: 1rem; animation: pulse 2s infinite;">
                <i class="fas fa-medal"></i>
            </div>
            <h2 style="margin: 1rem 0; font-size: 2rem; text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);">🎉 Congratulations! 🎉</h2>
            <p>You've earned a new badge!</p>
            <div class="new-badge-display" style="margin: 2rem 0; display: flex; justify-content: center;">
                <div class="badge-card earned" style="background: rgba(255, 255, 255, 0.2); border-radius: 15px; padding: 1.5rem; backdrop-filter: blur(10px); border: 2px solid rgba(255, 255, 255, 0.3);">
                    <div class="badge event-organizer" style="width: 80px; height: 80px; background: linear-gradient(135deg, #f39c12, #e67e22); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: white; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); animation: rotate 3s linear infinite;">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <p>Event Organizer</p>
                </div>
            </div>
            <p class="badge-description" style="font-size: 1.1rem; margin: 1rem 0; opacity: 0.9;">Excellent! You've successfully organized your first event and helped build the community!</p>
            <div class="notification-buttons" style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem;">
                <button onclick="viewBadgeDetails()" class="btn-view-badge" style="padding: 0.8rem 2rem; border: none; border-radius: 25px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; font-size: 1rem; background: rgba(255, 255, 255, 0.9); color: #d35400;">View Badge</button>
                <button onclick="closeBadgeNotification()" class="btn-close-notification" style="padding: 0.8rem 2rem; border: 2px solid white; border-radius: 25px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; font-size: 1rem; background: transparent; color: white;">OK</button>
            </div>
        </div>
    `;

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes bounceIn {
            0% { transform: scale(0.3) translateY(-50px); opacity: 0; }
            50% { transform: scale(1.05) translateY(-20px); }
            70% { transform: scale(0.95) translateY(-10px); }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    `;
    document.head.appendChild(style);

    // Auto-remove after 10 seconds if user doesn't interact
    setTimeout(() => {
        if (document.getElementById('badgeNotification')) {
            closeBadgeNotification();
        }
    }, 10000);
}

// Function to close badge notification
function closeBadgeNotification() {
    const notification = document.getElementById('badgeNotification');
    if (notification) {
        notification.style.animation = 'fadeOut 0.3s ease-out';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// Function to view badge details
function viewBadgeDetails() {
    closeBadgeNotification();
    setTimeout(() => {
        window.location.href = 'profile.php#badges';
    }, 400);
}
