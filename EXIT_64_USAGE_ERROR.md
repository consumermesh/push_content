# Exit Code 64 - Command Usage Error

## The Error

```
Main process exited, code=exited, status=64
```

Exit code 64 = EX_USAGE = Command line usage error

## The Problem

Your instance name was: `ramsalt-playground`

The script splits on the FIRST dash:
- ORG = `ramsalt`
- NAME = `playground`

This works for the wrapper script, but if `pushfin.sh` or the name itself has issues with parsing, you get exit 64.

## Root Cause

**Dash is ambiguous** when names can contain dashes:
- `mars-mpvg` → org=mars, name=mpvg ✅
- `ramsalt-playground` → org=ramsalt, name=playground ✅
- `mars-test-site` → org=mars, name=test-site ✅ (works with current fix)
- But it's confusing!

## Solution: Use Colon Delimiter

Change from dash (`-`) to colon (`:`) as delimiter:

**Old format:**
```bash
systemctl start cmesh-build@mars-mpvg
systemctl start cmesh-build@ramsalt-playground
```

**New format:**
```bash
systemctl start cmesh-build@mars:mpvg
systemctl start cmesh-build@ramsalt:playground
```

## Updated Files

The module now supports BOTH formats (for compatibility):

1. **Preferred:** `org:name` (colon)
2. **Fallback:** `org-name` (dash - splits on FIRST dash)

### Examples

```bash
# All of these work:
systemctl start cmesh-build@mars:mpvg
systemctl start cmesh-build@ramsalt:playground
systemctl start cmesh-build@acme:my-test-site
systemctl start cmesh-build@mars-mpvg  # fallback format
```

## Testing Your Instance

```bash
# Test the wrapper script directly
/opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground

# Should output:
# === Cmesh Build Started ===
# Instance: ramsalt:playground
# Organization: ramsalt
# Name: playground
# ...
```

## Systemd Colon Escaping

Systemd requires colons to be escaped in service names:

```bash
# Wrong (won't work)
systemctl start cmesh-build@mars:mpvg

# Correct (escape the colon)
systemctl start cmesh-build@mars\\:mpvg

# Or let the shell do it
systemctl start "cmesh-build@mars:mpvg"
```

The PHP service handles this automatically.

## Debugging Exit 64

### Step 1: Test wrapper script

```bash
# Test with your actual instance
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground

# Check the output - does it parse correctly?
```

### Step 2: Check what command is being called

```bash
# The wrapper should output:
Executing: /opt/cmesh/scripts/pushfin.sh -o 'ramsalt' -n 'playground'
```

### Step 3: Test pushfin.sh directly

```bash
# Test the actual build script
sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground

# Does it accept these arguments?
# Exit code 64 often means the script doesn't accept -o and -n flags
```

### Step 4: Check pushfin.sh usage

```bash
# Check how pushfin.sh expects arguments
cat /opt/cmesh/scripts/pushfin.sh | head -20

# Or check its help
/opt/cmesh/scripts/pushfin.sh --help
/opt/cmesh/scripts/pushfin.sh -h
```

## Common Issues

### Issue 1: pushfin.sh doesn't accept -o and -n

**Check:**
```bash
grep -E "getopts|while.*case" /opt/cmesh/scripts/pushfin.sh | head -10
```

**If pushfin.sh expects different arguments:**

Edit `/opt/cmesh/scripts/pushfin-systemd.sh` to match:

```bash
# Example: if pushfin.sh expects positional args
exec /opt/cmesh/scripts/pushfin.sh "$ORG" "$NAME"

# Or if it expects different flags
exec /opt/cmesh/scripts/pushfin.sh --org="$ORG" --name="$NAME"
```

### Issue 2: Arguments need quoting

Some scripts are picky about quotes:

```bash
# Try with quotes
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"

# Try without quotes  
exec /opt/cmesh/scripts/pushfin.sh -o $ORG -n $NAME

# Try with explicit escaping
exec /opt/cmesh/scripts/pushfin.sh -o "${ORG}" -n "${NAME}"
```

### Issue 3: Script needs different working directory

```bash
# Add cd before exec
cd /opt/cmesh/previews/ramsalt-playground || exit 1
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
```

## Updated Service Call

In the Drupal module, the service now does:

```php
// Old (dash delimiter)
$instance = "{$org}-{$name}";  // mars-mpvg

// New (colon delimiter)  
$instance = "{$org}:{$name}";  // mars:mpvg

// Escaped for systemctl
$escaped = str_replace(':', '\\:', $instance);  // mars\:mpvg
systemctl start cmesh-build@mars\\:mpvg
```

## Manual Testing

```bash
# 1. Test wrapper with colon format
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground

# 2. Check what it executes
# Should show: Executing: /opt/cmesh/scripts/pushfin.sh -o 'ramsalt' -n 'playground'

# 3. Test that command directly
sudo -u http /opt/cmesh/scripts/pushfin.sh -o ramsalt -n playground

# 4. Check exit code
echo $?
# Should be 0 for success, 64 for usage error
```

## If pushfin.sh Has Different Requirements

You might need to modify the wrapper. Here are common patterns:

### Pattern 1: Positional Arguments

```bash
# pushfin.sh expects: pushfin.sh <org> <name>
exec /opt/cmesh/scripts/pushfin.sh "$ORG" "$NAME"
```

### Pattern 2: Long Options

```bash
# pushfin.sh expects: pushfin.sh --org=X --name=Y
exec /opt/cmesh/scripts/pushfin.sh --org="$ORG" --name="$NAME"
```

### Pattern 3: Environment Variables

```bash
# pushfin.sh reads from environment
export CMESH_ORG="$ORG"
export CMESH_NAME="$NAME"
exec /opt/cmesh/scripts/pushfin.sh
```

### Pattern 4: Config File

```bash
# pushfin.sh reads from config file
cat > /tmp/build-config-$$.conf << EOF
org=$ORG
name=$NAME
EOF
exec /opt/cmesh/scripts/pushfin.sh /tmp/build-config-$$.conf
```

## Verify pushfin.sh Exists and Works

```bash
# 1. Check file exists
ls -la /opt/cmesh/scripts/pushfin.sh

# 2. Check it's executable
test -x /opt/cmesh/scripts/pushfin.sh && echo "Executable" || echo "Not executable"

# 3. Check shebang
head -1 /opt/cmesh/scripts/pushfin.sh

# 4. Check for syntax errors
bash -n /opt/cmesh/scripts/pushfin.sh

# 5. Try running it
sudo -u http /opt/cmesh/scripts/pushfin.sh -h 2>&1
```

## View Service Logs

```bash
# View what the service actually tried to do
sudo journalctl -u cmesh-build@ramsalt\\:playground -xe

# Or with dash format
sudo journalctl -u cmesh-build@ramsalt-playground -xe

# Look for the "Executing:" line to see exact command
```

## Recommendation

1. **Update wrapper script** (already done in module)
2. **Use colon format** for new instances
3. **Test directly** to see exact error
4. **Check pushfin.sh** accepts -o and -n flags

## Quick Test

```bash
# Copy updated wrapper
sudo cp config/pushfin-systemd.sh /opt/cmesh/scripts/
sudo chmod +x /opt/cmesh/scripts/pushfin-systemd.sh

# Test with your instance
sudo -u http /opt/cmesh/scripts/pushfin-systemd.sh ramsalt:playground

# Look at the output - did it work?
```

If you still get exit 64, **check what arguments pushfin.sh actually expects**!
