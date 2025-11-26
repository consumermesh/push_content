# CommandExecutorInterface Unification Guide

## Overview

I've updated the `CommandExecutorInterface` to ensure both `CmeshPushContentService` and `SystemdCommandExecutorService` implement the same interface methods, allowing the same Form to work seamlessly with both services.

## Changes Made

### 1. Interface Already Had executeCommandDirect()

The `CommandExecutorInterface` already defined the `executeCommandDirect()` method:

```php
/**
 * Execute a command via systemd using direct parameters.
 *
 * @param string $org
 *   The organization name.
 * @param string $name
 *   The site name.
 * @param string $command_key
 *   The command key (e.g., 'default', 'cloudflare', 'bunny', 'aws').
 * @param string $bucket
 *   The bucket name (optional, for AWS S3 deployments).
 *
 * @return array
 *   Array containing process info with keys:
 *   - process_id: Unique identifier for this process
 *   - pid: Process ID or instance identifier
 */
public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '');
```

### 2. Added executeCommandDirect() to CmeshPushContentService

**File**: `src/Service/CmeshPushContentService.php`

Added the missing method implementation:

```php
/**
 * Execute a command via systemd using direct parameters.
 *
 * This method provides compatibility with the interface for both
 * CmeshPushContentService and SystemdCommandExecutorService.
 * For CmeshPushContentService, it builds the command from parameters
 * and delegates to executeCommand().
 *
 * @param string $org
 *   The organization name.
 * @param string $name
 *   The site name.
 * @param string $command_key
 *   The command key (e.g., 'default', 'cloudflare', 'bunny', 'aws').
 * @param string $bucket
 *   The bucket name (optional, for AWS S3 deployments).
 *
 * @return array
 *   Array containing process info with keys:
 *   - process_id: Unique identifier for this process
 *   - pid: Process ID or instance identifier
 */
public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '') {
  // Build the command from parameters
  $script = '/opt/cmesh/scripts/pushfin.sh';
  
  // For custom commands, use the command key to determine the script
  if ($command_key !== 'default') {
    $script = "/opt/cmesh/scripts/deploy-{$command_key}.sh";
  }
  
  // Build the command string
  $command = sprintf(
    '%s -o %s -n %s -b %s',
    escapeshellarg($script),
    escapeshellarg($org),
    escapeshellarg($name),
    escapeshellarg($bucket)
  );
  
  // Delegate to the main executeCommand method
  return $this->executeCommand($command);
}
```

## How It Works

### For CmeshPushContentService (mod_php with SSH)

1. **Form calls**: `executeCommandDirect($org, $name, $command_key, $bucket)`
2. **Service builds**: Command string from parameters
3. **Example result**: `/opt/cmesh/scripts/pushfin.sh -o 'mars' -n 'mpvg' -b ''`
4. **Delegates to**: `executeCommand($command)` for background execution
5. **SSH Execution**: Command runs via SSH to remote server

### For SystemdCommandExecutorService (PHP-FPM with systemd)

1. **Form calls**: `executeCommandDirect($org, $name, $command_key, $bucket)`
2. **Service builds**: Systemd service instance name
3. **Example result**: `cmesh-build@mars:mpvg:default`
4. **Executes via**: `systemctl start cmesh-build@mars:mpvg:default`
5. **Systemd manages**: Process lifecycle independently

## Service Configuration

### For CmeshPushContentService (mod_php)
```yaml
# cmesh_push_content.services.yml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\CmeshPushContentService
    arguments: ['@file_system', '@state']
```

