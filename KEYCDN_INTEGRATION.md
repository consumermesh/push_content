# KeyCDN Integration Guide

## Overview

KeyCDN is a content delivery network (CDN) that can be integrated with the cmesh_push_content module using rsync deployment. This guide explains how to set up and use KeyCDN deployments.

## How KeyCDN Integration Works

The KeyCDN integration:
1. Uses rsync to upload files to your KeyCDN push zone
2. Leverages the existing bucket parameter system (where bucket = push zone name)
3. Follows the simplified architecture (direct parameter passing)

## Prerequisites

1. **KeyCDN Account**: You need an active KeyCDN account
2. **Push Zone**: Create a push zone in your KeyCDN dashboard
3. **SSH Access**: Ensure you have SSH key access to `rsync.keycdn.com`
4. **Deployment Script**: The `deploy-keycdn.sh` script must be present and executable

## Setup Instructions

### Step 1: Prepare the Deployment Script

```bash
# Copy the example script
sudo cp config/deploy-keycdn.sh.example /opt/cmesh/scripts/deploy-keycdn.sh

# Make it executable
sudo chmod +x /opt/cmesh/scripts/deploy-keycdn.sh

# Verify it exists
ls -la /opt/cmesh/scripts/deploy-keycdn.sh
```

### Step 2: Configure SSH Access

KeyCDN uses SSH keys for rsync authentication. You need to:

1. **Generate SSH key** (if you don't have one):
   ```bash
   ssh-keygen -t rsa -b 4096 -f ~/.ssh/keycdn_rsa
   ```

2. **Add SSH key to KeyCDN**:
   - Log into your KeyCDN dashboard
   - Go to Account > SSH Keys
   - Add your public key: `cat ~/.ssh/keycdn_rsa.pub`

3. **Test SSH connection**:
   ```bash
   ssh -i ~/.ssh/keycdn_rsa spfoos@rsync.keycdn.com
   ```

### Step 3: Configure Your Environment

Add KeyCDN configuration to your `.env.inc` file:

```php
<?php

/**
 * @file
 * Environment configuration with KeyCDN support
 */

$org = 'your-company';
$name = 'your-website';

// KeyCDN push zone (this becomes the "bucket" parameter)
$bucket = 'your-push-zone-name';

// Define custom commands
$custom_commands = [
  'keycdn' => [
    'label' => 'Deploy to KeyCDN',
    'description' => 'Deploy website to KeyCDN via rsync',
  ],
  'aws' => [
    'label' => 'Deploy to AWS S3',
    'description' => 'Deploy to AWS S3 bucket',
  ],
  // ... other commands
];
```

### Step 4: Environment-Specific Configuration

**Development Environment (`dev.env.inc`):**
```php
$org = 'mycompany';
$name = 'dev-site';
$bucket = 'mycompany-dev-push-zone';
```

**Production Environment (`prod.env.inc`):**
```php
$org = 'mycompany';
$name = 'production-site';
$bucket = 'mycompany-prod-push-zone';
```

## Usage

### Deploy to KeyCDN

1. Navigate to your Drupal admin: `/admin/config/system/cmesh-push-content`
2. Click the **"Deploy to KeyCDN"** button
3. Monitor the deployment progress in the output area

### Expected Output

```
=== Cmesh Build Started ===
Instance: mycompany:website:keycdn:mycompany-push-zone
Organization: mycompany
Name: website
Command Key: keycdn
Bucket: mycompany-push-zone
Started: 2025-01-15 10:30:45
User: www-data
Working Directory: /
NODE_OPTIONS: not set

=== Executing: KeyCDN deployment ===
Command: /opt/cmesh/scripts/deploy-keycdn.sh -o 'mycompany' -n 'website' --bucket 'mycompany-push-zone'

=== Cmesh Build Completed ===
Completed: 2025-01-15 10:31:02
Total duration: 17 seconds
```

## KeyCDN Push Zones vs Buckets

In the KeyCDN context:
- **"Bucket" parameter** = **KeyCDN Push Zone name**
- The push zone acts like a bucket where you upload content
- KeyCDN then serves this content from their CDN edge locations

## Advanced Configuration

### Dynamic Push Zone Names
```php
$org = 'mycompany';
$name = 'site';
$environment = 'dev'; // or 'prod', 'staging'
$bucket = 'mycompany-' . $environment . '-push-zone';
// Results in: mycompany-dev-push-zone
```

### Special Characters in Push Zone Names
The system handles URL encoding for push zone names with special characters:
```php
$org = 'company:division';
$name = 'project:environment';
$bucket = 'company:division:push-zone';
// Will be encoded as: company%3Adivision:project%3Aenvironment:keycdn:company%3Adivision%3Apush-zone
```

## Troubleshooting

### "Error: KeyCDN deployment requires a bucket (push zone) to be configured"
**Cause**: No `$bucket` parameter defined in your `.env.inc` file
**Solution**: Add `$bucket = 'your-push-zone-name';` to your configuration

### "Error: /opt/cmesh/scripts/deploy-keycdn.sh not found"
**Cause**: The deployment script is missing
**Solution**: Copy and make executable: `sudo cp config/deploy-keycdn.sh.example /opt/cmesh/scripts/deploy-keycdn.sh && sudo chmod +x /opt/cmesh/scripts/deploy-keycdn.sh`

### SSH Connection Failures
**Cause**: SSH key authentication issues
**Solution**:
1. Verify SSH key is added to KeyCDN dashboard
2. Test connection: `ssh -i ~/.ssh/keycdn_rsa spfoos@rsync.keycdn.com`
3. Check file permissions on SSH keys

### Deployment Failures
**Cause**: Various issues (permissions, paths, etc.)
**Solution**:
1. Check systemd logs: `journalctl -u cmesh-build@your-instance -f`
2. Verify build directory exists: `/opt/cmesh/previews/$ORG-$NAME/fe/build`
3. Test rsync manually: `rsync -rtvz --dry-run /path/to/build/ spfoos@rsync.keycdn.com:your-push-zone/`

### Instance Parsing Errors
**Cause**: Special characters in org/name/bucket not properly handled
**Solution**: The system automatically URL-encodes special characters - check the logs for the encoded instance name

## Security Considerations

1. **SSH Keys**: Keep your SSH private keys secure (`~/.ssh/keycdn_rsa`)
2. **KeyCDN Credentials**: Never commit KeyCDN API keys to version control
3. **File Permissions**: Ensure deployment scripts have appropriate permissions
4. **Push Zone Access**: Limit push zone access to necessary users only

## Performance Tips

1. **Incremental Deployments**: rsync only transfers changed files
2. **Compression**: The script uses rsync with compression (`-z` flag)
3. **Exact Timestamps**: Uses `--exact-timestamps` for accurate change detection
4. **Proper Permissions**: Sets correct file permissions (644 for files, 2755 for directories)

## Comparison with Other CDN Providers

| Feature | KeyCDN | AWS S3 | Cloudflare | Bunny CDN |
|---------|--------|---------|------------|-----------|
| Protocol | rsync | AWS CLI | API | API |
| Authentication | SSH Keys | AWS Keys | API Token | Access Key |
| Deployment Method | Push Zone | S3 Bucket | Zone ID | Storage Zone |
| Parameter | `$bucket` (push zone) | `$bucket` (S3 bucket) | Env var (zone ID) | Env var (storage zone) |

## Migration from Other Providers

To switch from another CDN to KeyCDN:

1. **Set up KeyCDN account and push zone**
2. **Configure SSH access**
3. **Update `.env.inc` with KeyCDN configuration**
4. **Test deployment**
5. **Update DNS to point to KeyCDN**
6. **Remove old CDN configuration**

## Summary

KeyCDN integration provides:
- ✅ **Simple rsync-based deployment**
- ✅ **Leverages existing bucket parameter system**
- ✅ **SSH key authentication**
- ✅ **Incremental uploads**
- ✅ **Proper file permissions**
- ✅ **Environment-specific push zones**
- ✅ **Special character support**

The integration follows the same simplified architecture pattern as other CDN providers, making it consistent and easy to use alongside AWS S3, Cloudflare, and Bunny CDN deployments.