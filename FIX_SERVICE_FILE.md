# Service File Not Using Wrapper Script

## The Problem

Your service file is calling `pushfin.sh` directly instead of using the wrapper `pushfin-systemd.sh`.

This means the instance name (`ramsalt-playground`) is being passed directly to `pushfin.sh`, which probably doesn't know how to handle it.

## The Fix

Update your service file to use the wrapper script.

### Option 1: Reinstall Service File

```bash
# Copy the correct service file from the module
sudo cp /path/to/module/config/cmesh-build@.service /etc/systemd/system/

# Reload systemd
sudo systemctl daemon-reload

# Verify it's correct
cat /etc/systemd/system/cmesh-build@.service | grep ExecStart
# Should show: ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i
```

### Option 2: Edit Service File Directly

```bash
# Edit the service file
sudo nano /etc/systemd/system/cmesh-build@.service
```

Find the line:
```ini
ExecStart=/opt/cmesh/scripts/pushfin.sh %i
```

Change to:
```ini
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i
```

Save and exit, then:

```bash
# Reload systemd
sudo systemctl daemon-reload

# Verify
systemctl cat cmesh-build@.service | grep ExecStart
```

## Complete Correct Service File

Here's what your service file should look like:

```ini
[Unit]
Description=Cmesh Build for %i
After=network.target

[Service]
Type=oneshot

# User and group
User=http
Group=http

# Environment variables
Environment="NODE_OPTIONS=--max-old-space-size=8192"
Environment="PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"
Environment="HOME=/tmp"
Environment="NODE_ENV=production"

# The command to execute - MUST use wrapper script
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i

# Logging
StandardOutput=append:/var/log/cmesh/build-%i.log
StandardError=append:/var/log/cmesh/build-%i.log

# Resource limits
MemoryMax=10G
MemoryHigh=8G
CPUQuota=400%%
TimeoutStartSec=900
TimeoutStopSec=60

# Restart policy
Restart=no

[Install]
WantedBy=multi-user.target
```

## Why the Wrapper is Needed

### Without Wrapper (Wrong):
```
Service instance: ramsalt-playground
↓
pushfin.sh gets: "ramsalt-playground" as single argument
↓
pushfin.sh doesn't know what to do with it ❌
↓
Exit code 64 (usage error)
```

### With Wrapper (Correct):
```
Service instance: ramsalt-playground
↓
pushfin-systemd.sh gets: "ramsalt-playground"
↓
Wrapper parses: ORG=ramsalt, NAME=playground
↓
Wrapper calls: pushfin.sh -o ramsalt -n playground
↓
pushfin.sh understands the arguments ✅
```

## Alternative: If You Want Direct Call

If you want to keep calling `pushfin.sh` directly, you need to change how the service passes arguments:

```ini
# Option A: Pass org and name separately
ExecStart=/opt/cmesh/scripts/pushfin.sh -o ramsalt -n %i

# Option B: Use a different instance format
# Then call with: systemctl start cmesh-build@playground
ExecStart=/opt/cmesh/scripts/pushfin.sh -o ramsalt -n %i
```

But this is **not recommended** because it's less flexible. The wrapper is the proper solution.

## Verification Steps

After fixing:

```bash
# 1. Verify service file is correct
sudo systemctl cat cmesh-build@.service | grep -A2 ExecStart
# Should show: ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i

# 2. Test the service
sudo systemctl start cmesh-build@ramsalt-playground

# 3. Check it's using the wrapper
sudo journalctl -u cmesh-build@ramsalt-playground -n 30 --no-pager
# Should see "=== Cmesh Build Started ===" from the wrapper
# Should see "Organization: ramsalt"
# Should see "Name: playground"

# 4. Check status
sudo systemctl status cmesh-build@ramsalt-playground
```

## How to Find Your Service File

```bash
# Find all service files
find /etc/systemd -name "cmesh-build*"

# View the one in use
systemctl cat cmesh-build@.service

# Or directly
cat /etc/systemd/system/cmesh-build@.service
```

## Quick Fix Script

Run this to fix everything:

```bash
# Create correct service file
sudo tee /etc/systemd/system/cmesh-build@.service > /dev/null << 'EOF'
[Unit]
Description=Cmesh Build for %i
After=network.target

[Service]
Type=oneshot
User=http
Group=http

Environment="NODE_OPTIONS=--max-old-space-size=8192"
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
Environment="HOME=/tmp"

ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i

StandardOutput=append:/var/log/cmesh/build-%i.log
StandardError=append:/var/log/cmesh/build-%i.log

MemoryMax=10G
TimeoutStartSec=900

Restart=no

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd
sudo systemctl daemon-reload

# Test
sudo systemctl start cmesh-build@ramsalt-playground
sudo systemctl status cmesh-build@ramsalt-playground
```

## After Fixing

The logs should now show:

```
=== Cmesh Build Started ===
Instance: ramsalt-playground
Organization: ramsalt
Name: playground
Started: 2025-10-25 15:30:00
User: http
...
Executing: /opt/cmesh/scripts/pushfin.sh -o 'ramsalt' -n 'playground'
```

Instead of just an error!

## Summary

**Problem:** Service calls `pushfin.sh` directly with instance name
**Solution:** Service should call `pushfin-systemd.sh` which parses the instance name

Update the `ExecStart` line in your service file!
