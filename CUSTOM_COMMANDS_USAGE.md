# Custom Commands Usage Guide

This guide explains how to use the new custom commands functionality in the cmesh_push_content module.

## Overview

The module now supports executing custom scripts with different parameters for different CDN providers like Cloudflare, Bunny CDN, AWS S3, etc. All parameters are defined in the `.env.inc` files.

## Configuration

### Basic Configuration (Default Behavior)

If you don't define custom commands, the module will use the default behavior:

```php
<?php

/**
 * @file
 * Development environment configuration.
 */

$org = 'your-org';
$name = 'your-site-name';
```

This creates a single button: **"Push to dev"** that executes:
```bash
/opt/cmesh/scripts/pushfin.sh -o 'your-org' -n 'your-site-name'
```

### Advanced Configuration (Custom Commands)

You can define multiple custom commands for different CDN providers:

```php
<?php

/**
 * @file
 * Development environment configuration.
 */

$org = 'your-org';
$name = 'your-site-name';

// Define custom commands for different CDN providers
$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id your-zone --api-token $CLOUDFLARE_API_TOKEN',
    'description' => 'Deploy to Cloudflare CDN',
  ],
  'bunny' => [
    'label' => 'Push to Bunny CDN',
    'command' => '/opt/cmesh/scripts/deploy-bunny.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --storage-zone your-storage --access-key $BUNNY_ACCESS_KEY',
    'description' => 'Deploy to Bunny CDN',
  ],
  'aws' => [
    'label' => 'Push to AWS S3',
    'command' => '/opt/cmesh/scripts/deploy-aws.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --bucket your-bucket --region us-east-1',
    'description' => 'Deploy to AWS S3',
  ],
  'default' => [
    'label' => 'Push to Dev (Default)',
    'command' => '/opt/cmesh/scripts/pushfin.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name),
    'description' => 'Default push to development environment',
  ],
];
```

### Colon Handling in Organization/Site Names

If your organization or site names contain colons (e.g., `company:division`), the system automatically handles them:

```php
<?php
$org = 'company:division';  // This works!
$name = 'project:staging';  // This also works!

$custom_commands = [
  'cloudflare' => [
    'label' => 'Push to Cloudflare',
    'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id $CLOUDFLARE_ZONE_ID --api-token $CLOUDFLARE_API_TOKEN',
    'description' => 'Deploy to Cloudflare CDN',
  ],
];
```

The system automatically URL-encodes colons in organization and site names to prevent parsing issues with the systemd service. This ensures that names like `company:division` or `project:staging` work correctly.

This creates multiple buttons:
- **"Push to Cloudflare"** - executes Cloudflare deployment script
- **"Push to Bunny CDN"** - executes Bunny CDN deployment script  
- **"Push to AWS S3"** - executes AWS deployment script
- **"Push to Dev (Default)"** - executes the default pushfin.sh script

## Environment Variables

You can use environment variables in your commands for sensitive data:

```php
'cloudflare' => [
  'label' => 'Push to Cloudflare',
  'command' => '/opt/cmesh/scripts/deploy-cloudflare.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --zone-id $CLOUDFLARE_ZONE_ID --api-token $CLOUDFLARE_API_TOKEN',
  'description' => 'Deploy to Cloudflare CDN',
],
```

Set these environment variables in your system or web server configuration:
- `$CLOUDFLARE_API_TOKEN` - Cloudflare API token
- `$CLOUDFLARE_ZONE_ID` - Cloudflare zone ID
- `$BUNNY_ACCESS_KEY` - Bunny CDN access key
- `$BUNNY_STORAGE_ZONE` - Bunny CDN storage zone
- `$AWS_ACCESS_KEY_ID` - AWS access key
- `$AWS_SECRET_ACCESS_KEY` - AWS secret key
- `$AWS_S3_BUCKET` - AWS S3 bucket name
- `$AWS_REGION` - AWS region

## Script Requirements

### Default Script
The default script `/opt/cmesh/scripts/pushfin.sh` should accept these parameters:
```bash
pushfin.sh -o <org> -n <name> [-b <bucket>]
```

### Custom Scripts
Your custom deployment scripts should follow similar patterns:

#### Cloudflare Script Example
```bash
#!/bin/bash
# deploy-cloudflare.sh

while [[ $# -gt 0 ]]; do
  case $1 in
    -o) org="$2"; shift 2 ;;
    -n) name="$2"; shift 2 ;;
    --zone-id) zone_id="$2"; shift 2 ;;
    --api-token) api_token="$2"; shift 2 ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# Deploy to Cloudflare
# ... your deployment logic here ...
```

