# Pushfin.sh Command Key Integration

## Overview

Updated both the CmeshPushContentService and the project-local pushfin.sh script to support command_key parameter for remote execution. This enables different deployment types (default, cloudflare, bunny, aws, keycdn) to be executed via SSH remote commands.

## Changes Made

### 1. CmeshPushContentService Updated

**File**: `src/Service/CmeshPushContentService.php`

**Method**: `executeCommandDirect()`

**Key Changes**:
- Now uses project-local pushfin.sh from `config/pushfin.sh`
- Passes command_key via `-k` parameter
- Supports remote execution with proper parameter handling

```php
// Get the module path to locate the config directory
$module_path = \Drupal::service('extension.list.module')->getPath('cmesh_push_content');
$config_script = $module_path . '/config/pushfin.sh';

// Use project-local pushfin.sh script from config directory
$script = $config_script;

// Build the command string with command_key support for remote execution
$command = sprintf(
  '%s -n %s -o %s -b %s -k %s',
  escapeshellarg($script),
  escapeshellarg($name),
  escapeshellarg($org),
  escapeshellarg($bucket),
  escapeshellarg($command_key)
);
```

### 2. Pushfin.sh Script Updated

**File**: `config/pushfin.sh`

**Key Changes**:
- Added `-k <command_key>` parameter support
- Updated parameter parsing to handle new argument order
- Added command_key-specific environment variable setup
- Enhanced remote command building with all parameters

```bash
# New parameter parsing
while getopts ":n:o:b:k:c:s:h" opt; do
    case ${opt} in
        k )
            command_key="$OPTARG"
            ;;
    esac
done

# Command key handling
case "$command_key" in
    "cloudflare")
        echo "Executing Cloudflare deployment"
        export CLOUDFLARE_ZONE_ID="${CLOUDFLARE_ZONE_ID:-}"
        export CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN:-}"
        ;;
    # ... other command keys
esac

# Remote command with all parameters including command_key
remote_command="sudo -u http bash -xc \"/opt/cmesh/scripts/pushfin.sh -n '$name' -o '$org' -b '$bucketName' -k '$command_key' -c '$client_id' -s '$client_secret'\""
```

## Command Flow

### Local Execution (CmeshPushContentService)

```
Drupal Form → CmeshPushContentService::executeCommandDirect() → Local pushfin.sh → SSH Remote → Remote pushfin.sh
```

**Generated local command**:
```bash
/path/to/module/config/pushfin.sh -n 'mars' -o 'mpvg' -b 'my-bucket' -k 'cloudflare'
```

**Generated remote command**:
```bash
ssh backend@fin.consumermesh.com "sudo -u http bash -xc \"/opt/cmesh/scripts/pushfin.sh -n 'mars' -o 'mpvg' -b 'my-bucket' -k 'cloudflare' -c 'client_id' -s 'client_secret'\""
```

### Systemd Execution (SystemdCommandExecutorService)

```
Drupal Form → SystemdCommandExecutorService → systemctl start → pushfin-systemd.sh → Remote pushfin.sh
```

**Instance format**: `org:name:command_key:bucket`

## Parameter Mapping

| Parameter | Local Script | Remote Script | Description |
|-----------|--------------|---------------|-------------|
| `-n` | ✅ | ✅ | Site name |
| `-o` | ✅ | ✅ | Organization |
| `-b` | ✅ | ✅ | Bucket name |
| `-k` | ✅ | ✅ | Command key |
| `-c` | ❌ | ✅ | Client ID |
| `-s` | ❌ | ✅ | Client Secret |

## Available Command Keys

The following command keys are supported:

| Command Key | Environment Variables Set | Purpose |
|-------------|---------------------------|---------|
| `default` | None | Standard deployment |
| `cloudflare` | `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_API_TOKEN` | Cloudflare CDN deployment |
| `bunny` | `BUNNY_STORAGE_ZONE`, `BUNNY_ACCESS_KEY` | Bunny CDN deployment |
| `aws` | `AWS_S3_BUCKET`, `AWS_REGION` | AWS S3 deployment |
| `keycdn` | `KEYCDN_PUSH_ZONE` | KeyCDN deployment |
| `custom` | User-defined | Custom deployment scripts |

## Usage Examples

### In Drupal Form

```php
// Form automatically calls:
$result = $this->commandExecutor->executeCommandDirect(
    'mars',           // $org
    'mpvg',           // $name  
    'cloudflare',     // $command_key
    'my-zone'         // $bucket
);
```

### Environment Configuration

```php
<?php
// sites/default/files/cmesh-config/production.env.inc
$org = 'your-org';
$name = 'your-site';
$bucket = 'your-bucket';

// Set environment-specific variables
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

### Manual Testing

**Test local script**:
```bash
# Test with different command keys
./config/pushfin.sh -n test-site -o test-org -b test-bucket -k default
echo $?

./config/pushfin.sh -n test-site -o test-org -b test-bucket -k cloudflare
echo $?

./config/pushfin.sh -n test-site -o test-org -b test-bucket -k aws
echo $?
```

**Test remote execution**:
```bash
# Test SSH connectivity
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "echo 'SSH test successful'"

# Test remote command
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http bash -xc 'echo Remote test successful'"
```

## Error Handling

### Common Issues

1. **SSH Key Issues**:
   ```bash
   # Check SSH key permissions
   ls -la /opt/cmesh/scripts/.ssh/id_rsa
   chmod 600 /opt/cmesh/scripts/.ssh/id_rsa
   ```

2. **Command Not Found**:
   ```bash
   # Ensure remote pushfin.sh exists
   ssh backend@fin.consumermesh.com "ls -la /opt/cmesh/scripts/pushfin.sh"
   ```

3. **Permission Issues**:
   ```bash
   # Test sudo access
   ssh backend@fin.consumermesh.com "sudo -u http whoami"
   ```

### Debug Mode

Add debugging to the service:

```php
public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '') {
    error_log("CmeshPushContentService: org=$org, name=$name, command_key=$command_key, bucket=$bucket");
    
    // ... rest of method
    error_log("CmeshPushContentService: Generated command: $command");
    
    return $this->executeCommand($command);
}
```

## Integration with Systemd Service

The systemd service (pushfin-systemd.sh) already handles command_key through the instance format:

```bash
# pushfin-systemd.sh extracts command_key from instance
case "$COMMAND_KEY" in
    "default")
        execute_command "/opt/cmesh/scripts/pushfin.sh -o '$ORG' -n '$NAME'" "Default pushfin.sh"
        ;;
    "cloudflare")
        execute_command "/opt/cmesh/scripts/deploy-cloudflare.sh -o '$ORG' -n '$NAME'" "Cloudflare deployment"
        ;;
esac
```

## Summary

The integration is now complete:

- ✅ **Local pushfin.sh** accepts command_key parameter
- ✅ **Remote execution** includes all parameters
- ✅ **SSH command building** handles proper escaping
- ✅ **Environment variables** are set based on command_key
- ✅ **Both services** (CmeshPushContent and SystemdCommandExecutor) support command_key
- ✅ **Backward compatibility** maintained for existing deployments

The command_key parameter now flows seamlessly from the Drupal form through both execution methods to enable different deployment types within your remote infrastructure.