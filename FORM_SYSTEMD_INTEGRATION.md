# Form Integration with SystemdCommandExecutor

## How It Works

The form and SystemdCommandExecutor work together seamlessly:

### 1. Form Builds Command String

**File:** `src/Form/CmeshPushContentForm.php`

```php
// In your .env.inc file
$org = 'ramsalt';
$name = 'playground';
$script = '/opt/cmesh/scripts/pushfin.sh';  // Ignored by systemd

// Form builds command
$command = sprintf(
  '%s -o %s -n %s',
  escapeshellarg($script),
  escapeshellarg($org),
  escapeshellarg($name)
);
// Result: '/opt/cmesh/scripts/pushfin.sh' -o 'ramsalt' -n 'playground'

// Calls service
$this->commandExecutor->executeCommand($command);
```

### 2. SystemdCommandExecutor Parses Command

**File:** `src/Service/SystemdCommandExecutorService.php`

```php
public function executeCommand($command) {
  // Receives: '/opt/cmesh/scripts/pushfin.sh' -o 'ramsalt' -n 'playground'
  
  // Regex extracts org and name (ignores script path)
  preg_match('/-o\s+[\'"]?([^\s\'"]+)[\'"]?\s+-n\s+[\'"]?([^\s\'"]+)[\'"]?/', 
             $command, $matches);
  
  $org = $matches[1];   // 'ramsalt'
  $name = $matches[2];  // 'playground'
  
  // Build systemd instance
  $instance = "{$org}:{$name}";  // 'ramsalt:playground'
  
  // Start service
  exec("systemctl start cmesh-build@{$instance}");
}
```

### 3. Systemd Service Runs

**Service:** `cmesh-build@ramsalt:playground`

```
systemctl start cmesh-build@ramsalt:playground
↓
Loads: /etc/systemd/system/cmesh-build@.service
↓
Substitutes %i with: ramsalt:playground
↓
Runs: /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground
```

### 4. Wrapper Parses and Executes

**File:** `/opt/cmesh/scripts/pushfin-systemd.sh`

```bash
INSTANCE="ramsalt:playground"

# Parse with colon
if [[ "$INSTANCE" =~ ^([^:]+):(.+)$ ]]; then
    ORG="ramsalt"
    NAME="playground"
fi

# Execute your script
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
```

## Why It Works This Way

1. **Form compatibility:** Existing env configs work without changes
2. **Script path ignored:** SystemdCommandExecutor doesn't use the script path, only extracts org/name
3. **Flexible parsing:** Handles various command formats from escapeshellarg()
4. **Colon delimiter:** Systemd uses colon to support names with dashes

## Supported Command Formats

The regex handles all these formats:

```php
// Format 1: With escapeshellarg (what the form produces)
'/opt/cmesh/scripts/pushfin.sh' -o 'ramsalt' -n 'playground'

// Format 2: Without quotes
/opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground

// Format 3: Double quotes
"/opt/cmesh/scripts/pushfin.sh" -o "ramsalt" -n "playground"

// Format 4: Mixed quotes
/opt/cmesh/scripts/pushfin.sh -o 'ramsalt' -n playground

// All extract: org=ramsalt, name=playground
```

## Complete Flow Diagram

```
┌─────────────────────────────────────────────┐
│ 1. User clicks "Push to dev" button        │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ 2. Form: executeEnvCommand()                │
│    - Loads dev.env.inc                      │
│    - Gets: $org='ramsalt', $name='playground'│
│    - Builds: '/script' -o 'ramsalt' -n 'playground'│
│    - Calls: commandExecutor->executeCommand()│
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ 3. SystemdCommandExecutorService            │
│    - Receives command string                │
│    - Regex parses: org='ramsalt', name='playground'│
│    - Builds instance: 'ramsalt:playground'  │
│    - Runs: systemctl start cmesh-build@...  │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ 4. Systemd                                  │
│    - Loads service template                 │
│    - Substitutes %i → ramsalt:playground    │
│    - Starts independent process             │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ 5. pushfin-systemd.sh wrapper               │
│    - Receives: 'ramsalt:playground'         │
│    - Parses colon: org='ramsalt', name='playground'│
│    - Calls: pushfin.sh -o ramsalt -n playground│
└──────────────────┬──────────────────────────┘
                   │
                   ▼
┌─────────────────────────────────────────────┐
│ 6. Your pushfin.sh build script             │
│    - Receives: -o ramsalt -n playground     │
│    - Runs your build commands               │
│    - Output → /var/log/cmesh/build-...log  │
└─────────────────────────────────────────────┘
```

