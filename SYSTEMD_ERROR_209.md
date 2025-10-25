# Systemd Exit Code 209 - SETSID Error

## The Error

```
The process' exit code is 'exited' and its exit status is 209.
```

Exit code 209 = systemd error code for **SETSID** failure (session creation failed)

## What This Means

Systemd couldn't create a new process session for the service. This is usually caused by:
1. Invalid User/Group
2. Working directory doesn't exist or not accessible
3. User can't create new sessions
4. Permission issues

## Quick Fixes

### Fix 1: Check User/Group Exists

```bash
# Check if http user exists
id http
# Should show: uid=33(http) gid=33(http) groups=33(http)

# If user doesn't exist, create it (usually already exists on Arch)
sudo useradd -r -s /usr/bin/nologin http

# Or use www-data if that's what your system uses
id www-data
```

### Fix 2: Check/Fix Working Directory

```bash
# Check if /opt/cmesh exists
ls -ld /opt/cmesh

# If doesn't exist, create it
sudo mkdir -p /opt/cmesh
sudo chown http:http /opt/cmesh
sudo chmod 755 /opt/cmesh
```

### Fix 3: Change Working Directory to /tmp

Edit the service file to use a directory that definitely exists:

```bash
sudo nano /etc/systemd/system/cmesh-build@.service
```

Change:
```ini
WorkingDirectory=/opt/cmesh
```

To:
```ini
WorkingDirectory=/tmp
```

### Fix 4: Remove WorkingDirectory Entirely

Sometimes it's better to not specify it:

```bash
sudo nano /etc/systemd/system/cmesh-build@.service
```

Comment out or remove:
```ini
# WorkingDirectory=/opt/cmesh
```

### Fix 5: Use DynamicUser

Let systemd create a temporary user:

```bash
sudo nano /etc/systemd/system/cmesh-build@.service
```

Replace User/Group with DynamicUser:
```ini
# User=http
# Group=http
DynamicUser=yes
```

## Recommended Service File for Arch

Here's a working service file for Arch Linux:

```ini
[Unit]
Description=Cmesh Build for %i
After=network.target

[Service]
Type=oneshot

# User - choose one option:
# Option 1: Use http user (Arch default)
User=http
Group=http

# Option 2: Use www-data user (if you have it)
# User=www-data
# Group=www-data

# Option 3: Use dynamic user (systemd creates temporary user)
# DynamicUser=yes

# Working directory - use /tmp if /opt/cmesh has permission issues
WorkingDirectory=/tmp

# Environment variables
Environment="NODE_OPTIONS=--max-old-space-size=8192"
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
Environment="HOME=/tmp"

# The command to execute
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i

# Logging
StandardOutput=append:/var/log/cmesh/build-%i.log
StandardError=append:/var/log/cmesh/build-%i.log

# Resource limits
MemoryMax=10G
CPUQuota=400%%
TimeoutStartSec=900

# Don't restart
Restart=no

[Install]
WantedBy=multi-user.target
```

## Step-by-Step Fix

### Step 1: Verify User

```bash
# Check http user
id http

# If doesn't exist, check what web server user you have
ps aux | grep -E 'nginx|httpd|php-fpm' | head -1

# Common users:
# - http (Arch)
# - www-data (Debian/Ubuntu)
# - nginx (some systems)
# - apache (RHEL/CentOS)
```

### Step 2: Create Directories

```bash
# Create working directory
sudo mkdir -p /opt/cmesh
sudo chown http:http /opt/cmesh
sudo chmod 755 /opt/cmesh

# Create log directory
sudo mkdir -p /var/log/cmesh
sudo chown http:http /var/log/cmesh
sudo chmod 755 /var/log/cmesh

# Create scripts directory if needed
sudo mkdir -p /opt/cmesh/scripts
sudo chown http:http /opt/cmesh/scripts
sudo chmod 755 /opt/cmesh/scripts
```

### Step 3: Test as User

```bash
# Test if http user can access directories
sudo -u http ls -la /opt/cmesh
sudo -u http ls -la /opt/cmesh/scripts
sudo -u http ls -la /var/log/cmesh

# Test if http user can run script
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg
```

### Step 4: Update Service File

```bash
# Edit service
sudo nano /etc/systemd/system/cmesh-build@.service

# Use this minimal working version:
```

```ini
[Unit]
Description=Cmesh Build for %i

[Service]
Type=oneshot
User=http
Group=http
WorkingDirectory=/tmp
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

### Step 5: Reload and Test

```bash
# Reload systemd
sudo systemctl daemon-reload

