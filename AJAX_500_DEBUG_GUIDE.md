# AJAX 500 Error Debug Guide - CmeshPushContentService

## Problem: Form Submit Fails with 500 Error (No Server Logs)

This is a common issue when using CmeshPushContentService with SSH remote execution on mod_php systems. The 500 error occurs during AJAX form submission, but no logs appear because the error happens before logging can occur.

## Root Cause Analysis

Based on the code analysis, here are the most likely causes:

### 1. **Missing or Invalid Environment Configuration File**

**The Issue**: The form tries to include `{$envKey}.env.inc` but the file is missing or has syntax errors.

**Location**: `src/Form/CmeshPushContentForm.php` lines 315-319
```php
if (is_file($inc)) {
    ob_start();
    include $inc;  // ← This can cause 500 error if file has syntax errors
    ob_end_clean();
}
```

**Debug Steps**:
```bash
# Check if environment files exist
ls -la /path/to/drupal/sites/default/files/cmesh-config/
# Should show files like: dev.env.inc, staging.env.inc, prod.env.inc

# Test PHP syntax of environment files
php -l /path/to/drupal/sites/default/files/cmesh-config/dev.env.inc

# Check file permissions
ls -la /path/to/drupal/sites/default/files/cmesh-config/
```

### 2. **Missing executeCommandDirect() Method**

**The Issue**: The form calls `executeCommandDirect()` but CmeshPushContentService only has `executeCommand()`.

**Location**: `src/Form/CmeshPushContentForm.php` line 322
```php
$result = $this->commandExecutor->executeCommandDirect($org, $name, $commandKey, $bucket);
```

**The Problem**: CmeshPushContentService doesn't implement `executeCommandDirect()` - only SystemdCommandExecutorService does!

**Solution**: Update the form to use the correct method for CmeshPushContentService:

```php
// Instead of:
$result = $this->commandExecutor->executeCommandDirect($org, $name, $commandKey, $bucket);

// Should be:
$command = $this->buildCommand($org, $name, $commandKey, $bucket);
$result = $this->commandExecutor->executeCommand($command);
```

### 3. **Temp Directory Permission Issues**

**The Issue**: CmeshPushContentService tries to create files in `/tmp` but fails silently.

**Location**: `src/Service/CmeshPushContentService.php` lines 59-62
```php
$temp_dir = $this->fileSystem->getTempDirectory();
$output_file = $temp_dir . '/' . $process_id . '_output.log';
$pid_file = $temp_dir . '/' . $process_id . '_pid.txt';
$script_file = $temp_dir . '/' . $process_id . '_script.sh';
```

**Debug Steps**:
```bash
# Check temp directory
ls -ld /tmp
echo "Temp directory: $(php -r 'echo sys_get_temp_dir();')"

# Test file creation as web user
sudo -u www-data touch /tmp/test_$(date +%s).txt
```

### 4. **SSH Command Execution Failures**

**The Issue**: Your SSH commands might be failing during the command building phase.

**Debug the actual command being executed**:

Add debugging to `CmeshPushContentService::executeCommand()`:

```php
public function executeCommand($command) {
    // Add this debugging
    error_log("CmeshPushContentService: Executing command: " . $command);
    
    // Your SSH command might look like:
    // ssh user@remote-server 'bash -s' < /tmp/script.sh
    
    // Rest of the method...
}
```

## Quick Fix Solutions

### Fix #1: Update the Form Submission Handler

**File**: `src/Form/CmeshPushContentForm.php`
**Method**: `executeEnvCommand()` around line 322

Replace this:
```php
$result = $this->commandExecutor->executeCommandDirect($org, $name, $commandKey, $bucket);
```

With this:
```php
// Build the command for SSH remote execution
$command = $this->buildSshCommand($org, $name, $commandKey, $bucket);
$result = $this->commandExecutor->executeCommand($command);
```

**Add this helper method**:
```php
/**
 * Build SSH command for remote execution
 */
private function buildSshCommand($org, $name, $commandKey, $bucket) {
    // Your SSH remote execution command pattern
    // Example: ssh user@remote-server '/path/to/script.sh -o org -n name -b bucket'
    $script = '/opt/cmesh/scripts/pushfin.sh';
    
    $remote_command = sprintf(
        '%s -o %s -n %s -b %s',
        escapeshellarg($script),
        escapeshellarg($org),
        escapeshellarg($name),
        escapeshellarg($bucket)
    );
    
    // Build your SSH command
    $ssh_host = 'your-remote-server.com';  // Configure this
    $ssh_user = 'your-user';               // Configure this
    
    return sprintf(
        'ssh %s@%s %s',
        escapeshellarg($ssh_user),
        escapeshellarg($ssh_host),
        escapeshellarg($remote_command)
    );
}
```

