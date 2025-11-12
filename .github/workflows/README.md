# CI/CD Pipeline Documentation

This directory contains GitHub Actions workflows for the SS Core Licenses plugin.

## Workflows

### 1. CI Pipeline (`ci.yml`)

Runs on every push and pull request to main/master/develop branches.

**Jobs:**
- **PHP Syntax Check**: Validates PHP syntax for all plugin files
- **WordPress Coding Standards**: Runs PHPCS to check code quality
- **Security Scan**: Scans for known security vulnerabilities
- **Build Plugin**: Creates a ZIP package of the plugin
- **Create Release**: Automatically creates GitHub release with plugin package

### 2. Plugin Deploy (`plugin-deploy.yml`)

Runs when a new release is published.

**Jobs:**
- **Deploy to WordPress.org**: Automatically deploys the plugin to WordPress.org SVN repository

## Setup

### Required Secrets

For WordPress.org deployment, add these secrets to your GitHub repository:

1. Go to Settings → Secrets and variables → Actions
2. Add the following secrets:
   - `WP_ORG_USERNAME`: Your WordPress.org username
   - `WP_ORG_PASSWORD`: Your WordPress.org application password (not your account password)

### Optional Secrets

- `WPSCAN_API_TOKEN`: For WPScan security scanning (optional)

## Local Development

### Install Dependencies

```bash
cd wp-content/plugins/ss-core-licenses
composer install
```

### Run Code Quality Checks

```bash
# Check PHP syntax
composer run lint

# Run PHPCS
composer run phpcs

# Auto-fix coding standards
composer run phpcbf
```

## Workflow Triggers

- **Push to main/master/develop**: Runs CI checks
- **Pull Request**: Runs CI checks
- **Release Created**: Builds plugin package and creates GitHub release
- **Release Published**: Deploys to WordPress.org (if configured)

## Build Artifacts

Plugin ZIP packages are automatically created and uploaded as GitHub Actions artifacts. They are available for 30 days after the workflow run.

## Troubleshooting

### PHPCS Errors

If you see PHPCS errors, you can:
1. Auto-fix many issues: `composer run phpcbf`
2. Review the `phpcs.xml` configuration file
3. Add exceptions for specific rules if needed

### Build Failures

- Check PHP version compatibility (requires PHP 7.4+)
- Ensure all required files are present
- Verify plugin header information in `ss-core-licenses.php`

