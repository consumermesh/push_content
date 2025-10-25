# Quick Fix for Exit Code 209

## The Error
```
The process' exit code is 'exited' and its exit status is 209.
```

Exit 209 = SETSID error (can't create new session)

## Most Common Cause

The **WorkingDirectory doesn't exist** or **user can't access it**.

## Quick Fix

```bash
# 1. Create required directories
sudo mkdir -p /opt/cmesh/scripts
sudo mkdir -p /var/log/cmesh
sudo chown -R http:http /opt/cmesh
sudo chown http:http /var/log/cmesh
sudo chmod -R 755 /opt/cmesh
sudo chmod 755 /var/log/cmesh

# 2. Update service file to remove WorkingDirectory
sudo nano /etc/systemd/system/cmesh-build@.service
```

Remove or comment out this line:
```ini
# WorkingDirectory=/opt/cmesh
```

Or use simplified service file:
```ini
[Unit]
Description=Cmesh Build for %i

[Service]
Type=oneshot
User=http
Group=http
Environment="PATH=/usr/local/bin:/usr/bin:/bin"
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i
StandardOutput=append:/var/log/cmesh/build-%i.log
StandardError=append:/var/log/cmesh/build-%i.log

[Install]
WantedBy=multi-user.target
```

```bash
# 3. Reload systemd
sudo systemctl daemon-reload

# 4. Test
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
```

## Verify Setup

```bash
# Check user exists
id http

# Check directories exist and are accessible
sudo -u http ls -la /opt/cmesh
sudo -u http ls -la /opt/cmesh/scripts
sudo -u http ls -la /var/log/cmesh

# Test script runs as user
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg
```

## Alternative: Use Different User

If `http` user has issues, check what your PHP-FPM uses:

```bash
# Check PHP-FPM config
grep -r "^user\|^group" /etc/php/*/fpm/pool.d/

# Might show www-data or nginx instead of http
```

Update service file to match:
```ini
User=www-data
Group=www-data
```

## Still Not Working?

Run and provide output:
```bash
id http
ls -ld /opt/cmesh
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg 2>&1
sudo journalctl -u cmesh-build@mars-mpvg -n 20 --no-pager
```
