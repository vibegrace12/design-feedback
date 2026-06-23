# Setup Guide

## Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)

## Installation

### 1. Database Setup

1. Create a new MySQL database:
   ```sql
   CREATE DATABASE `design_feedback-system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the schema:
   ```bash
   mysql -u root -p design_feedback-system < database/schema.sql
   ```

### 2. Configuration

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Update the database credentials in `.env`:
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASSWORD=your_password
   DB_NAME=design_feedback-system
   ```

### 3. Server Setup

1. Point your web server's document root to the `public` directory

2. For Apache, create a `.htaccess` in the root (if needed):
   ```
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /
       RewriteCond %{REQUEST_FILENAME} !-f
       RewriteCond %{REQUEST_FILENAME} !-d
       RewriteRule ^(.*)$ index.php [L]
   </IfModule>
   ```

### 4. Test the Installation

1. Start your web server
2. Navigate to `http://localhost` (or your configured domain)
3. You should see the Design Feedback System interface

## File Structure

```
design-feedback/
├── api/
│   ├── projects.php       # Project API endpoints
│   ├── pins.php          # Pin/comment API endpoints
│   └── replies.php       # Reply thread API endpoints
├── config/
│   └── database.php      # Database configuration
├── database/
│   └── schema.sql        # Database schema
├── public/
│   ├── index.html        # Main HTML interface
│   ├── js/
│   │   ├── app.js        # Main application logic
│   │   └── feedback-viewer.js  # Canvas interaction
│   └── styles/
│       ├── main.css      # Main styles
│       └── feedback-viewer.css # Canvas styles
└── docs/
    └── SETUP.md          # This file
```

## API Endpoints

### Projects
- `GET /api/projects.php?action=list` - List all projects
- `GET /api/projects.php?action=get&id=1` - Get project with versions
- `POST /api/projects.php?action=create` - Create new project

### Pins (Comments)
- `GET /api/pins.php?action=list&version_id=1` - Get pins for version
- `POST /api/pins.php?action=create` - Create new pin
- `PUT /api/pins.php?action=update` - Update pin status

### Replies (Thread)
- `GET /api/replies.php?action=list&pin_id=1` - Get replies for pin
- `POST /api/replies.php?action=create` - Add reply to thread

## Features

### Current Features
- ✅ View design versions
- ✅ Place contextual pins on designs
- ✅ Add feedback comments to pins
- ✅ Reply to feedback in threaded discussions
- ✅ Categorize feedback (Copy, Layout, Color, etc.)
- ✅ Set severity levels (Blocker, Minor, Idea)
- ✅ Mark pins as resolved
- ✅ View all feedback in side panel

### Future Enhancements
- User authentication
- Real-time notifications
- Export feedback reports
- Design comparison (before/after)
- Integration with design tools
- Permission management
- Version history

## Troubleshooting

### Database Connection Error
- Verify credentials in `.env`
- Ensure MySQL service is running
- Check database exists and is accessible

### API 404 Errors
- Verify web server is serving from `public` directory
- Check `.htaccess` is properly configured (if using Apache)

### Missing Styles or JavaScript
- Hard refresh browser (Ctrl+F5 or Cmd+Shift+R)
- Check browser console for 404 errors
- Verify all files are in the correct directories
