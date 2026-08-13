# Bloowing Admin Dashboard

A complete admin dashboard for managing the Bloowing database with full CRUD operations, authentication, and reporting features.

## Features

- **Authentication**: Secure login with password hashing (bcrypt)
- **Dashboard**: KPI cards showing key metrics
- **Admin Users**: Manage admin accounts
- **Technicians**: Full CRUD for technician management
- **Attendance**: Track check-in/out times and status
- **Installations/OT**: Manage work orders with status tracking
- **Materials**: Inventory management with low-stock alerts
- **Technician Materials**: Track issued and returned materials
- **Fleet Management**: Manage cars and drivers
- **Zones**: Manage service zones
- **Export**: CSV export for all major data types
- **Responsive Design**: Works on desktop and tablet

## Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: PHP 8+
- **Database**: MySQL/MariaDB
- **Security**: PDO prepared statements, password hashing, session management

## Installation

### 1. Database Setup

\`\`\`bash
# Create the database and tables (structure only)
mysql -u root -p < database/schema.sql

# Optional: load fictional demo data (includes admin/password login)
mysql -u root -p bloowing_db < database/seed.sql
\`\`\`

### 2. Configure Database Connection

Copy the example config and update credentials:

\`\`\`bash
cp config/database.example.php config/database.php
\`\`\`

\`\`\`php
$host = 'localhost';
$db = 'bloowing_db';
$user = 'root';
$password = '';
\`\`\`

`config/database.php` is gitignored, so your real credentials stay out of git.

### 3. Start PHP Server

\`\`\`bash
# Navigate to project directory
cd /path/to/dashboard

# Start built-in PHP server
php -S localhost:8000
\`\`\`

### 4. Access Dashboard

Open browser and navigate to:
\`\`\`
http://localhost:8000/login.php
\`\`\`

### 5. Login Credentials

- **Username**: admin
- **Password**: password

## Project Structure

\`\`\`
bloowing-dashboard/
├── database/
│   ├── schema.sql         # Database structure (no data)
│   └── seed.sql           # Optional demo data
├── config/
│   ├── database.example.php # Config template (copy to database.php)
│   ├── session.php        # Session management
│   └── helpers.php        # Helper functions
├── assets/
│   ├── css/
│   │   └── styles.css     # Main stylesheet
│   └── js/
│       └── main.js        # JavaScript utilities
├── pages/
│   ├── admin-users.php
│   ├── technicians.php
│   ├── attendance.php
│   ├── installations.php
│   ├── materials.php
│   ├── technician-materials.php
│   ├── cars.php
│   └── zones.php
├── api/
│   ├── logout.php
│   ├── admin-users/
│   ├── technicians/
│   ├── attendance/
│   ├── installations/
│   ├── materials/
│   ├── technician-materials/
│   ├── cars/
│   └── zones/
├── login.php              # Login page
├── index.php              # Dashboard home
└── README.md
\`\`\`

## Features Overview

### Dashboard
- KPI cards showing total technicians, open installations, low-stock materials, today's attendance
- Recent installations table with quick status view

### Admin Users
- Create, edit, delete admin accounts
- Unique username enforcement
- Password hashing with bcrypt

### Technicians
- Full CRUD operations
- Assign zones and specialties
- Contact information management

### Attendance
- Track daily check-in/out times
- Filter by date, technician, status
- Bulk export to CSV
- Add/edit/delete records

### Installations/OT
- Manage work orders with status tracking (In Progress, Completed, Late, Negative)
- Filter by date range, zone, status
- Assign technicians
- Track timestamps and comments
- CSV export

### Materials
- Inventory management
- Low-stock alerts (< 10 units)
- Track material units and descriptions
- Issue/return tracking

### Technician Materials
- Track material issuance to technicians
- Record quantities given and returned
- Link to cars and zones
- Export history

### Fleet Management
- Manage vehicles (matricule, brand, model)
- Assign drivers to cars
- Track vehicle notes

### Zones
- Simple zone lookup management
- Used for filtering and assignment

## Security Features

- **Session Management**: 30-minute timeout
- **Password Hashing**: bcrypt with PHP's password_hash()
- **Prepared Statements**: All database queries use PDO prepared statements
- **Input Sanitization**: All user inputs sanitized with htmlspecialchars()
- **CSRF Protection**: Session tokens for form submissions
- **Authentication**: Login required for all pages

## API Endpoints

All endpoints require authentication and return JSON responses.

### Admin Users
- `POST /api/admin-users/create.php` - Create new admin
- `POST /api/admin-users/update.php` - Update admin
- `POST /api/admin-users/delete.php` - Delete admin

### Technicians
- `POST /api/technicians/create.php` - Create technician
- `POST /api/technicians/update.php` - Update technician
- `POST /api/technicians/delete.php` - Delete technician

### Attendance
- `POST /api/attendance/create.php` - Create record
- `POST /api/attendance/update.php` - Update record
- `POST /api/attendance/delete.php` - Delete record

### Installations
- `POST /api/installations/create.php` - Create installation
- `POST /api/installations/update.php` - Update installation
- `POST /api/installations/delete.php` - Delete installation

### Materials
- `POST /api/materials/create.php` - Create material
- `POST /api/materials/update.php` - Update material
- `POST /api/materials/delete.php` - Delete material

### Technician Materials
- `POST /api/technician-materials/create.php` - Issue material
- `POST /api/technician-materials/update.php` - Update record
- `POST /api/technician-materials/delete.php` - Delete record

### Cars
- `POST /api/cars/create.php` - Add car
- `POST /api/cars/update.php` - Update car
- `POST /api/cars/delete.php` - Delete car

### Zones
- `POST /api/zones/create.php` - Create zone
- `POST /api/zones/update.php` - Update zone
- `POST /api/zones/delete.php` - Delete zone

### Authentication
- `GET /api/logout.php` - Logout and destroy session

## Export Features

All main data tables support CSV export:
- Click "Export CSV" button on any management page
- Downloads data as CSV file with current filters applied
- Excludes action columns

## Customization

### Theming
Edit CSS variables in `assets/css/styles.css`:

\`\`\`css
:root {
    --primary-color: #2563eb;
    --secondary-color: #10b981;
    --danger-color: #ef4444;
    /* ... more variables ... */
}
\`\`\`

### Database Connection
Update credentials in `config/database.php` for production environments.

## Production Deployment

1. **Use HTTPS**: Always use HTTPS in production
2. **Update Database Credentials**: Change default credentials
3. **Set Proper Permissions**: Restrict file permissions appropriately
4. **Enable Error Logging**: Configure PHP error logging
5. **Database Backups**: Set up regular backups
6. **Rate Limiting**: Enable rate limiting at server level
7. **Environment Variables**: Use environment variables for sensitive data

## Troubleshooting

### Database Connection Error
- Check database credentials in `config/database.php`
- Ensure MySQL/MariaDB is running
- Verify database name is correct

### Login Issues
- Clear browser cookies and cache
- Check admin_users table has data
- Verify password is hashed with bcrypt

### Session Timeout
- Session timeout is set to 30 minutes
- Adjust in `config/session.php` if needed

### CSV Export Not Working
- Check browser console for errors
- Ensure table has data
- Verify JavaScript is enabled

## Support

For issues or questions, refer to the database schema in the SQL dump and ensure all tables are properly created.

## License

This dashboard is provided as-is for managing the Bloowing database.
