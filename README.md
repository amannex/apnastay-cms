# OwnStay CMS

> **Backend CMS & API Layer** for the OwnStay Verified Long-Term Rental Platform, built on WordPress with a custom RBAC engine and headless REST API architecture.

---

## 🏠 Overview

OwnStay CMS is a WordPress-based content management system purpose-built for the OwnStay long-term rental platform. It combines a **custom plugin** (`ownstay-core`) for backend logic and a **headless-first theme** (`ownstay`) for REST API delivery, providing a secure and scalable foundation for property management, tenant onboarding, and role-based access control.

---

## 🧱 Project Structure

```
ownstay-cms/
├── wp-content/
│   ├── plugins/
│   │   └── ownstay-core/          # Core plugin – RBAC Engine & REST API
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
│   │       ├── ownstay-core.php   # Plugin entry point
│   │       └── uninstall.php      # Clean uninstall handler
│   └── themes/
│       └── ownstay/               # Headless-first WordPress theme
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
- Managed via `OwnStay_Roles` class with a dynamic capability system

### 🔑 Authentication Layer
- JWT-based authentication handled by `OwnStay_Auth`
- Secure login, token issuance, and refresh flows
- REST API authentication middleware

### 🌐 REST API
All endpoints are served under `/wp-json/ownstay/v1/`:

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
git clone https://github.com/amannex/ownstay-cms.git
cd ownstay-cms
```

### 2. Configure WordPress
Copy and configure your database settings:
```bash
cp wp-config-sample.php wp-config.php
```
Edit `wp-config.php` with your local database credentials:
```php
define( 'DB_NAME', 'ownstay_db' );
define( 'DB_USER', 'your_db_user' );
define( 'DB_PASSWORD', 'your_db_password' );
define( 'DB_HOST', 'localhost' );
```

### 3. Run WordPress Installer
- Navigate to your local server URL (e.g., `http://localhost/ownstay-cms`)
- Complete the WordPress installation wizard

### 4. Activate Plugin & Theme
- Go to **Plugins → Installed Plugins** and activate **OwnStay Core**
- Go to **Appearance → Themes** and activate **OwnStay Theme**

### 5. Verify RBAC Setup
After activating the plugin, the RBAC roles and capabilities are registered automatically via the activation hook.

---

## 🛠️ Development

### Plugin Entry Point
[`ownstay-core.php`](wp-content/plugins/ownstay-core/ownstay-core.php) bootstraps all components in the `plugins_loaded` hook:
- RBAC Roles (`OwnStay_Roles`)
- Authentication (`OwnStay_Auth`)
- REST API Router (`OwnStay_API`)
- Admin Menu (`OwnStay_Admin_Menu`)

### Adding a New API Endpoint
1. Create a new controller in `api/class-{name}-controller.php`
2. Register it in `ownstay-core.php` via `require_once`
3. The controller should extend or register with `OwnStay_API`

---

## 📄 License

This project is licensed under the **GNU General Public License v2 or later**.  
See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

## 👥 Author

**OwnStay Engineering**  
🌐 [ownstay.com](https://ownstay.com)
