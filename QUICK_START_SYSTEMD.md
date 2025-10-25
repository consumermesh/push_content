# Quick Start: Systemd Setup for PHP-FPM

## The Issue

Commands fail with PHP-FPM due to process isolation. The solution is to use systemd.

## Quick Setup (5 Steps)

### 1. Install Systemd Service

```bash
# Copy service file (adjust path to your module location)
sudo cp modules/custom/cmesh_push_content/config/cmesh-build@.service /etc/systemd/system/

# Copy wrapper script
sudo cp modules/custom/cmesh_push_content/config/pushfin-systemd.sh /opt/cmesh/scripts/
sudo chmod +x /opt/cmesh/scripts/pushfin-systemd.sh

# Create log directory
sudo mkdir -p /var/log/cmesh
sudo chown http:http /var/log/cmesh

# Reload systemd
sudo systemctl daemon-reload
```

### 2. Verify Module is Using Systemd Service

Check `cmesh_push_content.services.yml`:

```yaml
services:
  cmesh_push_content.service:
    class: Drupal\cmesh_push_content\Service\SystemdCommandExecutorService
    arguments: ['@file_system', '@state']
```

If it shows `CmeshPushContentService`, change it to `SystemdCommandExecutorService`.

### 3. Clear Cache

```bash
drush cr
```

### 4. Test Systemd Service Manually

```bash
# Test the wrapper script
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground

# Test systemd service
sudo systemctl start cmesh-build@ramsalt:playground
sudo systemctl status cmesh-build@ramsalt:playground
sudo journalctl -u cmesh-build@ramsalt:playground -f
```

### 5. Test from Drupal UI

1. Navigate to `/admin/config/system/cmesh-push-content`
2. Click "Push to dev" (or your environment)
3. Should work now!

## Instance Name Format

Use **colon** as delimiter (not dash) to support names with dashes:

**Correct:**
- `mars:mpvg`
- `ramsalt:playground`
- `acme:my-site-name`

**Incorrect:**
- `mars-mpvg` (works but limited)
- `ramsalt-playground` (gets parsed wrong if name has dash)

## Troubleshooting

### Service won't start
```bash
# Check service file
systemctl cat cmesh-build@.service | grep ExecStart
# Should show: /opt/cmesh/scripts/pushfin-systemd.sh %i

# Check wrapper exists
ls -la /opt/cmesh/scripts/pushfin-systemd.sh

# Check logs
sudo journalctl -u cmesh-build@test -xe
```

### "Error 2" (No such file or directory)
```bash
# Fix shebang
sudo sed -i '1s|.*|#!/usr/bin/env bash|' /opt/cmesh/scripts/pushfin-systemd.sh
```

### "Error 209" (SETSID)
```bash
# Remove WorkingDirectory from service file
sudo nano /etc/systemd/system/cmesh-build@.service
# Comment out: # WorkingDirectory=/opt/cmesh
sudo systemctl daemon-reload
```

### "Error 64" (Usage error)
```bash
# Make sure pushfin.sh exists
ls -la /opt/cmesh/scripts/pushfin.sh

# Test wrapper
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground
```

### Commands still fail from web
```bash
# Verify module is using systemd service
drush php:eval "echo get_class(\Drupal::service('cmesh_push_content.service'));"
# Should output: ...SystemdCommandExecutorService

# If not, update services.yml and clear cache
```

## Files Checklist

```bash
# Module service definition
cat modules/custom/cmesh_push_content/cmesh_push_content.services.yml
# Should use: SystemdCommandExecutorService

# Systemd service file
cat /etc/systemd/system/cmesh-build@.service
# Should exist and call pushfin-systemd.sh

# Wrapper script
ls -la /opt/cmesh/scripts/pushfin-systemd.sh
# Should be executable (rwxr-xr-x)

# Your build script
ls -la /opt/cmesh/scripts/pushfin.sh
# Should exist and be executable

# Log directory
ls -ld /var/log/cmesh
# Should be writable by http user
```

## Viewing Logs

```bash
# Real-time logs
sudo journalctl -u cmesh-build@ramsalt:playground -f

# Or log file
sudo tail -f /var/log/cmesh/build-ramsalt:playground.log

# Recent logs
sudo journalctl -u cmesh-build@ramsalt:playground -n 50

# All active builds
sudo systemctl list-units 'cmesh-build@*' --state=active
```

## How It Works

```
User clicks button in Drupal
↓
PHP calls: systemctl start cmesh-build@ramsalt:playground
↓
Systemd starts service (independent of PHP)
↓
Service calls: pushfin-systemd.sh ramsalt:playground
↓
Wrapper parses: org=ramsalt, name=playground
↓
Wrapper calls: pushfin.sh -o ramsalt -n playground
↓
Your build runs!
```

## Key Points

1. ✅ Module must use `SystemdCommandExecutorService` 
2. ✅ Systemd service file must be installed
3. ✅ Wrapper script must be executable
4. ✅ Your `pushfin.sh` must exist
5. ✅ Use colon delimiter: `org:name`

## Need More Help?

See detailed documentation:
- `SWITCH_TO_SYSTEMD.md` - Complete setup guide
- `SYSTEMD_SETUP.md` - Installation details
- `FIX_SERVICE_FILE.md` - Service file issues
- `SYSTEMD_ERROR_*.md` - Error-specific guides

## Quick Test

Run all these - they should all pass:

```bash
# 1. Service file exists
test -f /etc/systemd/system/cmesh-build@.service && echo "✓ Service file OK"

# 2. Wrapper exists and executable
test -x /opt/cmesh/scripts/pushfin-systemd.sh && echo "✓ Wrapper OK"

# 3. Build script exists
test -f /opt/cmesh/scripts/pushfin.sh && echo "✓ Build script OK"

# 4. Log directory writable
sudo -u http test -w /var/log/cmesh && echo "✓ Log dir OK"

# 5. Module using systemd
drush php:eval "echo strpos(get_class(\Drupal::service('cmesh_push_content.service')), 'Systemd') !== false ? '✓ Module OK' : '✗ Wrong service';"

# 6. Test service
sudo systemctl start cmesh-build@test:test 2>&1 && echo "✓ Systemd OK"
```

If all pass, you're good to go!
