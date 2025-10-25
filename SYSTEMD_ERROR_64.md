# Systemd Exit Code 64 - Command Usage Error

## The Error

```
Main process exited, code=exited, status=64
```

Exit code 64 = **EX_USAGE** = Command line usage error

## What This Means

The script ran but encountered an error with:
1. Invalid arguments/parsing
2. Missing required file (pushfin.sh)
3. pushfin.sh itself returned exit code 64

## Instance Name Parsing

Your instance: `ramsalt-playground`

The script tries to parse this as:
- **Pattern 1 (colon):** `org:name` → Not matched (no colon)
- **Pattern 2 (dash):** `org-name` → Matches!
  - ORG = `ramsalt`
  - NAME = `playground`

This should work, so the issue is likely downstream.

## Debugging Steps

### Step 1: Check the Logs

```bash
# View full service logs
sudo journalctl -u cmesh-build@ramsalt-playground -n 50 --no-pager

# Or view log file if it was created
sudo cat /var/log/cmesh/build-ramsalt-playground.log
```

Look for the actual error message after "=== Cmesh Build Started ==="

### Step 2: Test Script Manually

```bash
# Run the wrapper script directly
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground

# This should show you the actual error
```

### Step 3: Check if pushfin.sh Exists

```bash
# Check main script exists
ls -la /opt/cmesh/scripts/pushfin.sh

# Check if it's executable
file /opt/cmesh/scripts/pushfin.sh

# Test if user can access it
sudo -u http test -x /opt/cmesh/scripts/pushfin.sh && echo "OK" || echo "NOT EXECUTABLE"
```

### Step 4: Check pushfin.sh Arguments

```bash
# See what pushfin.sh expects
head -20 /opt/cmesh/scripts/pushfin.sh

# Try running it manually
sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground

# Or check its usage
sudo -u http /opt/cmesh/scripts/pushfin.sh --help
sudo -u http /opt/cmesh/scripts/pushfin.sh
```

## Common Issues

### Issue 1: pushfin.sh Doesn't Exist

```bash
# Check if file exists
ls -la /opt/cmesh/scripts/pushfin.sh
```

**If missing**, you need to create or locate it. This is the actual script that does the build work.

**Temporary test script:**
```bash
# Create a dummy pushfin.sh for testing
sudo tee /opt/cmesh/scripts/pushfin.sh > /dev/null << 'EOF'
#!/usr/bin/env bash

echo "pushfin.sh called with arguments: $@"

ORG=""
NAME=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -o)
            ORG="$2"
            shift 2
            ;;
        -n)
            NAME="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

if [[ -z "$ORG" || -z "$NAME" ]]; then
    echo "Error: -o ORG and -n NAME are required" >&2
    exit 64
fi

echo "Organization: $ORG"
echo "Name: $NAME"
echo "Would run build here..."
echo "Build completed successfully!"
EOF

sudo chmod +x /opt/cmesh/scripts/pushfin.sh
sudo chown http:http /opt/cmesh/scripts/pushfin.sh
```

### Issue 2: pushfin.sh Expects Different Arguments

The wrapper calls:
```bash
/opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
```

But maybe pushfin.sh expects:
- Different flag names (`--org` instead of `-o`)
- Different order
- Additional required arguments

**Fix wrapper script:**
```bash
sudo nano /opt/cmesh/scripts/pushfin-systemd.sh
```

Change the `exec` line to match what pushfin.sh expects.

### Issue 3: pushfin.sh Not Executable

```bash
# Make executable
sudo chmod +x /opt/cmesh/scripts/pushfin.sh

# Test
sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground
```

### Issue 4: Wrong Shebang in pushfin.sh

```bash
# Check first line
head -1 /opt/cmesh/scripts/pushfin.sh

# Should be:
#!/usr/bin/env bash
# or
#!/usr/bin/bash
# or
#!/bin/bash

# Fix if needed
sudo sed -i '1s|^#!.*|#!/usr/bin/env bash|' /opt/cmesh/scripts/pushfin.sh
```

### Issue 5: pushfin.sh Has Different Name

Maybe the script is named differently:

```bash
# Find scripts in directory
ls -la /opt/cmesh/scripts/

# Common alternatives
ls -la /opt/cmesh/scripts/ | grep -E 'push|build|deploy|fin'
```

If found with different name, update wrapper:
```bash
sudo nano /opt/cmesh/scripts/pushfin-systemd.sh
```

Change the exec line to use the correct script name.

### Issue 6: Instance Name Has Issues

The instance name `ramsalt-playground` gets parsed as:
- ORG: `ramsalt`
- NAME: `playground`

But maybe you need:
- ORG: `ramsalt`
- NAME: `ramsalt-playground` (full name with dash)

**If so, change service instance name format:**

Instead of:
```bash
sudo systemctl start cmesh-build@ramsalt-playground
```

Use colon delimiter:
```bash
sudo systemctl start cmesh-build@ramsalt:ramsalt-playground
```

Or update your env config to use proper format.

