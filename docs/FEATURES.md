# Enhanced Features Documentation

## 1. User Authentication

### Overview
Secure user registration and login system with role-based access control.

### Features
- User registration with email validation
- Secure password hashing (bcrypt)
- Session-based authentication
- User roles: Admin, Designer, Reviewer
- User profiles with avatars

### API Endpoints

**Register User**
```
POST /api/auth.php?action=register
Body: {
  "username": "john_doe",
  "email": "john@example.com",
  "password": "secure_password",
  "full_name": "John Doe"
}
```

**Login**
```
POST /api/auth.php?action=login
Body: {
  "email": "john@example.com",
  "password": "secure_password"
}
```

**Verify Authentication**
```
GET /api/auth.php?action=verify
Response: { authenticated: true, user: {...} }
```

**Logout**
```
POST /api/auth.php?action=logout
```

---

## 2. Real-time Notifications

### Overview
Notification system to keep users informed of project updates.

### Notification Types
- `pin_created` - New feedback pin added
- `reply_added` - New reply to feedback thread
- `pin_resolved` - Feedback pin marked as resolved
- `mentioned` - User mentioned in a comment
- `permission_granted` - Access granted to project
- `permission_revoked` - Access revoked from project

### API Endpoints

**List Notifications**
```
GET /api/notifications.php?action=list&limit=50&offset=0&unread=0
Response: {
  "notifications": [...],
  "unread_count": 5
}
```

**Mark as Read**
```
PUT /api/notifications.php?action=mark-read&id=123
```

**Mark All as Read**
```
PUT /api/notifications.php?action=mark-all-read
```

### Features
- Unread notification badge
- Real-time notification feed
- Mark as read functionality
- Actor information with avatar
- Related resource links

---

## 3. Feedback Reports

### Overview
Generate comprehensive feedback reports with statistics and filtering.

### Report Contents
- Total pins count
- Resolved pins count
- Breakdown by severity (Blocker, Minor, Idea)
- Breakdown by category
- List of all pins with details
- Reply count per pin
- User information

### API Endpoints

**Generate Report**
```
POST /api/reports.php?action=generate
Body: {
  "project_id": 1,
  "version_id": 5,
  "filters": {
    "severity": "Blocker",
    "category": "Layout",
    "resolved": 0
  }
}
Response: {
  "report_id": 123,
  "data": {
    "total_pins": 42,
    "resolved_pins": 15,
    "blocker_count": 8,
    "minor_count": 20,
    "idea_count": 14,
    "pins": [...]
  }
}
```

**List Reports**
```
GET /api/reports.php?action=list&project_id=1
Response: {
  "reports": [
    {
      "id": 123,
      "generated_by": "john_doe",
      "total_pins": 42,
      "resolved_pins": 15,
      "blocker_count": 8,
      "minor_count": 20,
      "idea_count": 14,
      "generated_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

**Get Report**
```
GET /api/reports.php?action=get&id=123
Response: {
  "report": {
    "id": 123,
    "project_id": 1,
    "data": {...}
  }
}
```

### Features
- Filter by severity, category, resolution status
- Custom date range filtering
- Export to PDF
- Historical report tracking
- Audit trail of report generation

---

## 6. Permission Management

### Overview
Granular permission control for project access and editing.

### Permission Levels
- **Viewer**: Can view projects and feedback only
- **Editor**: Can create pins and replies
- **Admin**: Full project control including permission management

### API Endpoints

**List Permissions**
```
GET /api/permissions.php?action=list&project_id=1
Response: {
  "permissions": [
    {
      "id": 1,
      "user_id": 5,
      "username": "jane_doe",
      "email": "jane@example.com",
      "role": "editor",
      "created_at": "2024-01-01T12:00:00Z"
    }
  ]
}
```

**Grant Permission**
```
POST /api/permissions.php?action=grant
Body: {
  "project_id": 1,
  "user_id": 5,
  "role": "editor"
}
```

**Revoke Permission**
```
DELETE /api/permissions.php?action=revoke&id=1
```

### Features
- Role-based access control (RBAC)
- Project-level permissions
- Audit trail of permission changes
- Automatic notifications on permission changes
- Permission inheritance from project owner

---

## Activity Logging

### Logged Activities
- User registration/login/logout
- Permission grants/revocations
- Report generation
- Pin creation/modification
- Pin resolution
- Reply creation
- File uploads

### Log Details
- User ID
- Action type
- Resource type and ID
- Timestamp
- IP address
- Additional details (JSON)

---

## Security Considerations

1. **Password Security**
   - Bcrypt hashing with salt
   - Minimum password requirements
   - Password reset functionality

2. **Session Management**
   - Secure session tokens
   - Session expiration
   - CSRF protection

3. **Authorization**
   - Permission checks on all endpoints
   - Role-based access control
   - Resource-level authorization

4. **Audit Trail**
   - All user actions logged
   - IP address tracking
   - Timestamp recording

5. **Data Protection**
   - SQL injection prevention (prepared statements)
   - XSS protection (HTML escaping)
   - HTTPS enforcement recommended
