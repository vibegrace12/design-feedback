// Main Application Logic

class DesignFeedbackApp {
    constructor() {
        this.currentProject = null;
        this.currentVersion = null;
        this.projects = [];
        this.pins = [];
        this.userId = 1; // Mock user ID
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadProjects();
    }

    setupEventListeners() {
        document.getElementById('new-project-btn').addEventListener('click', () => this.showNewProjectModal());
    }

    loadProjects() {
        fetch('/api/projects.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.projects = data.projects;
                    this.renderProjects();
                }
            })
            .catch(err => console.error('Failed to load projects:', err));
    }

    renderProjects() {
        const list = document.getElementById('projects-list');
        list.innerHTML = '';

        this.projects.forEach(project => {
            const item = document.createElement('div');
            item.className = 'project-item';
            item.innerHTML = `
                <div class="project-item-name">${this.escapeHtml(project.name)}</div>
                <div class="project-item-meta">Client: ${project.client_id}</div>
            `;
            item.addEventListener('click', () => this.selectProject(project));
            list.appendChild(item);
        });
    }

    selectProject(project) {
        this.currentProject = project;
        document.querySelectorAll('.project-item').forEach(item => item.classList.remove('active'));
        event.currentTarget.classList.add('active');
        this.loadProjectVersions();
    }

    loadProjectVersions() {
        fetch(`/api/projects.php?action=get&id=${this.currentProject.id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.currentProject = data.project;
                    this.renderVersionSelector();
                    if (this.currentProject.versions.length > 0) {
                        this.selectVersion(this.currentProject.versions[0]);
                    }
                }
            })
            .catch(err => console.error('Failed to load versions:', err));
    }

    renderVersionSelector() {
        const selector = document.getElementById('version-select');
        selector.innerHTML = '';

        this.currentProject.versions.forEach((version, index) => {
            const option = document.createElement('option');
            option.value = version.id;
            option.textContent = `Version ${version.version_number} (${new Date(version.uploaded_at).toLocaleDateString()})`;
            selector.appendChild(option);
        });

        selector.addEventListener('change', (e) => {
            const version = this.currentProject.versions.find(v => v.id == e.target.value);
            this.selectVersion(version);
        });
    }

    selectVersion(version) {
        this.currentVersion = version;
        document.getElementById('version-title').textContent = `Version ${version.version_number}`;
        document.getElementById('design-image').src = version.file_url;
        document.getElementById('viewer-placeholder').style.display = 'none';
        document.getElementById('viewer-container').style.display = 'flex';
        this.loadPins();
    }

    loadPins() {
        fetch(`/api/pins.php?action=list&version_id=${this.currentVersion.id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.pins = data.pins;
                    this.renderPins();
                    this.renderFeedbackList();
                }
            })
            .catch(err => console.error('Failed to load pins:', err));
    }

    renderPins() {
        const container = document.getElementById('pins-container');
        container.innerHTML = '';

        this.pins.forEach((pin, index) => {
            const pinEl = document.createElement('div');
            pinEl.className = `pin severity-${pin.severity.toLowerCase()}`;
            if (pin.is_resolved) pinEl.classList.add('resolved');
            pinEl.style.left = pin.x_percentage + '%';
            pinEl.style.top = pin.y_percentage + '%';
            pinEl.textContent = index + 1;
            pinEl.addEventListener('click', () => this.showThreadModal(pin));
            container.appendChild(pinEl);
        });
    }

    renderFeedbackList() {
        const list = document.getElementById('feedback-list');
        list.innerHTML = '';

        this.pins.forEach((pin, index) => {
            const item = document.createElement('div');
            item.className = `feedback-item severity-${pin.severity.toLowerCase()}`;
            item.innerHTML = `
                <div class="feedback-meta">
                    <span class="feedback-tag severity">${pin.severity}</span>
                    <span class="feedback-tag category">${pin.category}</span>
                </div>
                <div><strong>#${index + 1}</strong> ${pin.category}</div>
            `;
            item.addEventListener('click', () => this.showThreadModal(pin));
            list.appendChild(item);
        });
    }

    showThreadModal(pin) {
        const modal = document.getElementById('thread-modal');
        const content = document.getElementById('thread-content');
        content.innerHTML = '<div class="loading">Loading thread...</div>';

        fetch(`/api/replies.php?action=list&pin_id=${pin.id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.renderThread(data.replies, pin.id);
                }
            })
            .catch(err => console.error('Failed to load thread:', err));

        modal.style.display = 'flex';
        this.setupModalEvents(modal, pin);
    }

    renderThread(replies, pinId) {
        const content = document.getElementById('thread-content');
        content.innerHTML = '';

        replies.forEach(reply => {
            const msg = document.createElement('div');
            msg.className = reply.user_id === this.userId ? 'thread-message own' : 'thread-message';
            msg.innerHTML = `
                <div class="thread-message-meta">User ${reply.user_id} • ${new Date(reply.created_at).toLocaleString()}</div>
                <div class="thread-message-text">${this.escapeHtml(reply.comment_text)}</div>
            `;
            content.appendChild(msg);
        });
    }

    setupModalEvents(modal, pin) {
        const closeBtn = modal.querySelector('.close-btn');
        const replyForm = document.getElementById('reply-form');

        closeBtn.onclick = () => modal.style.display = 'none';
        window.onclick = (e) => {
            if (e.target === modal) modal.style.display = 'none';
        };

        replyForm.onsubmit = (e) => {
            e.preventDefault();
            const comment = document.getElementById('reply-comment').value;
            this.addReply(pin.id, comment, () => {
                this.loadPins();
                this.showThreadModal(pin);
                document.getElementById('reply-comment').value = '';
            });
        };
    }

    addReply(pinId, comment, callback) {
        fetch('/api/replies.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pin_id: pinId,
                user_id: this.userId,
                comment_text: comment
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    callback();
                }
            })
            .catch(err => console.error('Failed to add reply:', err));
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    showNewProjectModal() {
        alert('New project creation not yet implemented');
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new DesignFeedbackApp();
});
