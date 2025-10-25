# Quick Fix for Systemd Error 2

## The Error
```
The process /opt/cmesh/scripts/pushfin-systemd.sh could not be executed and failed.
The error number returned by this process is 2.
```

Error 2 = "No such file or directory" (usually the bash interpreter)

## Most Likely Cause (Arch Linux)

On Arch Linux, bash is at `/usr/bin/bash`, not `/bin/bash`.

## Quick Fix

```bash
# Check where bash is
which bash
# Output: /usr/bin/bash (on Arch)

# Fix the script shebang
sudo sed -i '1s|^#!/bin/bash|#!/usr/bin/env bash|' /opt/cmesh/scripts/pushfin-systemd.sh

# Test the script
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# If that works, test the service
sudo systemctl start cmesh-build@mars-mpvg
sudo systemctl status cmesh-build@mars-mpvg
```

## Other Common Issues

### 1. Windows Line Endings (CRLF)

```bash
# Check
file /opt/cmesh/scripts/pushfin-systemd.sh
# Bad: "with CRLF line terminators"

# Fix
dos2unix /opt/cmesh/scripts/pushfin-systemd.sh
# Or:
sed -i 's/\r$//' /opt/cmesh/scripts/pushfin-systemd.sh
```

### 2. Script Not Executable

```bash
# Check
ls -la /opt/cmesh/scripts/pushfin-systemd.sh
# Should show: -rwxr-xr-x

# Fix
chmod +x /opt/cmesh/scripts/pushfin-systemd.sh
```

### 3. pushfin.sh Doesn't Exist

```bash
# Check
ls -la /opt/cmesh/scripts/pushfin.sh

# If missing, you need to create or locate it
```

## Debugging Steps

```bash
# 1. Test script directly
sudo -u http bash -x /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg

# 2. Check script content
head -5 /opt/cmesh/scripts/pushfin-systemd.sh

# 3. Check for hidden characters
cat -A /opt/cmesh/scripts/pushfin-systemd.sh | head

# 4. View systemd logs
sudo journalctl -u cmesh-build@mars-mpvg -xe
```

## Complete Script Reinstall

If nothing works, recreate the script:

```bash
# Delete old script
sudo rm /opt/cmesh/scripts/pushfin-systemd.sh

# Create new with proper content
sudo tee /opt/cmesh/scripts/pushfin-systemd.sh > /dev/null << 'ENDOFSCRIPT'
#!/usr/bin/env bash

set -e
set -u

INSTANCE="${1:-}"

if [[ -z "$INSTANCE" ]]; then
    echo "Error: No instance provided" >&2
    exit 1
fi

if [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
else
    echo "Error: Invalid instance format: $INSTANCE" >&2
    exit 1
fi

echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Date: $(date)"
echo ""

exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
ENDOFSCRIPT

# Make executable
sudo chmod +x /opt/cmesh/scripts/pushfin-systemd.sh

# Set ownership
sudo chown http:http /opt/cmesh/scripts/pushfin-systemd.sh

# Test
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg
```

## After Fixing

```bash
# Reload systemd
sudo systemctl daemon-reload

# Test service
sudo systemctl start cmesh-build@mars-mpvg

# Check status
sudo systemctl status cmesh-build@mars-mpvg

# View logs
sudo journalctl -u cmesh-build@mars-mpvg -f
```

## Still Not Working?

Run these and provide output:

```bash
which bash
ls -la /opt/cmesh/scripts/pushfin-systemd.sh
file /opt/cmesh/scripts/pushfin-systemd.sh
head -1 /opt/cmesh/scripts/pushfin-systemd.sh | od -c
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh mars-mpvg 2>&1
sudo journalctl -u cmesh-build@mars-mpvg -n 20 --no-pager
```
