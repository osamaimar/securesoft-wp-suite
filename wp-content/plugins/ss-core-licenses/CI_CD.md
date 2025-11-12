# CI/CD Setup for SS Core Licenses Plugin

This document describes the Continuous Integration and Continuous Deployment (CI/CD) setup for the SS Core Licenses WordPress plugin.

## Overview

The plugin uses GitHub Actions for automated testing, code quality checks, and deployment. The CI/CD pipeline ensures code quality, security, and automated releases.

## Workflows

### 1. Continuous Integration (`.github/workflows/ci.yml`)

Runs automatically on:
- Push to `main`, `master`, or `develop` branches
- Pull requests to `main`, `master`, or `develop` branches
- Release creation

**Pipeline Steps:**

1. **PHP Syntax Check**
   - Validates PHP syntax for all plugin files
   - Ensures no syntax errors before proceeding

2. **WordPress Coding Standards**
   - Runs PHPCS (PHP CodeSniffer) with WordPress coding standards
   - Checks code quality and adherence to WordPress best practices
   - Uses custom `phpcs.xml` configuration

3. **Security Scan**
   - Scans for known security vulnerabilities
   - Uses Symfony Security Checker
   - Optional WPScan integration

4. **Build Plugin Package**
   - Creates a distributable ZIP file
   - Excludes development files (tests, node_modules, etc.)
   - Uploads as GitHub Actions artifact

5. **Create GitHub Release** (on release events)
   - Automatically creates GitHub release
   - Attaches plugin ZIP package
   - Uses version from plugin header

### 2. WordPress.org Deployment (`.github/workflows/plugin-deploy.yml`)

Runs automatically when a release is published.

**Deployment Steps:**

1. Checks out code
2. Sets up PHP environment
3. Checks out WordPress.org SVN repository
4. Copies plugin files to SVN trunk
5. Creates SVN tag from version
6. Commits to WordPress.org

## Local Development

### Prerequisites

- PHP 7.4 or higher
- Composer
- Git

### Setup

1. **Install Dependencies**

   ```bash
   cd wp-content/plugins/ss-core-licenses
   composer install
   ```

2. **Run Code Quality Checks Locally**

   ```bash
   # Check PHP syntax
   composer run lint
   
   # Run WordPress coding standards
   composer run phpcs
   
   # Auto-fix coding standards issues
   composer run phpcbf
   ```

3. **Build Plugin Package Locally**

   **Linux/Mac:**
   ```bash
   ./build.sh
   ```

   **Windows:**
   ```cmd
   build.bat
   ```

   The ZIP file will be created in the `dist/` directory.

## Configuration Files

### `phpcs.xml`

WordPress Coding Standards configuration file. Defines:
- WordPress coding standards rules
- PHP version compatibility (7.4+)
- Custom exclusions and rules
- Text domain for i18n checks

### `composer.json`

Defines development dependencies:
- `wp-coding-standards/wpcs`: WordPress coding standards
- `phpcompatibility/phpcompatibility-wp`: PHP version compatibility checks
- `squizlabs/php_codesniffer`: PHPCS tool

### `.gitignore`

Excludes from version control:
- `vendor/`: Composer dependencies
- `dist/`: Build artifacts
- IDE files
- OS-specific files

## GitHub Secrets

For WordPress.org deployment, configure these secrets in GitHub:

1. Go to: **Settings → Secrets and variables → Actions**
2. Add:
   - `WP_ORG_USERNAME`: Your WordPress.org username
   - `WP_ORG_PASSWORD`: Your WordPress.org application password

   **Note:** Use an application password, not your account password. Generate one at: https://wordpress.org/user/your-username/applications/

### Optional Secrets

- `WPSCAN_API_TOKEN`: For enhanced security scanning (optional)

## Workflow Triggers

| Event | Workflow | Description |
|-------|----------|-------------|
| Push to main/master/develop | CI Pipeline | Runs all checks and builds package |
| Pull Request | CI Pipeline | Runs all checks (no build) |
| Release Created | CI Pipeline | Builds package and creates release |
| Release Published | Deploy Pipeline | Deploys to WordPress.org |

## Build Artifacts

- Plugin ZIP packages are stored as GitHub Actions artifacts
- Available for download for 30 days
- Automatically attached to GitHub releases

## Troubleshooting

### PHPCS Errors

1. **Auto-fix issues:**
   ```bash
   composer run phpcbf
   ```

2. **Review configuration:**
   - Check `phpcs.xml` for rule exceptions
   - Some rules can be disabled if needed

3. **Common issues:**
   - Missing text domain: Ensure all strings use `ss-core-licenses`
   - Non-prefixed functions: All functions should be prefixed with `ss` or `SS`
   - Escaping: Ensure all output is properly escaped

### Build Failures

1. **Check PHP version:**
   - Ensure PHP 7.4+ is available
   - Check `composer.json` requirements

2. **Verify plugin header:**
   - Ensure `Version:` header is present in `ss-core-licenses.php`
   - Version format: `X.Y.Z` (e.g., `1.0.1`)

3. **File permissions:**
   - Ensure build scripts are executable (Linux/Mac)
   - Check file paths are correct

### Deployment Issues

1. **SVN Authentication:**
   - Verify `WP_ORG_USERNAME` and `WP_ORG_PASSWORD` secrets
   - Use application password, not account password
   - Check username is correct

2. **SVN Repository:**
   - Ensure plugin slug matches WordPress.org slug
   - Verify SVN repository exists

## Best Practices

1. **Run checks locally before pushing:**
   ```bash
   composer run lint
   composer run phpcs
   ```

2. **Fix auto-fixable issues:**
   ```bash
   composer run phpcbf
   ```

3. **Test build locally:**
   ```bash
   ./build.sh  # or build.bat on Windows
   ```

4. **Version management:**
   - Update version in `ss-core-licenses.php` before release
   - Use semantic versioning (MAJOR.MINOR.PATCH)
   - Tag releases in Git

5. **Commit messages:**
   - Use clear, descriptive commit messages
   - Follow conventional commits if possible

## Continuous Improvement

The CI/CD pipeline can be extended with:

- **Unit Tests**: Add PHPUnit tests
- **Integration Tests**: Test WordPress integration
- **E2E Tests**: Browser-based testing
- **Performance Tests**: Check plugin performance
- **Dependency Updates**: Automated dependency updates
- **Changelog Generation**: Auto-generate from commits

## Support

For issues with CI/CD:
1. Check GitHub Actions logs
2. Review this documentation
3. Check WordPress Coding Standards documentation
4. Contact the development team

