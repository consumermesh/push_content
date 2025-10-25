# Systemd Error 2: "Could not be executed"

## The Error

```
The process /opt/cmesh/scripts/pushfin-systemd.sh could not be executed and failed.
The error number returned by this process is 2.
```

Error number 2 = ENOENT = "No such file or directory"

## Common Causes

### 1. Shebang Interpreter Not Found (MOST COMMON)

The script starts with `#!/bin/bash` but `/bin/bash` doesn't exist.

**Check:**
```bash
head -1 /opt/cmesh/scripts/pushfin-systemd.sh
# Should show: #!/bin/bash

# Check if bash exists at that path
ls -la /bin/bash

# Find where bash actually is
which bash
# Might return: /usr/bin/bash
```

**Fix if bash is at /usr/bin/bash:**
```bash
# Edit the script
sudo nano /opt/cmesh/scripts/pushfin-systemd.sh

# Change first line from:
#!/bin/bash

# To:
#!/usr/bin/bash
```

**Or create symlink:**
```bash
sudo ln -s /usr/bin/bash /bin/bash
```

### 2. Windows Line Endings (CRLF vs LF)

If the file was created on Windows or edited with certain tools, it may have Windows line endings.

**Check:**
```bash
file /opt/cmesh/scripts/pushfin-systemd.sh
# Good: "Bourne-Again shell script, ASCII text executable"
# Bad:  "Bourne-Again shell script, ASCII text executable, with CRLF line terminators"
```

**Fix:**
```bash
# Install dos2unix if needed
sudo pacman -S dos2unix  # Arch
sudo apt-get install dos2unix  # Debian/Ubuntu

# Convert line endings
dos2unix /opt/cmesh/scripts/pushfin-systemd.sh

# Or manually with sed
sed -i 's/\r$//' /opt/cmesh/scripts/pushfin-systemd.sh
```

### 3. Script Path Wrong in Service File

The service file points to wrong path.

**Check:**
```bash
# Verify script exists
ls -la /opt/cmesh/scripts/pushfin-systemd.sh

# Check what the service file says
grep ExecStart /etc/systemd/system/cmesh-build@.service
```

**Fix if needed:**
```bash
sudo nano /etc/systemd/system/cmesh-build@.service

# Make sure ExecStart has full absolute path:
ExecStart=/opt/cmesh/scripts/pushfin-systemd.sh %i

# Reload
sudo systemctl daemon-reload
```

### 4. Script Not Executable

**Check:**
```bash
ls -la /opt/cmesh/scripts/pushfin-systemd.sh
# Should show: -rwxr-xr-x (the 'x' means executable)
```

**Fix:**
```bash
chmod +x /opt/cmesh/scripts/pushfin-systemd.sh
```

### 5. Dependencies Not Found

The script calls other scripts/commands that don't exist.

**Check what the script calls:**
```bash
cat /opt/cmesh/scripts/pushfin-systemd.sh
```

**Verify dependencies exist:**
```bash
# If script calls /opt/cmesh/scripts/pushfin.sh:
ls -la /opt/cmesh/scripts/pushfin.sh

# Should exist and be executable
```

### 6. SELinux Context Wrong

If SELinux is enforcing, scripts need proper context.

**Check:**
```bash
getenforce
# If returns "Enforcing":

ls -Z /opt/cmesh/scripts/pushfin-systemd.sh
# Check the security context
```

**Fix:**
```bash
# Set proper context
sudo chcon -t bin_t /opt/cmesh/scripts/pushfin-systemd.sh

# Or disable SELinux temporarily to test
sudo setenforce 0
```

## Step-by-Step Debugging

### Step 1: Find Bash Location

```bash
which bash
# Example output: /usr/bin/bash

# Check both common locations
ls -la /bin/bash
ls -la /usr/bin/bash
```

**Most systems:**
- Debian/Ubuntu: `/bin/bash` (symlink to `/usr/bin/bash`)
- Arch/Manjaro: `/usr/bin/bash`
- RHEL/CentOS: `/bin/bash`

### Step 2: Check Script Content

```bash
# View first few lines
head -5 /opt/cmesh/scripts/pushfin-systemd.sh

# Should start with:
#!/bin/bash  (or #!/usr/bin/bash)
#
# Systemd wrapper for pushfin.sh
```

### Step 3: Check for Hidden Characters

```bash
# Check for carriage returns
cat -A /opt/cmesh/scripts/pushfin-systemd.sh | head

# Look for ^M at end of lines (indicates CRLF)
# Should just see $ at end of lines (indicates LF)
```

### Step 4: Test Script Manually

```bash
# Try running directly
/opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# Try with bash explicitly
bash /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# Try with full path bash
/usr/bin/bash /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg
```

### Step 5: Check Permissions

```bash
# Check script permissions
ls -la /opt/cmesh/scripts/pushfin-systemd.sh

# Check directory permissions
ls -la /opt/cmesh/scripts/

# Check if http user can read it
sudo -u http cat /opt/cmesh/scripts/pushfin-systemd.sh

# Check if http user can execute it
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg
```

### Step 6: Run Service with Verbose Logging

```bash
# Stop any running instance
sudo systemctl stop cmesh-build@mars-mpvg

# Run with debug output
sudo systemd-run --unit=test-cmesh \
  --uid=http --gid=http \
  --working-directory=/opt/cmesh \
  /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# Check output
sudo journalctl -u test-cmesh
```

## Quick Fixes to Try