### Fix #2: Add Error Handling to the Form

**File**: `src/Form/CmeshPushContentForm.php`
**Method**: `executeEnvCommand()`

Add try-catch around the command execution:

```php
public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
    try {
        $trigger = $form_state->getTriggeringElement();
        $envKey = $trigger['#env_key'];
        $commandKey = $trigger['#command_key'] ?? 'default';

        // ... existing code ...
        
        // Build and execute command
        $command = $this->buildSshCommand($org, $name, $commandKey, $bucket);
        $result = $this->commandExecutor->executeCommand($command);
        
        $this->messenger()->addStatus(
            $this->t('@description started with PID: @pid', [
                '@description' => $command_config['description'],
                '@pid' => $result['pid'],
            ])
        );
        
    } catch (\Exception $e) {
        $this->messenger()->addError(
            $this->t('Command execution failed: @error', [
                '@error' => $e->getMessage(),
            ])
        );
        // Log the full error for debugging
        error_log('CmeshPushContent AJAX Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
    
    $form_state->setRebuild(TRUE);
}
```

### Fix #3: Add Debugging to CmeshPushContentService

**File**: `src/Service/CmeshPushContentService.php`
**Method**: `executeCommand()`

Add error logging:

```php
public function executeCommand($command) {
    // Add debugging
    error_log("CmeshPushContentService::executeCommand() - Command: " . $command);
    
    try {
        // Increase PHP limits for long-running commands
        @ini_set('memory_limit', '2048M');
        @ini_set('max_execution_time', '900');
        @set_time_limit(900);
        
        $process_id = uniqid('cmd_', TRUE);
        $temp_dir = $this->fileSystem->getTempDirectory();
        
        error_log("CmeshPushContentService - Process ID: $process_id, Temp dir: $temp_dir");
        
        // ... rest of the method ...
        
    } catch (\Exception $e) {
        error_log("CmeshPushContentService::executeCommand() - Exception: " . $e->getMessage());
        throw $e;
    }
}
```

## Immediate Debugging Steps

### Step 1: Check Environment Files
```bash
# Find your Drupal files directory
drush php:eval "echo \Drupal::service('file_system')->realpath('public://');"

# Check for cmesh-config directory
ls -la /path/to/drupal/sites/default/files/cmesh-config/

# Test PHP syntax
drush php:eval "php -l '/path/to/drupal/sites/default/files/cmesh-config/dev.env.inc';"
```

### Step 2: Test Command Execution Manually
```bash
# Test your SSH command manually
ssh your-user@remote-server '/opt/cmesh/scripts/pushfin.sh -o mars -n mpvg -b ""'

# Test as web user
sudo -u www-data ssh your-user@remote-server 'echo "test"'
```

### Step 3: Enable PHP Error Display
Add to your Drupal `sites/default/settings.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
```

### Step 4: Check Web Server Error Logs
```bash
# Apache
sudo tail -f /var/log/apache2/error.log

# Nginx
sudo tail -f /var/log/nginx/error.log

# PHP-FPM (if applicable)
sudo tail -f /var/log/php-fpm/error.log
```

## Common SSH-Specific Issues

### 1. **SSH Key Authentication**
```bash
# Test SSH key
ssh -i ~/.ssh/your-key user@remote-server "echo 'SSH test successful'"

# Check key permissions (should be 600)
ls -la ~/.ssh/your-key
```

### 2. **SSH Host Key Verification**
Add to SSH command:
```bash
ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null user@remote-server 'command'
```

### 3. **SSH Connection Timeouts**
Add timeout options:
```bash
ssh -o ConnectTimeout=30 -o ServerAliveInterval=60 user@remote-server 'command'
```

## Summary

The 500 error is most likely caused by one of these issues:

1. **Missing executeCommandDirect() method** in CmeshPushContentService
2. **Syntax errors in .env.inc files**
3. **Temp directory permission issues**
4. **SSH command building failures**

The key fix is to update the form submission handler to use the correct method for CmeshPushContentService and add proper error handling throughout the execution chain.