# Switching Module to Use Systemd Service

## The Problem

The Drupal module is currently using `CmeshPushContentService` which:
- Uses `exec()` to run commands directly
- Fails with PHP-FPM due to process isolation
- Can't spawn background processes properly

## The Solution

Switch to `SystemdCommandExecutorService` which:
- Uses `systemctl start` to trigger systemd services
- Works perfectly with PHP-FPM
- Completely decouples from PHP process

## How to Switch

### Step 1: Update services.yml

**Edit:** `cmesh_push_content.services.yml`

**Change from:**
```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\CmeshPushContentService
    arguments: ['@file_system', '@state']
```

**To:**
```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

### Step 2: Clear Cache

```bash
drush cr
```

### Step 3: Test

Navigate to the form and click a button. It should now work!

## What Changes

### Old Flow (Direct exec - Fails with PHP-FPM):
```
User clicks "Push to dev"
↓
Form calls: CmeshPushContentService::executeCommand()
↓
Service runs: exec('bash /tmp/script.sh &')
↓
PHP-FPM request ends
↓
PHP-FPM kills background process ❌
↓
Build fails
```

### New Flow (Systemd - Works with PHP-FPM):
```
User clicks "Push to dev"
↓
Form calls: SystemdCommandExecutorService::executeCommand()
↓
Service runs: systemctl start cmesh-build@ramsalt:playground
↓
Systemd starts independent service
↓
PHP-FPM request ends
↓
Service continues running ✅
↓
Build completes successfully
```

## How It Works

### 1. Form Execution

The form still works the same way:

```php
// In your .env.inc file
$org = 'ramsalt';
$name = 'playground';

// Form builds command
$command = sprintf(
  '%s -o %s -n %s',
  escapeshellarg($script),
  escapeshellarg($org),
  escapeshellarg($name)
);

// Calls service
$this->commandExecutor->executeCommand($command);
```

### 2. Systemd Service Parsing

The `SystemdCommandExecutorService::executeCommand()`:

```php
// Parses command to extract org and name
preg_match('/-o\s+\'?([^\'\\s]+)\'?\s+-n\s+\'?([^\'\\s]+)\'?/', $command, $matches);
$org = $matches[1];   // 'ramsalt'
$name = $matches[2];  // 'playground'

// Builds instance name
$instance = "{$org}:{$name}";  // 'ramsalt:playground'

// Starts systemd service
exec("systemctl start cmesh-build@{$instance}");
```

### 3. Systemd Service Execution

```
systemctl start cmesh-build@ramsalt:playground
↓
Systemd loads: /etc/systemd/system/cmesh-build@.service
↓
Replaces %i with: ramsalt:playground
↓
Runs: /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground
↓
Wrapper parses: ORG=ramsalt, NAME=playground
↓
Wrapper calls: /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground
↓
Your build runs!
```

## Instance Name Format

The systemd service uses colon (`:`) as delimiter to support names with dashes:

**Examples:**
- `mars:mpvg` → org=mars, name=mpvg
- `ramsalt:playground` → org=ramsalt, name=playground
- `acme:my-project` → org=acme, name=my-project

## Status Monitoring

The systemd service checks status via:

```php
// Check if service is active
exec("systemctl is-active cmesh-build@{$instance}", $output);
$is_running = (trim($output[0]) === 'active');

// Read log file
$log_file = "/var/log/cmesh/build-{$instance}.log";
$output = file_get_contents($log_file);
```

## Your .env.inc Files

You can still customize the script path, but it won't be used directly:

```php
<?php
$org = 'ramsalt';
$name = 'playground';

// This $script is now ignored by systemd service
// The systemd service always uses pushfin-systemd.sh
$script = '/opt/cmesh/scripts/pushfin.sh';
```

The systemd service ignores `$script` because it always calls the wrapper.

## If You Need Different Scripts Per Environment

Update the systemd service or create multiple service templates:

```bash
# Create environment-specific services
/etc/systemd/system/cmesh-build-prod@.service
/etc/systemd/system/cmesh-build-dev@.service
```

Or modify `pushfin-systemd.sh` to check environment and call different scripts.

## Verification

After switching, verify it's working:

```bash
# 1. Check service is using systemd class
drush php:eval "echo get_class(\Drupal::service('cmesh_push_content.service'));"
# Should output: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService

# 2. Click button in UI

# 3. Check systemd service started
sudo systemctl list-units 'cmesh-build@*'

# 4. View logs
sudo journalctl -u cmesh-build@ramsalt:playground -f

# 5. Check log file
sudo tail -f /var/log/cmesh/build-ramsalt:playground.log
```

## Rollback (If Needed)

To switch back to direct exec:

```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\CmeshPushContentService
    arguments: ['@file_system', '@state']
```

```bash
drush cr
```

## Advantages of Systemd Service

✅ Works with PHP-FPM
✅ Works with any PHP configuration
✅ Process completely independent
✅ Built-in logging
✅ Resource limits (CPU, memory)
✅ Can monitor/restart services
✅ Standard system integration
✅ No PHP memory/time limits apply

## Disadvantages

❌ Requires systemd setup
❌ Requires root access for initial setup
❌ Linux only
❌ Slightly more complex debugging

## Complete Setup Checklist

- [ ] Systemd service file installed at `/etc/systemd/system/cmesh-build@.service`
- [ ] Wrapper script at `/opt/cmesh/scripts/pushfin-systemd.sh` (executable)
- [ ] Your script at `/opt/cmesh/scripts/pushfin.sh` (executable)
- [ ] Log directory `/var/log/cmesh` (writable by http user)
- [ ] Service file reloaded: `sudo systemctl daemon-reload`
- [ ] Module services.yml updated to use SystemdCommandExecutorService
- [ ] Cache cleared: `drush cr`
- [ ] Tested from UI
- [ ] Can view logs: `sudo journalctl -u cmesh-build@...`

## Summary

**Change this one line in `cmesh_push_content.services.yml`:**

```yaml
class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
```

**Then:**
```bash
drush cr
```

That's it! The module will now use systemd instead of direct exec.
