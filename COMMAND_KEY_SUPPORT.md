# Command Key Support Implementation

## Overview

Added support for `command_key` parameter to both CmeshPushContentService and SystemdCommandExecutorService, allowing the pushfin.sh script to receive and handle different deployment commands (default, cloudflare, bunny, aws, keycdn, or custom commands).

## Changes Made

### 1. CmeshPushContentService Updated

**File**: `src/Service/CmeshPushContentService.php`

**Method**: `executeCommandDirect()`

**Change**: Added `-k %s` parameter to the command string to pass command_key to pushfin.sh

```php
// Before:
$command = sprintf(
  '%s -o %s -n %s -b %s',
  escapeshellarg($script),
  escapeshellarg($org),
  escapeshellarg($name),
  escapeshellarg($bucket)
);

// After:
$command = sprintf(
  '%s -o %s -n %s -b %s -k %s',
  escapeshellarg($script),
  escapeshellarg($org),
  escapeshellarg($name),
  escapeshellarg($bucket),
  escapeshellarg($command_key)
);
```

### 2. SystemdCommandExecutorService Already Supported

**File**: `src/Service/SystemdCommandExecutorService.php`

The systemd service already properly handles command_key by including it in the instance name format: `org:name:command_key[:bucket]`

## Command Flow

### For CmeshPushContentService (mod_php with SSH)

```
Form → executeCommandDirect() → Build Command → executeCommand() → SSH → pushfin.sh
```

**Example command generated**:
```bash
ssh user@remote-server '/opt/cmesh/scripts/pushfin.sh -o "mars" -n "mpvg" -b "" -k "cloudflare"'
```

### For SystemdCommandExecutorService (PHP-FPM with systemd)

```
Form → executeCommandDirect() → Build Instance → systemctl start → pushfin-systemd.sh → pushfin.sh
```

**Example instance format**:
```
cmesh-build@mars:mpvg:cloudflare
```

**Result**: pushfin-systemd.sh extracts command_key and calls pushfin.sh with appropriate parameters

## Expected pushfin.sh Updates

Your pushfin.sh script should now accept the `-k` parameter:

```bash
#!/bin/bash

# pushfin.sh - Updated to accept command_key parameter

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -o|--org)
            ORG="$2"
            shift 2
            ;;
        -n|--name)
            NAME="$2"
            shift 2
            ;;
        -b|--bucket)
            BUCKET="$2"
            shift 2
            ;;
        -k|--command-key)
            COMMAND_KEY="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

# Set default command key if not provided
COMMAND_KEY=${COMMAND_KEY:-"default"}

echo "Organization: $ORG"
echo "Name: $NAME"
echo "Bucket: $BUCKET"
echo "Command Key: $COMMAND_KEY"

# Handle different command keys
case "$COMMAND_KEY" in
    "default")
        echo "Executing default deployment"
        # Your default deployment logic here
        ;;
    "cloudflare")
        echo "Executing Cloudflare deployment"
        # Your Cloudflare deployment logic here
        ;;
    "bunny")
        echo "Executing Bunny CDN deployment"
        # Your Bunny CDN deployment logic here
        ;;
    "aws")
        echo "Executing AWS deployment"
        # Your AWS deployment logic here
        ;;
    "keycdn")
        echo "Executing KeyCDN deployment"
        # Your KeyCDN deployment logic here
        ;;
    *)
        echo "Unknown command key: $COMMAND_KEY" >&2
        echo "Available command keys: default, cloudflare, bunny, aws, keycdn" >&2
        exit 1
        ;;
esac
```

## Available Command Keys

The following command keys are supported:

| Command Key | Description | Script Used |
|-------------|-------------|-------------|
| `default` | Default deployment | `pushfin.sh` |
| `cloudflare` | Cloudflare deployment | `deploy-cloudflare.sh` |
| `bunny` | Bunny CDN deployment | `deploy-bunny.sh` |
| `aws` | AWS S3 deployment | `deploy-aws.sh` |
| `keycdn` | KeyCDN deployment | `deploy-keycdn.sh` |
| `custom` | Custom deployment | `deploy-{custom}.sh` |

## Usage Examples

### In Environment Configuration

```php
<?php
// sites/default/files/cmesh-config/production.env.inc
$org = 'your-org';
$name = 'your-site';
$bucket = 'your-bucket';

// Custom commands can be defined
$custom_commands = [
  'staging' => [
    'label' => 'Deploy to Staging',
    'description' => 'Deploy to staging environment',
  ],
  'production' => [
    'label' => 'Deploy to Production', 
    'description' => 'Deploy to production environment',
  ],
];
```

### In Form Submission

```php
// Form automatically calls:
$result = $this->commandExecutor->executeCommandDirect(
    'mars',           // $org
    'mpvg',           // $name  
    'cloudflare',     // $command_key
    'my-bucket'       // $bucket
);
```

### Generated Commands

**CmeshPushContentService generates**:
```bash
# For default command
ssh user@server '/opt/cmesh/scripts/pushfin.sh -o 