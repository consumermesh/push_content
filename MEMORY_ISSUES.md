# Memory and Resource Limit Issues

## The Error

```
ELIFECYCLE  Command failed with exit code -2.
[Command completed with exit code: 254]
```

Exit code `-2` typically means the process was killed (often by OOM killer).
Exit code `254` usually indicates a resource limit or permission issue.

## Why It Works in Terminal But Not From Web

When you run from terminal vs. web server, different limits apply:

| Resource | Terminal (SSH) | Web Server (PHP) |
|----------|---------------|------------------|
| User | Your user account | www-data/apache |
| Memory | Your ulimit | PHP memory_limit |
| Time | Unlimited | PHP max_execution_time |
| Processes | Your ulimit | System limits |
| Environment | Your shell env | Limited PHP env |

## Common Issues

### 1. PHP Memory Limit

**Check current limit:**
```bash
php -i | grep memory_limit
# Or
drush php:eval "echo ini_get('memory_limit');"
```

**Typical values:**
- Default: 128M (too low!)
- Recommended: 512M or higher
- For builds: 1024M (1G) or more

**Fix in php.ini:**
```ini
memory_limit = 1024M
```

**Or in settings.php:**
```php
ini_set('memory_limit', '1024M');
```

### 2. PHP Execution Time

**Check:**
```bash
php -i | grep max_execution_time
```

**Fix in php.ini:**
```ini
max_execution_time = 600  ; 10 minutes
```

**Or in settings.php:**
```php
ini_set('max_execution_time', 600);
set_time_limit(600);
```

### 3. System Memory (OOM Killer)

**Check available memory:**
```bash
free -h
```

**Check if OOM killer is active:**
```bash
dmesg | grep -i "killed process"
# Or
grep -i "out of memory" /var/log/syslog
```

**If OOM killer is active:**
- System is genuinely out of memory
- Next.js build needs 4GB+ RAM
- Server may not have enough RAM

### 4. ulimit Restrictions

**Check limits for web server user:**
```bash
# As root
sudo -u www-data bash -c 'ulimit -a'
```

**Check for memory limits:**
```bash
ulimit -v  # Virtual memory
ulimit -m  # Physical memory
ulimit -d  # Data segment
```

**If limited, increase in systemd service file:**
```ini
[Service]
LimitAS=infinity
LimitDATA=infinity
LimitMEMLOCK=infinity
```

### 5. Node.js Memory Not Respected

The `NODE_OPTIONS='--max-old-space-size=4096'` may not work when:
- Environment variables aren't passed through
- Shell isn't properly initialized
- Node can't allocate that much

## Solutions

### Solution 1: Increase PHP Memory Limit

**Edit `/etc/php/8.x/fpm/php.ini` (or your PHP version):**

```ini
memory_limit = 2048M
max_execution_time = 900
max_input_time = 600
```

**Restart PHP:**
```bash
sudo systemctl restart php8.2-fpm
# Or for Apache module
sudo systemctl restart apache2
```

### Solution 2: Run Command with More Memory

Update your `.env.inc` files to increase Node memory:

```php
<?php

$org = 'mars';
$name = 'mpvg';

// Optionally add extra environment variables
$extra_env = [
  'NODE_OPTIONS' => '--max-old-space-size=8192',  // 8GB
];
```

Then modify the form to use these:

```php
public function executeEnvCommand(array &$form, FormStateInterface $form_state) {
  // ... existing code ...
  
  $extra_env = [];
  if (is_file($inc)) {
    ob_start();
    include $inc;
    ob_end_clean();
  }
  
  // Build environment variables string
  $env_string = '';
  if (!empty($extra_env)) {
    foreach ($extra_env as $key => $value) {
      $env_string .= escapeshellarg($key) . '=' . escapeshellarg($value) . ' ';
    }
  }
  
  $command = sprintf(
    '%s/opt/cmesh/scripts/pushfin.sh -o %s -n %s',
    $env_string,
    escapeshellarg($org),
    escapeshellarg($name)
  );
  
  // ... rest of code ...
}
```

### Solution 3: Use a Queue/Background Worker

For memory-intensive tasks, don't run from PHP directly:

**Option A: Use Drupal Queue**
```php
// Add to queue instead of running immediately
$queue = \Drupal::queue('cmesh_push_content');
$queue->createItem([
  'command' => $command,
  'env' => $envKey,
]);
```

**Option B: Use systemd service**

Create `/etc/systemd/system/cmesh-build@.service`:
```ini
[Unit]
Description=Cmesh Build for %i

[Service]
Type=oneshot
User=www-data
Group=www-data
Environment="NODE_OPTIONS=--max-old-space-size=8192"
ExecStart=/opt/cmesh/scripts/pushfin.sh -o %i -n %I
StandardOutput=append:/tmp/cmesh-build-%i.log
StandardError=append:/tmp/cmesh-build-%i.log

# Resource limits
MemoryMax=10G
MemoryHigh=8G
CPUQuota=400%
```