### Fix 1: Update Shebang

```bash
# Edit script
sudo nano /opt/cmesh/scripts/pushfin-systemd.sh

# Change first line to use env
#!/usr/bin/env bash

# This will find bash wherever it is
```

### Fix 2: Recreate Script

```bash
# Delete old script
sudo rm /opt/cmesh/scripts/pushfin-systemd.sh

# Create new with correct line endings
sudo cat > /opt/cmesh/scripts/pushfin-systemd.sh << 'ENDOFSCRIPT'
#!/usr/bin/env bash

# Systemd wrapper for pushfin.sh
# Parses the instance name and calls the actual script
# Instance format: org-name (e.g., mars-mpvg)

INSTANCE="$1"

# Split instance into org and name
if [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
else
    echo "Error: Invalid instance format: $INSTANCE" >&2
    echo "Expected format: org-name" >&2
    exit 1
fi

echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Started: $(date)"
echo "NODE_OPTIONS: $NODE_OPTIONS"
echo ""

# Execute the actual build script
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
ENDOFSCRIPT

# Make executable
sudo chmod +x /opt/cmesh/scripts/pushfin-systemd.sh

# Set ownership
sudo chown http:http /opt/cmesh/scripts/pushfin-systemd.sh
```

### Fix 3: Test with Absolute Bash Path

```bash
# Edit service file
sudo nano /etc/systemd/system/cmesh-build@.service

# Change ExecStart to use bash explicitly:
ExecStart=/usr/bin/bash /opt/cmesh/scripts/pushfin-systemd.sh %i

# Or wherever bash is:
ExecStart=/bin/bash /opt/cmesh/scripts/pushfin-systemd.sh %i

# Reload and test
sudo systemctl daemon-reload
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
```

### Fix 4: Check Environment Path

```bash
# Edit service file
sudo nano /etc/systemd/system/cmesh-build@.service

# Make sure PATH includes bash location
Environment="PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin"

# Reload
sudo systemctl daemon-reload
```

## Diagnostic Commands

```bash
# 1. Check bash location
type bash
which bash
whereis bash

# 2. Check script shebang
head -1 /opt/cmesh/scripts/pushfin-systemd.sh | od -c
# Should show: #!/bin/bash or #!/usr/bin/bash

# 3. Check for CRLF
file /opt/cmesh/scripts/pushfin-systemd.sh

# 4. Check permissions
stat /opt/cmesh/scripts/pushfin-systemd.sh

# 5. Test as service user
sudo -u http bash -c 'ls -la /opt/cmesh/scripts/pushfin-systemd.sh'
sudo -u http bash -c '/opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg'

# 6. Check systemd logs
sudo journalctl -xe -u cmesh-build@mars-mpvg

# 7. Check for SELinux denials
sudo ausearch -m avc -ts recent

# 8. Validate service file
sudo systemd-analyze verify /etc/systemd/system/cmesh-build@.service
```

## Working Script Template

Here's a guaranteed-working version:

```bash
#!/usr/bin/env bash

set -e  # Exit on error
set -u  # Exit on undefined variable

INSTANCE="${1:-}"

if [[ -z "$INSTANCE" ]]; then
    echo "Error: No instance provided" >&2
    exit 1
fi

# Parse instance: org-name
if [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
else
    echo "Error: Invalid instance format: $INSTANCE" >&2
    echo "Expected format: org-name (e.g., mars-mpvg)" >&2
    exit 1
fi

# Log start
echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "User: $(whoami)"
echo "PATH: $PATH"
echo "Working Directory: $(pwd)"
echo ""

# Verify pushfin.sh exists
if [[ ! -x "/opt/cmesh/scripts/pushfin.sh" ]]; then
    echo "Error: /opt/cmesh/scripts/pushfin.sh not found or not executable" >&2
    exit 1
fi

# Execute the build
echo "Executing: /opt/cmesh/scripts/pushfin.sh -o $ORG -n $NAME"
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
```

## After Fixing

```bash
# 1. Test script directly
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# 2. Test via systemd
sudo systemctl start cmesh-build@mars-mpvg

# 3. Check status
sudo systemctl status cmesh-build@mars-mpvg

# 4. View logs
sudo journalctl -u cmesh-build@mars-mpvg -f
```

## Still Not Working?

Provide these outputs:

```bash
# 1. Script details
ls -la /opt/cmesh/scripts/pushfin-systemd.sh
file /opt/cmesh/scripts/pushfin-systemd.sh
head -1 /opt/cmesh/scripts/pushfin-systemd.sh | od -c

# 2. Bash location
which bash
ls -la /bin/bash
ls -la /usr/bin/bash

# 3. Service file
cat /etc/systemd/system/cmesh-build@.service

# 4. Test output
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg 2>&1

# 5. Systemd logs
sudo journalctl -u cmesh-build@mars-mpvg -n 50 --no-pager
```

## Most Likely Solution

Based on your setup (Arch Linux with http:http user), the issue is probably:

**The shebang points to `/bin/bash` but Arch uses `/usr/bin/bash`**

**Fix:**
```bash
sudo sed -i '1s|^#!/bin/bash|#!/usr/bin/bash|' /opt/cmesh/scripts/pushfin-systemd.sh
```

Or change to:
```bash
sudo sed -i '1s|^#!.*|#!/usr/bin/env bash|' /opt/cmesh/scripts/pushfin-systemd.sh
```

Then test:
```bash
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
```