## Debugging

### Check What Command Is Being Built

Add logging to form:

```php
public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
  // ... build command ...
  
  \Drupal::logger('cmesh_push_content')->info('Form command: @cmd', ['@cmd' => $command]);
  
  $this->executeCommand($command, "Push to $envKey");
}
```

### Check What Systemd Receives

The SystemdCommandExecutor already logs:

```php
\Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Received command: @cmd', ['@cmd' => $command]);
\Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Parsed org=@org, name=@name', ...);
\Drupal::logger('cmesh_push_content')->info('SystemdCommandExecutor: Starting service: @service', ...);
```

View logs:
```bash
drush watchdog:show --type=cmesh_push_content
```

### Check Systemd Execution

```bash
# View systemd logs
sudo journalctl -u cmesh-build@ramsalt:playground -n 50

# Check service status
sudo systemctl status cmesh-build@ramsalt:playground

# View output log
sudo cat /var/log/cmesh/build-ramsalt:playground.log
```

## Testing the Integration

### 1. Test Form Command Building

```bash
drush php:eval "
\$org = 'ramsalt';
\$name = 'playground';
\$script = '/opt/cmesh/scripts/pushfin.sh';
\$command = sprintf('%s -o %s -n %s',
  escapeshellarg(\$script),
  escapeshellarg(\$org),
  escapeshellarg(\$name)
);
echo \"Command: \$command\n\";
"
```

Expected output:
```
Command: '/opt/cmesh/scripts/pushfin.sh' -o 'ramsalt' -n 'playground'
```

### 2. Test Regex Parsing

```bash
drush php:eval "
\$command = \"'/opt/cmesh/scripts/pushfin.sh' -o 'ramsalt' -n 'playground'\";
if (preg_match('/-o\s+[\\'\\\"]?([^\\s\\'\\\"]+)[\\'\\\"]?\s+-n\s+[\\'\\\"]?([^\\s\\'\\\"]+)[\\'\\\"]?/', \$command, \$matches)) {
  echo \"Org: {\$matches[1]}\n\";
  echo \"Name: {\$matches[2]}\n\";
} else {
  echo \"No match\n\";
}
"
```

Expected output:
```
Org: ramsalt
Name: playground
```

### 3. Test Service Execution

```bash
# Manually call the service method
drush php:eval "
\$service = \Drupal::service('cmesh_push_content.service');
\$command = '/opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground';
try {
  \$result = \$service->executeCommand(\$command);
  print_r(\$result);
} catch (\Exception \$e) {
  echo 'Error: ' . \$e->getMessage();
}
"
```

### 4. Test Full Flow

1. Navigate to `/admin/config/system/cmesh-push-content`
2. Click "Push to dev" (or your environment)
3. Check Drupal logs: `drush watchdog:show --type=cmesh_push_content`
4. Check systemd: `sudo systemctl status cmesh-build@...`
5. Check output: `sudo tail -f /var/log/cmesh/build-*.log`

## Common Issues

### Issue: "Could not parse command"

**Cause:** Regex didn't match the command format

**Fix:** Check the command string in logs:
```bash
drush watchdog:show --type=cmesh_push_content | grep "Received command"
```

Verify it has `-o` and `-n` flags.

### Issue: "Failed to start systemd service"

**Cause:** systemctl command failed

**Fix:** 
1. Check service file exists: `systemctl cat cmesh-build@.service`
2. Test manually: `sudo systemctl start cmesh-build@test:test`
3. Check permissions: Can www-data/http run systemctl?

### Issue: Service starts but nothing happens

**Cause:** Wrapper or pushfin.sh has issues

**Fix:**
1. Check systemd logs: `sudo journalctl -u cmesh-build@... -n 50`
2. Test wrapper: `sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground`
3. Test pushfin.sh: `sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground`

## Summary

The integration works in 3 steps:

1. **Form** builds command string with `-o ORG -n NAME`
2. **SystemdCommandExecutor** extracts org/name, starts systemd service
3. **Systemd** runs wrapper which calls your script

No changes needed to your env configs - it just works!
