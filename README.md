# ApnaStay CMS

> **Backend CMS & API Layer** for the ApnaStay Verified Long-Term Rental Platform, built on WordPress with a custom RBAC engine and headless REST API architecture.

---

## 🏠 Overview

ApnaStay CMS is a WordPress-based content management system purpose-built for the ApnaStay long-term rental platform. It combines a **custom plugin** (`apnastay-core`) for backend logic and a **headless-first theme** (`apnastay`) for REST API delivery, providing a secure and scalable foundation for property management, tenant onboarding, and role-based access control.

---

## 🧱 Project Structure

```
apnastay-cms/
├── wp-content/
│   ├── plugins/
│   │   └── apnastay-core/          # Core plugin – RBAC Engine & REST API
│   │       ├── admin/             # WordPress admin menu integration
│   │       ├── api/               # REST API controllers
│   │       │   ├── class-auth-controller.php
│   │       │   ├── class-user-controller.php
│   │       │   ├── class-property-controller.php
│   │       │   └── class-permission-controller.php
│   │       ├── includes/          # Core logic classes
│   │       │   ├── class-activator.php
│   │       │   ├── class-auth.php
│   │       │   ├── class-api.php
│   │       │   ├── class-roles.php
│   │       │   └── helpers.php
│   │       ├── apnastay-core.php   # Plugin entry point
│   │       └── uninstall.php      # Clean uninstall handler
│   └── themes/
│       └── apnastay/               # Headless-first WordPress theme
│           ├── functions.php
│           ├── style.css
│           └── index.php
└── (WordPress core files)
```

---

## ✨ Features

### 🔐 Role-Based Access Control (RBAC)
- Custom WordPress roles: **Admin**, **Landlord**, **Tenant**, and more
- Granular permission matrix controlling access to properties, users, and API endpoints
- Managed via `ApnaStay_Roles` class with a dynamic capability system

### 🔑 Authentication Layer
- JWT-based authentication handled by `ApnaStay_Auth`
- Secure login, token issuance, and refresh flows
- REST API authentication middleware

### 🌐 REST API
All endpoints are served under `/wp-json/apnastay/v1/`:

| Controller | Endpoints |
|---|---|
| `Auth` | `/login`, `/logout`, `/refresh-token` |
| `Users` | `/users`, `/users/{id}` |
| `Properties` | `/properties`, `/properties/{id}` |
| `Permissions` | `/permissions`, `/permissions/matrix` |

### 🎨 Headless-First Theme
- Minimal WordPress theme with CORS support for decoupled frontends
- Exposes REST API cleanly for any JavaScript frontend (React, Next.js, etc.)
- Custom template hierarchy for headless deployments

---

## ⚙️ Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 8.0 |
| WordPress | ≥ 6.0 (tested up to 6.6) |
| MySQL/MariaDB | ≥ 5.7 |

---

## 🚀 Setup & Installation

### 1. Clone the Repository
```bash
git clone https://github.com/amannex/apnastay-cms.git
cd apnastay-cms
```

### 2. Configure WordPress
Copy and configure your database settings:
```bash
cp wp-config-sample.php wp-config.php
```
Edit `wp-config.php` with your local database credentials:
```php
define( 'DB_NAME', 'apnastay_db' );
define( 'DB_USER', 'your_db_user' );
define( 'DB_PASSWORD', 'your_db_password' );
define( 'DB_HOST', 'localhost' );
```

### 3. Run WordPress Installer
- Navigate to your local server URL (e.g., `http://localhost/apnastay-cms`)
- Complete the WordPress installation wizard

### 4. Activate Plugin & Theme
- Go to **Plugins → Installed Plugins** and activate **ApnaStay Core**
- Go to **Appearance → Themes** and activate **ApnaStay Theme**

### 5. Verify RBAC Setup
After activating the plugin, the RBAC roles and capabilities are registered automatically via the activation hook.

---

## 🛠️ Development

### Plugin Entry Point
[`apnastay-core.php`](wp-content/plugins/apnastay-core/apnastay-core.php) bootstraps all components in the `plugins_loaded` hook:
- RBAC Roles (`ApnaStay_Roles`)
- Authentication (`ApnaStay_Auth`)
- REST API Router (`ApnaStay_API`)
- Admin Menu (`ApnaStay_Admin_Menu`)

### Adding a New API Endpoint
1. Create a new controller in `api/class-{name}-controller.php`
2. Register it in `apnastay-core.php` via `require_once`
3. The controller should extend or register with `ApnaStay_API`

---

## 📄 License

This project is licensed under the **GNU General Public License v2 or later**.  
See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## 👥 Author

**ApnaStay Engineering**  
🌐 [apnastay.com](https://apnastay.com)
