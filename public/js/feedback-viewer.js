// Canvas Pin Placement and Interaction

class FeedbackViewer {
    constructor() {
        this.canvas = document.getElementById('design-canvas');
        this.pinsContainer = document.getElementById('pins-container');
        this.currentPin = null;
        this.setupCanvasInteraction();
    }

    setupCanvasInteraction() {
        this.canvas.addEventListener('click', (e) => this.handleCanvasClick(e));
    }

    handleCanvasClick(e) {
        // Only add new pin if not clicking on existing pin
        if (e.target === this.canvas || e.target.tagName === 'IMG') {
            const rect = this.canvas.getBoundingClientRect();
            const imgRect = document.getElementById('design-image').getBoundingClientRect();
            
            const x = ((e.clientX - imgRect.left) / imgRect.width) * 100;
            const y = ((e.clientY - imgRect.top) / imgRect.height) * 100;

            if (x >= 0 && x <= 100 && y >= 0 && y <= 100) {
                this.showPinModal(x, y);
            }
        }
    }

    showPinModal(x, y) {
        const modal = document.getElementById('pin-modal');
        const form = document.getElementById('pin-form');
        
        form.onsubmit = (e) => {
            e.preventDefault();
            this.createPin(x, y, form);
        };

        const closeBtn = modal.querySelector('.close-btn');
        closeBtn.onclick = () => modal.style.display = 'none';

        modal.style.display = 'flex';
    }

    createPin(x, y, form) {
        const app = window.app; // Assuming app instance is global
        const category = document.getElementById('pin-category').value;
        const severity = document.getElementById('pin-severity').value;
        const comment = document.getElementById('pin-comment').value;

        // Create pin
        fetch('/api/pins.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                version_id: app.currentVersion.id,
                user_id: app.userId,
                x_percentage: x,
                y_percentage: y,
                category: category,
                severity: severity
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Add reply with the initial comment
                    return fetch('/api/replies.php?action=create', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            pin_id: data.id,
                            user_id: app.userId,
                            comment_text: comment
                        })
                    });
                } else {
                    throw new Error(data.error);
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    app.loadPins();
                    document.getElementById('pin-modal').style.display = 'none';
                    form.reset();
                }
            })
            .catch(err => console.error('Failed to create pin:', err));
    }
}

// Initialize feedback viewer when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new FeedbackViewer();
    // Make app instance global for feedback viewer
    window.addEventListener('DOMContentLoaded', () => {
        // App should already be initialized
    });
});
