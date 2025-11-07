# Club Events Manager

Event management platform for engineering school clubs with participant registration, email communications, and certificate generation.

## ✨ Features

### For Participants
- Create account and login
- Browse available events
- Register for events with email validation
- Receive event reminders and communications
- Receive participation certificates
- View registration history

### For Organizers
- Create and manage events (add, modify, delete)
- Manage participants (accept/reject registrations)
- Send emails to participants
- Share files (program, venue map, etc.)
- Generate and send participation certificates
- View event statistics
- Monitor registration and payment status

### For Administrators
- Add and remove organizers
- View site-wide statistics
- Monitor platform activity
- Manage system users

## 🛠 Technologies Used

### Backend
- **PHP 8.8+**: Server-side language
- **MySQL 8.0+**: Relational database
- **PDO**: Data access interface

### Frontend
- **HTML5, CSS3, JavaScript**
- **Bootstrap 5**: Responsive CSS framework

### Libraries
- **PHPMailer**: SMTP email sending
- **TCPDF**: PDF generation for certificates
- **Chart.js**: Statistical charts

## 🚀 Installation

### Prerequisites
- PHP 8.8 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Composer

### Steps

1. **Clone the repository**
```bash
git clone https://github.com/yourusername/club-events-manager.git
cd club-events-manager
```

2. **Install dependencies**
```bash
composer install
```

3. **Configure the web server**
   - Point the document root to the `public/` directory

## 💾 Database Setup

1. **Create the database**
```sql
CREATE DATABASE gestion_evenements CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. **Import the schema**
```bash
mysql -u your_username -p gestion_evenements < migrations/schema.sql
```

### Main Entities

- **Utilisateur**: User information (participants, organizers, admins)
- **Evenement**: Event details (title, description, date, location, capacity, price)
- **Inscription**: Participant registrations with status tracking
- **Email**: Email history
- **Fichier**: Shared files
- **Attestation**: Generated certificates
- **Club**: Club information

## ⚙️ Configuration

1. **Database configuration**

Edit `config/config.php` with your database credentials.

2. **Email configuration**

Configure PHPMailer settings for SMTP email sending in the relevant files.

## 📁 Project Structure

```
/CAMPUSEVENTS
├── config/
│   └── config.php                      # MySQL connection
├── cron/
│   └── cleanup_expired_inscriptions.php # Cleanup task
├── includes/
│   ├── email_functions.php             # Email utilities
│   ├── footer.php                      # Common footer
│   └── navbar.php                      # Navigation bar
├── migrations/
│   └── script.sql                      # Database schema
├── public/
│   ├── admin/                          # Admin panel
│   │   ├── admin_event_detail.php
│   │   ├── admin_tous_evenements.php
│   │   ├── change_admin_password.php
│   │   ├── dashboard_admin.php
│   │   ├── delete_club.php
│   │   ├── delete_organisateur.php
│   │   ├── edit_club.php
│   │   ├── edit_organisateur.php
│   │   ├── process_add_club.php
│   │   ├── process_add_organisateur.php
│   │   ├── process_edit_club.php
│   │   └── process_edit_organisateur.php
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css               # Main styles
│   │   └── js/
│   │       └── script.js               # Main scripts
│   ├── auth/                           # Authentication
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── process_login.php
│   │   ├── process_register.php
│   │   ├── register.php
│   │   └── verify.php
│   ├── organisateur/                   # Organizer panel
│   │   ├── change_organisateur_password.php
│   │   ├── dashboard_organisateur.php
│   │   ├── delete_event.php
│   │   ├── get_event_participants.php
│   │   ├── modify_event.php
│   │   ├── process_attestations.php
│   │   ├── process_create_event.php
│   │   ├── process_modify_event.php
│   │   ├── process_send_email.php
│   │   ├── process_update_payment.php
│   │   └── process_update_status.php
│   ├── uploads/                        # Uploaded files
│   │   ├── affiches/                   # Event posters
│   │   ├── attestations/               # Certificates
│   │   └── emails/                     # Email attachments
│   ├── annuler_inscription.php         # Cancel registration
│   ├── confirm_inscription.php         # Confirm registration
│   ├── details.php                     # Event details
│   ├── evenements.php                  # Events list
│   ├── home.php                        # Homepage
│   ├── inscription_evenement.php       # Event registration form
│   ├── inscription_success.php         # Registration success
│   ├── mes_inscriptions.php            # User registrations
│   ├── process_inscription.php         # Process registration
│   └── profile.php                     # User profile
├── vendor/                             # External libraries
├── composer.json                       # Composer dependencies
└── composer.lock                       # Locked versions
```

## 🔒 Security

- **Password hashing**: Using bcrypt
- **CSRF protection**: Tokens for all forms
- **Input validation**: XSS prevention with `htmlspecialchars`
- **CAPTCHA**: For registration and sensitive actions
- **Email validation**: Verification links
- **Prepared statements**: SQL injection prevention