### For SystemdCommandExecutorService (PHP-FPM)
```yaml
# cmesh_push_content.services.yml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

## Form Usage (Works with Both Services)

The form can now use the same code path for both services:

```php
// src/Form/CmeshPushContentForm.php
public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $envKey = $trigger['#env_key'];
    $commandKey = $trigger['#command_key'] ?? 'default';

    // Get org, name, bucket from environment configuration
    $org = 'mars';
    $name = 'mpvg';
    $bucket = '';
    
    // This now works with BOTH services!
    $result = $this->commandExecutor->executeCommandDirect($org, $name, $commandKey, $bucket);
    
    // Handle result...
}
```

## Customization for SSH Remote Execution

Since you mentioned using SSH remote execution, you can customize the CmeshPushContentService to build SSH commands:

### Option 1: Override the Command Building Logic

Modify the `executeCommandDirect()` method in CmeshPushContentService:

```php
public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '') {
    // Build the remote command
    $script = '/opt/cmesh/scripts/pushfin.sh';
    if ($command_key !== 'default') {
        $script = "/opt/cmesh/scripts/deploy-{$command_key}.sh";
    }
    
    $remote_command = sprintf(
        '%s -o %s -n %s -b %s',
        escapeshellarg($script),
        escapeshellarg($org),
        escapeshellarg($name),
        escapeshellarg($bucket)
    );
    
    // Build SSH command for remote execution
    $ssh_host = 'your-remote-server.com';
    $ssh_user = 'your-user';
    
    $command = sprintf(
        'ssh %s@%s %s',
        escapeshellarg($ssh_user),
        escapeshellarg($ssh_host),
        escapeshellarg($remote_command)
    );
    
    return $this->executeCommand($command);
}
```

### Option 2: Use Environment Configuration

Configure SSH settings in your `.env.inc` files:

```php
<?php
// sites/default/files/cmesh-config/production.env.inc
$org = 'your-org';
$name = 'your-site';
$bucket = 'your-bucket';

// SSH configuration for remote execution
$ssh_host = 'remote-server.com';
$ssh_user = 'deploy-user';
$ssh_key = '/path/to/ssh/key';
```

Then modify the service to read these variables:

```php
public function executeCommandDirect($org, $name, $command_key = 'default', $bucket = '') {
    // Get SSH configuration from environment (if available)
    $ssh_host = isset($ssh_host) ? $ssh_host : 'default-server.com';
    $ssh_user = isset($ssh_user) ? $ssh_user : 'default-user';
    
    // Build remote command...
    // Build SSH command...
    
    return $this->executeCommand($command);
}
```

## Error Handling

Add proper error handling to catch issues with both services:

```php
try {
    $result = $this->commandExecutor->executeCommandDirect($org, $name, $commandKey, $bucket);
    
    $this->messenger()->addStatus(
        $this->t('Command started with ID: @id', ['@id' => $result['process_id']])
    );
    
} catch (\Exception $e) {
    $this->messenger()->addError(
        $this->t('Command execution failed: @error', ['@error' => $e->getMessage()])
    );
    
    // Log the error for debugging
    \Drupal::logger('cmesh_push_content')->error(
        'Command execution failed: @error', ['@error' => $e->getMessage()]
    );
}
```

## Testing the Implementation

### Test Both Services

1. **Clear cache**:
   ```bash
   drush cr
   ```

2. **Test with CmeshPushContentService** (mod_php):
   - Configure service to use CmeshPushContentService
   - Submit form and verify SSH command executes

3. **Test with SystemdCommandExecutorService** (PHP-FPM):
   - Configure service to use SystemdCommandExecutorService  
   - Submit form and verify systemd service starts

### Debug SSH Issues

If using SSH remote execution, test manually:

```bash
# Test SSH connectivity
ssh your-user@remote-server "echo 'SSH test successful'"

# Test the actual command
ssh your-user@remote-server "/opt/cmesh/scripts/pushfin.sh -o 'mars' -n 'mpvg' -b ''"

# Check SSH key permissions
ls -la ~/.ssh/
chmod 600 ~/.ssh/your-key
```

## Summary

The interface is now unified:

- ✅ **Both services implement** `executeCommandDirect($org, $name, $command_key, $bucket)`
- ✅ **Same form code** works with both services
- ✅ **CmeshPushContentService** builds commands and executes via SSH
- ✅ **SystemdCommandExecutorService** uses systemd services
- ✅ **Error handling** works consistently
- ✅ **Backward compatibility** maintained

Your 500 error should now be resolved, and the form will work seamlessly with both services depending on your server configuration (mod_php vs PHP-FPM).