## Updated Wrapper Script with Better Error Messages

Replace the wrapper script with this version that shows more details:

```bash
sudo tee /opt/cmesh/scripts/pushfin-systemd.sh > /dev/null << 'ENDOFSCRIPT'
#!/usr/bin/env bash

set -e
set -u

INSTANCE="${1:-}"

if [[ -z "$INSTANCE" ]]; then
    echo "Error: No instance provided" >&2
    echo "Usage: $0 INSTANCE" >&2
    echo "Examples: $0 mars-mpvg" >&2
    echo "         $0 ramsalt:playground" >&2
    exit 64
fi

# Parse instance name
# Try colon first (allows dashes in name)
if [[ "$INSTANCE" =~ ^([^:]+):(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
    echo "Parsed using colon delimiter"
# Fall back to dash (split on first dash)
elif [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
    echo "Parsed using dash delimiter"
else
    echo "Error: Cannot parse instance: $INSTANCE" >&2
    echo "Expected format: org:name or org-name" >&2
    exit 64
fi

echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Date: $(date '+%Y-%m-%d %H:%M:%S')"
echo "User: $(whoami)"
echo "UID: $(id -u)"
echo "GID: $(id -g)"
echo "HOME: ${HOME:-not set}"
echo "PATH: ${PATH:-not set}"
echo "NODE_OPTIONS: ${NODE_OPTIONS:-not set}"
echo ""

# Check if pushfin.sh exists
PUSHFIN_SCRIPT="/opt/cmesh/scripts/pushfin.sh"

if [[ ! -f "$PUSHFIN_SCRIPT" ]]; then
    echo "Error: Script not found: $PUSHFIN_SCRIPT" >&2
    echo "Listing /opt/cmesh/scripts/:" >&2
    ls -la /opt/cmesh/scripts/ >&2 || echo "Cannot list directory" >&2
    exit 1
fi

if [[ ! -x "$PUSHFIN_SCRIPT" ]]; then
    echo "Error: Script not executable: $PUSHFIN_SCRIPT" >&2
    ls -la "$PUSHFIN_SCRIPT" >&2
    exit 1
fi

# Execute
echo "Executing: $PUSHFIN_SCRIPT -o '$ORG' -n '$NAME'"
echo "---"
exec "$PUSHFIN_SCRIPT" -o "$ORG" -n "$NAME"
ENDOFSCRIPT

sudo chmod +x /opt/cmesh/scripts/pushfin-systemd.sh
sudo chown http:http /opt/cmesh/scripts/pushfin-systemd.sh
```

Then test:
```bash
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground
```

This will show you exactly where it fails.

## Check Environment Files

Your env file might be:

```bash
# Check if env file exists
ls -la /path/to/module/config/ramsalt-playground.env.inc
```

The instance name should match the env file name (without `.env.inc`).

## Test End-to-End

```bash
# 1. Test wrapper directly
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground

# 2. If that works, test via systemd
sudo systemctl start cmesh-build@ramsalt-playground

# 3. Check status
sudo systemctl status cmesh-build@ramsalt-playground

# 4. View logs
sudo journalctl -u cmesh-build@ramsalt-playground -f
```

## If pushfin.sh Doesn't Exist

You need to either:

1. **Create it** - This is your actual build script
2. **Find it** - Maybe it's in a different location
3. **Point to different script** - Update the wrapper

Example build script:
```bash
sudo tee /opt/cmesh/scripts/pushfin.sh > /dev/null << 'EOF'
#!/usr/bin/env bash

# Parse arguments
ORG=""
NAME=""

while [[ $# -gt 0 ]]; do
    case $1 in
        -o|--org)
            ORG="$2"
            shift 2
            ;;
        -n|--name)
            NAME="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

if [[ -z "$ORG" || -z "$NAME" ]]; then
    echo "Usage: $0 -o ORG -n NAME" >&2
    exit 64
fi

echo "Running build for $ORG/$NAME..."

# Your actual build commands here
cd "/opt/cmesh/previews/$NAME/fe" || exit 1
npm run build

echo "Build completed!"
EOF

sudo chmod +x /opt/cmesh/scripts/pushfin.sh
sudo chown http:http /opt/cmesh/scripts/pushfin.sh
```

## Quick Check Checklist

```bash
# 1. Wrapper exists and is executable
ls -la /opt/cmesh/scripts/pushfin-systemd.sh

# 2. Main script exists and is executable
ls -la /opt/cmesh/scripts/pushfin.sh

# 3. User can access both
sudo -u http test -x /opt/cmesh/scripts/pushfin-systemd.sh && echo "Wrapper OK"
sudo -u http test -x /opt/cmesh/scripts/pushfin.sh && echo "Main script OK"

# 4. Test wrapper
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground

# 5. Check logs
sudo journalctl -u cmesh-build@ramsalt-playground -n 50
```

## Most Likely Cause

Based on exit code 64, the most likely issue is:

**`/opt/cmesh/scripts/pushfin.sh` doesn't exist or isn't executable.**

Check and create it if needed!