# Test the service
sudo systemctl start cmesh-build@mars-mpvg

# Check status
sudo systemctl status cmesh-build@mars-mpvg

# View logs
sudo journalctl -u cmesh-build@mars-mpvg -xe
```

## Alternative: Use Root User (Testing Only)

For testing, you can run as root:

```bash
sudo nano /etc/systemd/system/cmesh-build@.service
```

Change to:
```ini
User=root
Group=root
```

**⚠️ Warning:** Only for testing! Don't use in production.

## Alternative: Simplify Service File

Minimal service that should work:

```ini
[Unit]
Description=Cmesh Build for %i

[Service]
Type=simple
ExecStart=/usr/bin/bash /opt/cmesh/scripts/pushfin-systemd.sh %i

[Install]
WantedBy=multi-user.target
```

This removes all user/group/directory specifications and lets systemd use defaults.

## Check Systemd Logs for More Details

```bash
# View detailed logs
sudo journalctl -u cmesh-build@mars-mpvg -n 50 --no-pager

# View with extra detail
sudo journalctl -xe -u cmesh-build@mars-mpvg

# Check systemd debug output
sudo systemd-analyze verify /etc/systemd/system/cmesh-build@.service
```

## Common Causes on Arch

### 1. http User Can't Create Sessions

```bash
# Check user's login shell
getent passwd http
# Should show: http:x:33:33:http:/:/usr/bin/nologin

# Check if user is locked
sudo passwd -S http
```

### 2. PAM Configuration

```bash
# Check PAM settings
ls -la /etc/pam.d/

# Sometimes needed (rare)
sudo systemctl edit cmesh-build@.service
```

Add:
```ini
[Service]
PAMName=system-auth
```

### 3. Directory Permissions

```bash
# Verify all directories in path are accessible
sudo -u http bash -c 'cd /opt/cmesh && pwd'

# Should output: /opt/cmesh
# If error, fix permissions
```

## Debugging Commands

```bash
# 1. Verify user exists
id http
getent passwd http

# 2. Test user can execute
sudo -u http whoami
sudo -u http pwd

# 3. Test script manually
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# 4. Test with systemd-run
sudo systemd-run --unit=test-build --uid=http --gid=http \
  /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# 5. Check status
sudo systemctl status test-build

# 6. View logs
sudo journalctl -u test-build
```

## Working Configuration Summary

After trying fixes, you should have:

```bash
# User exists
id http
# uid=33(http) gid=33(http)

# Directories exist and accessible
ls -ld /opt/cmesh
# drwxr-xr-x 3 http http 4096 ... /opt/cmesh

ls -ld /var/log/cmesh
# drwxr-xr-x 2 http http 4096 ... /var/log/cmesh

# Script exists and executable
ls -la /opt/cmesh/scripts/pushfin-systemd.sh
# -rwxr-xr-x 1 http http ... pushfin-systemd.sh

# Service file valid
sudo systemd-analyze verify /etc/systemd/system/cmesh-build@.service
# No output = good

# Service works
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
# Active: active (running) or exited (success)
```

## If Still Failing

Provide these outputs:

```bash
# 1. User info
id http
getent passwd http

# 2. Directory permissions
ls -ld /opt/cmesh
ls -ld /opt/cmesh/scripts
ls -ld /var/log/cmesh

# 3. Script details
ls -la /opt/cmesh/scripts/pushfin-systemd.sh

# 4. Service file
cat /etc/systemd/system/cmesh-build@.service

# 5. Test as user
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg 2>&1

# 6. Systemd logs
sudo journalctl -u cmesh-build@mars-mpvg -n 50 --no-pager

# 7. System info
uname -a
systemctl --version
```

## Most Likely Solution for Arch

Based on Arch Linux + http user + exit 209:

```bash
# 1. Make sure /opt/cmesh exists
sudo mkdir -p /opt/cmesh/scripts
sudo chown -R http:http /opt/cmesh
sudo chmod -R 755 /opt/cmesh

# 2. Make sure log directory exists
sudo mkdir -p /var/log/cmesh
sudo chown http:http /var/log/cmesh
sudo chmod 755 /var/log/cmesh

# 3. Use simplified service file
sudo tee /etc/systemd/system/cmesh-build@.service > /dev/null << 'EOF'
[Unit]
Description=Cmesh Build for %i

[Service]
Type=oneshot
User=http
Group=http
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i
StandardOutput=append:/var/log/cmesh/build-%i.log
StandardError=append:/var/log/cmesh/build-%i.log
EOF

# 4. Reload and test
sudo systemctl daemon-reload
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
```

That should work!
