# SecureSoft Core & Licenses Plugin

The foundation plugin for the SecureSoft system, providing encryption, license management, audit logging, and WooCommerce integration.

## Features

- 🔐 **AES-256-GCM Encryption** - Secure encryption for license codes with key rotation support
- 💾 **License Management** - CRUD operations for digital licenses with status tracking
- 🧩 **License Pools** - Organize licenses by product with cached counts
- 🪵 **Audit Logging** - Track all sensitive operations with detailed metadata
- 🛒 **WooCommerce Integration** - Automatic license assignment on order completion
- 📊 **Admin Interface** - Comprehensive admin screens for managing licenses
- 🔌 **REST API** - RESTful endpoints for license management
- 🔔 **Hooks & Filters** - Extensive hooks and filters for extensibility

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce (active)

## Installation

1. Upload the plugin to `/wp-content/plugins/ss-core-licenses/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create database tables on activation

## Database Schema

### ss_licenses
Stores encrypted license codes with status tracking.

### ss_license_pools
Organizes licenses by product with cached counts.

### ss_license_events
Tracks license lifecycle events (reserve, assign, release, revoke, import).

### ss_audit_log
Records all sensitive operations with actor, action, entity, and metadata.

## Usage

### Admin Interface

Navigate to **SecureSoft** in the WordPress admin menu:

- **Licenses** - View and manage all licenses
- **License Pools** - View pool statistics and manage counts
- **Import** - Import licenses from CSV/Excel files
- **Key Management** - Manage encryption keys and rotation
- **Audit Log** - View and export audit logs

### WooCommerce Integration

#### Product Settings

1. Edit a WooCommerce product
2. Go to the "Licenses & Delivery" tab
3. Configure:
   - Delivery Mode (Internal/External)
   - License Source (Pool/Provider)
   - License Pool ID
   - Notes/Policy

#### Order Processing

Licenses are automatically:
- **Reserved** when an order is created
- **Assigned** when payment is completed
- **Released** if order is cancelled or refunded

### REST API

#### Import Licenses
```
POST /wp-json/ss/v1/licenses/import
{
  "product_id": 123,
  "codes": ["LICENSE1", "LICENSE2"]
}
```

#### Get Pool Data
```
GET /wp-json/ss/v1/licenses/pool/{product_id}
```

#### Rotate Keys
```
POST /wp-json/ss/v1/licenses/rotate
{
  "dry_run": false
}
```

#### Get Audit Logs
```
GET /wp-json/ss/v1/audit?action=license_imported&limit=50
```

### Hooks & Filters

#### Actions

- `ss/license/imported` - Fired when a license is imported
- `ss/license/reserved` - Fired when a license is reserved
- `ss/license/assigned` - Fired when a license is assigned
- `ss/license/revoked` - Fired when a license is revoked
- `ss/license/sent_to_customer` - Fired when license is sent to customer
- `ss/audit/log` - Fired when an audit event is logged

#### Filters

- `ss/encrypt/context` - Filter encryption context
- `ss/licenses/assign/strategy` - Filter license assignment strategy
- `ss/licenses/import/row` - Filter import row data

### Encryption

#### Environment Variables

Set encryption keys via environment variables:

```php
define( 'SS_CORE_ENCRYPTION_KEY', 'base64_encoded_key' );
define( 'SS_CORE_BACKUP_KEY', 'backup_key_for_backups' );
```

#### Key Rotation

1. Go to **SecureSoft > Key Management**
2. Click **Rotate Keys**
3. New key version will be created
4. Old keys are retained for decrypting existing licenses

### Capabilities

- `ss_manage_licenses` - Manage licenses
- `ss_view_plain_codes` - View plain license codes
- `ss_manage_keys` - Manage encryption keys
- `ss_view_audit_log` - View audit logs

## Development

### File Structure

```
ss-core-licenses/
├── ss-core-licenses.php
├── src/
│   ├── Autoloader.php
│   ├── Plugin.php
│   ├── Database.php
│   ├── Activator.php
│   ├── Deactivator.php
│   ├── Uninstaller.php
│   ├── Crypto/
│   │   └── Encryption.php
│   ├── KeyStore/
│   │   └── Manager.php
│   ├── Licenses/
│   │   ├── Repository.php
│   │   └── Service.php
│   ├── Pools/
│   │   └── Repository.php
│   ├── Audit/
│   │   └── Logger.php
│   ├── Import/
│   │   └── Importer.php
│   ├── Woo/
│   │   ├── ProductMeta.php
│   │   └── OrderHooks.php
│   ├── Admin/
│   │   ├── Base.php
│   │   └── Screens/
│   │       ├── Licenses.php
│   │       ├── Pools.php
│   │       ├── Import.php
│   │       ├── Keys.php
│   │       └── Audit.php
│   └── REST/
│       └── Controllers/
│           ├── Licenses.php
│           ├── Audit.php
│           └── Rotate.php
└── assets/
    └── admin/
        ├── css/
        │   └── admin.css
        └── js/
            └── admin.js
```

### Testing

Run tests with PHPUnit:

```bash
vendor/bin/phpunit
```

### CI/CD

The plugin includes automated CI/CD pipelines using GitHub Actions:

- **Code Quality**: Automatic PHP syntax checking and WordPress coding standards
- **Security Scanning**: Automated security vulnerability scanning
- **Build Automation**: Automatic plugin package creation
- **Deployment**: Automated deployment to WordPress.org (optional)

See [CI_CD.md](CI_CD.md) for detailed documentation.

**Local Development:**

```bash
# Install dependencies
composer install

# Run code quality checks
composer run lint
composer run phpcs

# Auto-fix coding standards
composer run phpcbf

# Build plugin package
./build.sh  # Linux/Mac
build.bat   # Windows
```

## Security

- All license codes are encrypted using AES-256-GCM
- Encryption keys are stored securely (environment variables recommended)
- Audit logs track all sensitive operations
- Capability-based access control
- Input sanitization and output escaping
- Nonce verification for all actions

## Performance

- Indexed database queries for fast lookups
- Cached pool counts in `ss_license_pools`
- Background processing for large imports (Action Scheduler recommended)
- Optimized queries for license retrieval

## Support

For support, please contact the SecureSoft team or visit [https://securesoft.tech](https://securesoft.tech).

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Core encryption system
- License management
- WooCommerce integration
- Admin interface
- REST API endpoints
- Audit logging

