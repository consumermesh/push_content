# Persistent Configuration Directory Setup

## Problem

When updating the cmesh_push_content module, configuration files like `dev.env.inc`, `staging.env.inc`, and `prod.env.inc` get wiped out because they are stored in the module's `config` directory. Drupal replaces the entire module directory during updates.

## Solution

The module now stores configuration files in a persistent location outside the module directory: `sites/default/files/cmesh-config/`

## Setup Instructions

### 1. Create the Configuration Directory

```bash
# Navigate to your Drupal root
cd /path/to/drupal

# Create the persistent configuration directory
mkdir -p sites/default/files/cmesh-config

# Set proper permissions (adjust as needed for your setup)
chmod 755 sites/default/files/cmesh-config
```

### 2. Move Existing Configuration Files

If you have existing configuration files in the module's config directory:

```bash
# Copy existing config files to new location
cp modules/custom/cmesh_push_content/config/*.env.inc sites/default/files/cmesh-config/

# Or if you're starting fresh, create new ones based on examples
cp modules/custom/cmesh_push_content/config/dev.env.inc.example sites/default/files/cmesh-config/dev.env.inc
cp modules/custom/cmesh_push_content/config/staging.env.inc.example sites/default/files/cmesh-config/staging.env.inc
cp modules/custom/cmesh_push_content/config/prod.env.inc.example sites/default/files/cmesh-config/prod.env.inc
```

### 3. Edit Your Configuration Files

Edit each configuration file in the new location:

**sites/default/files/cmesh-config/dev.env.inc:**
```php
<?php

/**
 * Development environment configuration.
 */

$org = 'your-org';
$name = 'dev-site';
```

**sites/default/files/cmesh-config/staging.env.inc:**
```php
<?php

/**
 * Staging environment configuration.
 */

$org = 'your-org';
$name = 'staging-site';
```

**sites/default/files/cmesh-config/prod.env.inc:**
```php
<?php

/**
 * Production environment configuration.
 */

$org = 'your-org';
$name = 'production-site';
```

### 4. Verify the Setup

1. Clear Drupal cache: `drush cr`
2. Navigate to the cmesh push content form in your Drupal admin
3. You should see the "Push to dev", "Push to staging", and "Push to prod" buttons
4. The configuration files will now persist through module updates

## Security Considerations

### File Permissions

Ensure proper file permissions are set:

```bash
# Restrict access to configuration files
chmod 640 sites/default/files/cmesh-config/*.env.inc

# Ensure web server can read the files
chown www-data:www-data sites/default/files/cmesh-config/*.env.inc
```

### .htaccess Protection

The configuration directory is inside `sites/default/files/` which should already be protected by Drupal's `.htaccess` rules. However, you can add additional protection:

Create `sites/default/files/cmesh-config/.htaccess`:
```apache
# Deny all requests from Apache 2.4+
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>

# Deny all requests from Apache 2.0
<IfModule !mod_authz_core.c>
  Deny from all
</IfModule>
```

### Alternative: Private File System

For enhanced security, you can use Drupal's private file system:

1. Set up private file system in `sites/default/settings.php`:
```php
$settings['file_private_path'] = 'sites/default/private';
```

2. Create the private directory:
```bash
mkdir -p sites/default/private/cmesh-config
chmod 755 sites/default/private
chmod 750 sites/default/private/cmesh-config
```

3. Modify the module code to use private files (requires custom code modification).

## Troubleshooting

### Configuration Files Not Found

If the module can't find your configuration files:

1. Check file permissions:
   ```bash
   ls -la sites/default/files/cmesh-config/
   ```

2. Verify the directory exists:
   ```bash
   drush php:eval "echo \Drupal::service('file_system')->realpath('public://cmesh-config');"
   ```

3. Check PHP syntax of config files:
   ```bash
   php -l sites/default/files/cmesh-config/dev.env.inc
   ```

### Missing Buttons

If the "Push to" buttons don't appear:

1. Ensure configuration files have proper PHP syntax
2. Check that files end with `.env.inc` extension
3. Verify the directory path is correct
4. Clear Drupal cache

### Module Updates

After module updates:

1. Configuration files in `sites/default/files/cmesh-config/` will persist
2. No need to recreate or move configuration files
3. The module will automatically find and use the existing configuration

## Migration from Old Setup

If you were using the old setup (config files in module directory):

1. **Before updating the module**: Copy your existing config files to the new location
2. **Update the module**: The module will continue to work with your existing configuration
3. **Clean up**: After confirming everything works, you can remove the old config files from the module directory

## Backup Recommendations

Since these configuration files contain sensitive environment information:

1. **Regular backups**: Include `sites/default/files/cmesh-config/` in your backup routine
2. **Version control**: Consider using a private repository or secure configuration management system
3. **Documentation**: Keep a record of which environments are configured and their purposes

## File Structure Summary

```
sites/default/files/
└── cmesh-config/
    ├── dev.env.inc      # Development environment
    ├── staging.env.inc  # Staging environment
    ├── prod.env.inc     # Production environment
    └── .htaccess       # Security protection (optional)
```

Your configuration files will now persist through module updates and remain secure!