# Systemd Service Setup for PHP-FPM

## The Problem

Commands fail when using PHP-FPM due to process isolation. Even simple commands like `date` fail.

## The Solution

Use systemd to run commands completely outside of PHP-FPM.

## Installation Steps

### 1. Copy Service Files

```bash
# Copy systemd service template
sudo cp config/cmesh-build@.service /etc/systemd/system/

# Copy wrapper script
sudo cp config/pushfin-systemd.sh /opt/cmesh/scripts/
sudo chmod +x /opt/cmesh/scripts/pushfin-systemd.sh

# Create log directory
sudo mkdir -p /var/log/cmesh
sudo chown www-data:www-data /var/log/cmesh
sudo chmod 755 /var/log/cmesh
```

### 2. Reload Systemd

```bash
sudo systemctl daemon-reload
```

### 3. Test the Service

```bash
# Start a test build
sudo systemctl start cmesh-build@mars-mpvg

# Check status
sudo systemctl status cmesh-build@mars-mpvg

# View logs
sudo journalctl -u cmesh-build@mars-mpvg -f

# Or view log file
tail -f /var/log/cmesh/build-mars-mpvg-*.log
```

### 4. Update Module to Use Systemd Service

**Edit:** `cmesh_push_content.services.yml`

```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

**Or keep both and switch based on environment:**

```yaml
services:
  # Default service (direct exec)
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\CmeshPushContentService
    arguments: ['@file_system', '@state']
  
  # Systemd service (for PHP-FPM)
  cmesh_push_content.service.systemd:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

Then in `settings.php`:

```php
// Use systemd service on production
if (getenv('DRUPAL_ENV') === 'production') {
  $settings['container_yamls'][] = DRUPAL_ROOT . '/modules/custom/cmesh_push_content/cmesh_push_content.services.systemd.yml';
}
```

### 5. Clear Cache

```bash
drush cr
```

### 6. Test from Web Interface

1. Navigate to `/admin/config/system/cmesh-push-content`
2. Click "Push to dev"
3. Should work now!

## How It Works

### Old Method (Direct Exec - Fails with PHP-FPM)
```
User clicks button
↓
PHP-FPM executes command directly
↓
PHP-FPM request ends
↓
PHP-FPM kills background process ❌
↓
Command fails
```

### New Method (Systemd - Works Always)
```
User clicks button
↓
PHP calls: systemctl start cmesh-build@mars-mpvg
↓
systemd starts service (separate process tree)
↓
PHP-FPM request ends
↓
Service continues running ✅
↓
Command completes successfully
```

## Service Instance Format

The service uses systemd templates with instance names:

```bash
# Format: cmesh-build@{org}-{name}
systemctl start cmesh-build@mars-mpvg
systemctl start cmesh-build@acme-production
systemctl start cmesh-build@test-staging
```

The wrapper script parses `{org}-{name}` and calls:
```bash
/opt/cmesh/scripts/pushfin.sh -o mars -n mpvg
```

## Log Files

Logs are stored in `/var/log/cmesh/` with format:
```
build-{instance}-{timestamp}.log
```

Examples:
- `/var/log/cmesh/build-mars-mpvg-20251024173000.log`
- `/var/log/cmesh/build-acme-prod-20251024173100.log`

## Monitoring

### Check if service is running
```bash
systemctl is-active cmesh-build@mars-mpvg
```

### View live logs
```bash
journalctl -u cmesh-build@mars-mpvg -f
```

### View all build logs
```bash
ls -lh /var/log/cmesh/
```

### Check recent builds
```bash
systemctl list-units 'cmesh-build@*'
```

## Troubleshooting

### Service won't start
```bash
# Check service status
sudo systemctl status cmesh-build@mars-mpvg

# Check journal for errors
sudo journalctl -xe

# Check if wrapper script exists
ls -la /opt/cmesh/scripts/pushfin-systemd.sh

# Check if main script exists  
ls -la /opt/cmesh/scripts/pushfin.sh
```

### Permission denied
```bash
# Check log directory permissions
ls -ld /var/log/cmesh

# Should be:
# drwxr-xr-x www-data www-data

# Fix if needed:
sudo chown www-data:www-data /var/log/cmesh
sudo chmod 755 /var/log/cmesh
```

### www-data can't run systemctl
```bash
# Create polkit rule
sudo nano /etc/polkit-1/rules.d/50-cmesh-build.rules
```

Add:
```javascript
polkit.addRule(function(action, subject) {
    if (action.id == "org.freedesktop.systemd1.manage-units" &&
        action.lookup("unit").indexOf("cmesh-build@") == 0 &&
        subject.user == "www-data") {
        return polkit.Result.YES;
    }
});
```

Reload polkit:
```bash
sudo systemctl restart polkit
```

### Service times out
Edit `/etc/systemd/system/cmesh-build@.service`:
```ini
TimeoutStartSec=1800  # 30 minutes
```

Reload:
```bash
sudo systemctl daemon-reload
```

## Advantages of Systemd Method

✅ Works with PHP-FPM
✅ Works with Apache mod_php
✅ Complete process isolation
✅ Proper logging
✅ Resource limits (CPU, memory)
✅ Auto-restart on failure (if enabled)
✅ Status monitoring
✅ Standard system integration
✅ Works with any PHP configuration
✅ No SELinux issues
✅ No memory limit issues from PHP

## Disadvantages

❌ Requires root setup (one-time)
❌ Requires systemd (Linux only)
❌ Slightly more complex setup
❌ Need polkit rule for www-data

## Alternative: Queue File Method

If you can't use systemd, use the queue file method described in `PHP_FPM_ISSUES.md`.

## Production Checklist

- [ ] Service file installed
- [ ] Wrapper script executable
- [ ] Log directory created with correct permissions
- [ ] systemd reloaded
- [ ] Test service works manually
- [ ] Module configured to use SystemdCommandExecutorService
- [ ] polkit rule configured (if needed)
- [ ] Cache cleared
- [ ] Tested from web interface
- [ ] Monitoring set up
- [ ] Log rotation configured

## Log Rotation

Create `/etc/logrotate.d/cmesh-builds`:

```
/var/log/cmesh/build-*.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

## Monitoring Script

Create `/opt/cmesh/scripts/monitor-builds.sh`:

```bash
#!/bin/bash

echo "=== Active Builds ==="
systemctl list-units 'cmesh-build@*' --state=active

echo ""
echo "=== Recent Logs ==="
ls -lht /var/log/cmesh/ | head -10

echo ""
echo "=== Failed Services ==="
systemctl list-units 'cmesh-build@*' --state=failed
```

Run with:
```bash
/opt/cmesh/scripts/monitor-builds.sh
```
