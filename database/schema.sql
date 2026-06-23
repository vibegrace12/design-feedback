-- Design Feedback System Database Schema
-- Database: `design_feedback-system`

-- 1. Projects Table
CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    client_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Design Versions Table
CREATE TABLE design_versions (
    id SERIAL PRIMARY KEY,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    version_number INT NOT NULL,
    file_url TEXT NOT NULL, -- S3 Asset Bucket URL
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Contextual Pins Table
CREATE TABLE comment_pins (
    id SERIAL PRIMARY KEY,
    version_id INT REFERENCES design_versions(id) ON DELETE CASCADE,
    user_id INT NOT NULL,
    x_percentage NUMERIC(5,2) NOT NULL, -- Relative horizontal location
    y_percentage NUMERIC(5,2) NOT NULL, -- Relative vertical location
    category VARCHAR(50) DEFAULT 'General', -- e.g., 'Copy', 'Layout', 'Color'
    severity VARCHAR(20) DEFAULT 'Minor',   -- 'Blocker', 'Minor', 'Idea'
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Threaded Comments Table
CREATE TABLE pin_replies (
    id SERIAL PRIMARY KEY,
    pin_id INT REFERENCES comment_pins(id) ON DELETE CASCADE,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for performance
CREATE INDEX idx_design_versions_project ON design_versions(project_id);
CREATE INDEX idx_comment_pins_version ON comment_pins(version_id) WHERE is_resolved = FALSE;
CREATE INDEX idx_pin_replies_pin ON pin_replies(pin_id);
