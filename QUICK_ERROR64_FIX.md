# Quick Debug for Exit 64

## The Issue

Exit 64 = Command usage error

Your instance: `ramsalt-playground`
- Parsed as: ORG=`ramsalt`, NAME=`playground`

## Most Likely Cause

**The `/opt/cmesh/scripts/pushfin.sh` script doesn't exist or is failing.**

## Quick Tests

### 1. Test Wrapper Script Directly

```bash
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground
```

This will show you the actual error.

### 2. Check if pushfin.sh Exists

```bash
ls -la /opt/cmesh/scripts/pushfin.sh
```

**If it doesn't exist**, that's your problem! You need to create it.

### 3. View Service Logs

```bash
sudo journalctl -u cmesh-build@ramsalt-playground -n 50 --no-pager
```

Look for error messages after "=== Cmesh Build Started ==="

### 4. Check Log File

```bash
sudo cat /var/log/cmesh/build-ramsalt-playground.log
```

## If pushfin.sh Doesn't Exist

You need to create the actual build script. Here's a template:

```bash
sudo tee /opt/cmesh/scripts/pushfin.sh > /dev/null << 'EOF'
#!/usr/bin/env bash

set -e

# Parse arguments
ORG=""
NAME=""

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
    echo "Error: -o ORG and -n NAME required" >&2
    exit 64
fi

echo "Building: $ORG / $NAME"

# Your actual build commands here
# Example:
# cd "/opt/cmesh/previews/$NAME/fe"
# npm run build

echo "Build completed!"
EOF

sudo chmod +x /opt/cmesh/scripts/pushfin.sh
sudo chown http:http /opt/cmesh/scripts/pushfin.sh
```

## If pushfin.sh Exists But Fails

The script might be expecting different arguments or format. Check what it expects:

```bash
# View the script
cat /opt/cmesh/scripts/pushfin.sh | head -30

# Try running it manually
sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground
```

## Instance Name Format

If your name contains dashes (like `ramsalt-playground`), use colon delimiter:

**Instead of:**
```bash
systemctl start cmesh-build@ramsalt-playground
```

**Use:**
```bash
systemctl start cmesh-build@ramsalt:ramsalt-playground
```

This way:
- ORG = `ramsalt`
- NAME = `ramsalt-playground` (preserves the dash)

## Update Your Env Config

In your module config, update the env file name:

**From:** `config/ramsalt-playground.env.inc`

**To:** Use the service instance name in the form:

```php
<?php
$org = 'ramsalt';
$name = 'ramsalt-playground';  // Full name with dash
$script = '/opt/cmesh/scripts/pushfin.sh';
```

And call the service with colon:
```php
$instance = "{$org}:{$name}";  // ramsalt:ramsalt-playground
$service_name = "cmesh-build@{$instance}";
```

## Test Everything

```bash
# 1. Test wrapper
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground

# 2. Test main script
sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground

# 3. Test service
sudo systemctl start cmesh-build@ramsalt-playground
sudo systemctl status cmesh-build@ramsalt-playground

# 4. View logs
sudo journalctl -u cmesh-build@ramsalt-playground -f
```

## What to Check

Run these and tell me the output:

```bash
# 1. Does pushfin.sh exist?
ls -la /opt/cmesh/scripts/pushfin.sh

# 2. What does wrapper show?
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt-playground 2>&1 | head -30

# 3. What do logs say?
sudo journalctl -u cmesh-build@ramsalt-playground -n 20 --no-pager
```

The error is likely **pushfin.sh doesn't exist** or **pushfin.sh is rejecting the arguments**.