Then trigger from PHP:
```php
exec('systemctl start cmesh-build@' . escapeshellarg($org));
```

### Solution 4: Check Script Memory Usage

Modify `pushfin.sh` to track memory:

```bash
#!/bin/bash

echo "=== Memory before build ==="
free -h

echo "=== Starting build ==="
export NODE_OPTIONS='--max-old-space-size=8192'
cd /path/to/project
npm run build

echo "=== Memory after build ==="
free -h
```

### Solution 5: Swap Space

If system has limited RAM, add swap:

```bash
# Check current swap
swapon --show

# Create 4GB swap file
sudo fallocate -l 4G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Make permanent
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## Debugging Steps

### Step 1: Check What's Actually Running

```bash
# Watch processes while command runs
watch -n 1 'ps aux | grep -E "(node|next|npm)"'

# Monitor memory usage
watch -n 1 'free -h'
```

### Step 2: Run Command as Web Server User

```bash
# Switch to web server user
sudo -u www-data bash

# Try running the command
cd /opt/cmesh/previews/ramsalt-playground/fe
NODE_OPTIONS='--max-old-space-size=4096' npm run build

# Check for errors
echo $?
```

### Step 3: Check Actual Memory Limit

```bash
# For the running process
cat /proc/$(pgrep -f "next build")/limits

# Shows max virtual memory, max memory locked, etc.
```

### Step 4: Check System Resources

```bash
# Available memory
free -h

# CPU usage
top

# Disk space (builds need temp space)
df -h /tmp
df -h /opt/cmesh

# Check if any resource is maxed out
vmstat 1
```

### Step 5: Check for Memory Leaks

```bash
# Monitor memory during build
while true; do
  ps aux | grep "next build" | awk '{print $6}'
  sleep 1
done
```

## Quick Fixes to Try

### Fix 1: Increase Everything
```bash
# Edit PHP config
sudo nano /etc/php/8.2/fpm/php.ini

# Set these:
memory_limit = 2048M
max_execution_time = 900

# Restart
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx  # or apache2
```

### Fix 2: Run with Unlimited Memory
```php
// In CmeshPushContentService::executeCommand()
// Add before executing command:
ini_set('memory_limit', '-1');  // Unlimited
set_time_limit(0);  // No time limit
```

### Fix 3: Use nohup to Detach
Modify the service to use nohup:

```php
$full_command = sprintf(
  'nohup bash %s > %s 2>&1 & echo $! > %s',
  escapeshellarg($script_file),
  escapeshellarg($output_file),
  escapeshellarg($pid_file)
);
```

This fully detaches from PHP process.

## Recommended Solution

For Next.js builds specifically, I recommend:

1. **Increase PHP memory:**
   ```ini
   memory_limit = 2048M
   ```

2. **Increase Node memory in script:**
   ```bash
   export NODE_OPTIONS='--max-old-space-size=8192'
   ```

3. **Add swap if needed:**
   ```bash
   sudo fallocate -l 8G /swapfile
   # ... setup swap ...
   ```

4. **Use nice/ionice to prevent system overload:**
   ```bash
   nice -n 10 ionice -c3 npm run build
   ```

5. **Split into stages if possible:**
   ```bash
   # Instead of one big build:
   npm run build:dependencies
   npm run build:pages
   npm run build:static
   ```

## Long-Term Solution

Create a dedicated build server or container with:
- 8GB+ RAM allocated
- Dedicated resources
- Queue-based processing
- Monitoring and auto-restart

## Testing the Fix

After applying fixes:

```bash
# 1. Clear cache
drush cr

# 2. Test as web server user
sudo -u www-data bash -c 'php -i | grep memory_limit'

# 3. Try command through web
# Click "Push to dev" button

# 4. Monitor in real-time
tail -f /tmp/cmd_*_output.log

# 5. Check for OOM
dmesg | grep -i "out of memory"
```

## If Still Failing

Check these:

1. **Disk space:**
   ```bash
   df -h /tmp
   df -h /opt/cmesh
   ```

2. **Node modules size:**
   ```bash
   du -sh node_modules/
   ```

3. **Build cache:**
   ```bash
   # Clear Next.js cache
   rm -rf .next/
   ```

4. **System load:**
   ```bash
   uptime
   top
   ```

5. **Check build works at all:**
   ```bash
   cd /opt/cmesh/previews/ramsalt-playground/fe
   npm run build
   ```

## Need More Help?

Provide these details:
- `php -i | grep -E "(memory|execution|version)"`
- `free -h`
- `df -h`
- `ulimit -a` (as www-data user)
- `cat /proc/cpuinfo | grep -c processor`
- Full output from manual run vs. web run