#### Bunny CDN Script Example
```bash
#!/bin/bash
# deploy-bunny.sh

while [[ $# -gt 0 ]]; do
  case $1 in
    -o) org="$2"; shift 2 ;;
    -n) name="$2"; shift 2 ;;
    --storage-zone) storage_zone="$2"; shift 2 ;;
    --access-key) access_key="$2"; shift 2 ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# Deploy to Bunny CDN
# ... your deployment logic here ...
```

#### AWS S3 Script Example
```bash
#!/bin/bash
# deploy-aws.sh

while [[ $# -gt 0 ]]; do
  case $1 in
    -o) org="$2"; shift 2 ;;
    -n) name="$2"; shift 2 ;;
    --bucket) bucket="$2"; shift 2 ;;
    --region) region="$2"; shift 2 ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# Deploy to AWS S3
# ... your deployment logic here ...
```

## Systemd Integration

When using the systemd service, the module will:

1. Parse your command to determine the CDN provider (cloudflare, bunny, aws, etc.)
2. Create a systemd service instance like `cmesh-build@mars:mpvg:cloudflare`
3. Execute the appropriate deployment script based on the command key

The systemd wrapper script (`/opt/cmesh/scripts/pushfin-systemd.sh`) will:
- Extract org, name, and command key from the instance name
- Execute the appropriate deployment script
- Handle environment variables
- Provide proper logging

## Examples by Use Case

### Multi-Region Deployment
```php
$custom_commands = [
  'us-east' => [
    'label' => 'Deploy to US East',
    'command' => '/opt/cmesh/scripts/deploy.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --region us-east-1 --bucket us-east-bucket',
    'description' => 'Deploy to US East region',
  ],
  'eu-west' => [
    'label' => 'Deploy to EU West',
    'command' => '/opt/cmesh/scripts/deploy.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --region eu-west-1 --bucket eu-west-bucket',
    'description' => 'Deploy to EU West region',
  ],
  'asia-pacific' => [
    'label' => 'Deploy to Asia Pacific',
    'command' => '/opt/cmesh/scripts/deploy.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --region ap-southeast-1 --bucket apac-bucket',
    'description' => 'Deploy to Asia Pacific region',
  ],
];
```

### Staging vs Production
```php
// In staging.env.inc
$custom_commands = [
  'staging' => [
    'label' => 'Deploy to Staging',
    'command' => '/opt/cmesh/scripts/deploy-staging.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name),
    'description' => 'Deploy to staging environment',
  ],
];

// In prod.env.inc
$custom_commands = [
  'production' => [
    'label' => 'Deploy to Production',
    'command' => '/opt/cmesh/scripts/deploy-production.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name) . ' --confirm-production',
    'description' => 'Deploy to production environment',
  ],
];
```

### CDN Failover Strategy
```php
$custom_commands = [
  'primary-cdn' => [
    'label' => 'Deploy to Primary CDN',
    'command' => '/opt/cmesh/scripts/deploy-primary.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name),
    'description' => 'Deploy to primary CDN provider',
  ],
  'backup-cdn' => [
    'label' => 'Deploy to Backup CDN',
    'command' => '/opt/cmesh/scripts/deploy-backup.sh -o ' . escapeshellarg($org) . ' -n ' . escapeshellarg($name),
    'description' => 'Deploy to backup CDN provider',
  ],
];
```

## Migration from Old Format

If you have existing `.env.inc` files without custom commands, they will continue to work exactly as before. The module automatically falls back to the default behavior when no `$custom_commands` array is defined.

To migrate to custom commands:

1. Add the `$custom_commands` array to your `.env.inc` file
2. Define your custom commands with appropriate labels and scripts
3. Make sure your custom scripts exist and are executable
4. Set up any required environment variables

## Troubleshooting

### Commands Not Showing Up
- Check that your `.env.inc` file is in the correct location: `sites/default/files/cmesh-config/`
- Verify the file has the correct format and no syntax errors
- Check Drupal logs for any PHP errors

### Scripts Not Executing
- Ensure your custom scripts exist in `/opt/cmesh/scripts/`
- Make sure scripts are executable: `chmod +x /opt/cmesh/scripts/your-script.sh`
- Check script permissions (should be readable by web server user)
- Verify environment variables are set correctly

### Systemd Service Issues
- Check systemd service status: `systemctl status cmesh-build@your-instance`
- View service logs: `journalctl -u cmesh-build@your-instance`
- Ensure the systemd wrapper script is updated: `/opt/cmesh/scripts/pushfin-systemd.sh`
- Verify environment variables are available to the systemd service

### Environment Variable Issues
- Check if environment variables are set: `echo $CLOUDFLARE_API_TOKEN`
- Make sure variables are available to the web server process
- Consider setting variables in systemd service file or web server configuration
- Test scripts manually with the same environment variables