# Quick Fix for Memory Issues

## The Problem
```
ELIFECYCLE  Command failed with exit code -2.
[Command completed with exit code: 254]
```

Exit code -2 = Process killed (out of memory)
Exit code 254 = Resource limit reached

## Quick Fixes (In Order)

### 1. Increase PHP Memory (EASIEST)

**Edit:** `/etc/php/8.2/fpm/php.ini` (or your PHP version)

```ini
memory_limit = 2048M
max_execution_time = 900
```

**Restart:**
```bash
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx  # or apache2
```

### 2. Use High-Memory Wrapper Script

**Create:** `/opt/cmesh/scripts/pushfin-high-memory.sh`

```bash
#!/bin/bash
export NODE_OPTIONS='--max-old-space-size=8192'
exec /opt/cmesh/scripts/pushfin.sh "$@"
```

**Make executable:**
```bash
chmod +x /opt/cmesh/scripts/pushfin-high-memory.sh
```

**Update your env file:** `config/dev.env.inc`

```php
<?php
$org = 'mars';
$name = 'mpvg';
$script = '/opt/cmesh/scripts/pushfin-high-memory.sh';
```

### 3. Add Swap Space (If Low RAM)

```bash
# Create 8GB swap
sudo fallocate -l 8G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Make permanent
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### 4. Test Command Manually

```bash
# Switch to web server user
sudo -u www-data bash

# Try the command
cd /opt/cmesh/previews/ramsalt-playground/fe
NODE_OPTIONS='--max-old-space-size=8192' npm run build

# If it works manually but not from web, it's a PHP limit issue
```

## What Changed in Module

The module now:
1. ✅ Sets PHP memory to 2048M before executing
2. ✅ Sets execution time to 900 seconds (15 min)
3. ✅ Allows custom script path in env files
4. ✅ Includes example high-memory wrapper

## Verify Settings

```bash
# Check PHP memory limit
php -i | grep memory_limit

# Check as web server user
sudo -u www-data php -r "echo ini_get('memory_limit');"

# Should show: 2048M or higher
```

## Still Not Working?

### Check System Memory
```bash
free -h
# Need at least 4GB free for Next.js builds
```

### Check Disk Space
```bash
df -h /tmp
df -h /opt/cmesh
# Need at least 2GB free
```

### Check OOM Killer
```bash
dmesg | grep -i "killed process"
# If you see entries, system ran out of memory
```

### Increase Node Memory More
Edit wrapper script:
```bash
export NODE_OPTIONS='--max-old-space-size=16384'  # 16GB
```

## Recommended Configuration

For Next.js builds, use:

**PHP (php.ini):**
```ini
memory_limit = 2048M
max_execution_time = 900
```

**Node (wrapper script):**
```bash
export NODE_OPTIONS='--max-old-space-size=8192'
```

**System:**
- 8GB+ RAM total
- 4GB+ swap space
- 2GB+ free disk in /tmp

## After Applying Fixes

1. **Restart PHP:** `sudo systemctl restart php8.2-fpm`
2. **Clear cache:** `drush cr`
3. **Hard refresh browser:** Ctrl+Shift+R
4. **Try again**

## File Locations

- PHP config: `/etc/php/8.2/fpm/php.ini`
- Wrapper script: `/opt/cmesh/scripts/pushfin-high-memory.sh`
- Env config: `modules/custom/cmesh_push_content/config/dev.env.inc`
- Module service: `src/Service/CmeshPushContentService.php` (now sets limits)

## Get Help

If still failing, provide:
```bash
php -i | grep -E "(memory|execution|version)"
free -h
df -h
ulimit -a  # as www-data user
```
