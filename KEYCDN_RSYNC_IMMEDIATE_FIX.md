# KeyCDN Rsync SSH Protocol Fix - Immediate Solution

## The Problem

When deploying to KeyCDN via SSH, you get:
```
protocol version mismatch -- is your shell clean?
rsync error: protocol incompatibility (code 2) at compat.c(622)
```

This happens because rsync expects a clean protocol stream, but something in the SSH shell is outputting text.

## Immediate Fix Applied

I've updated your pushfin.sh script with special handling for KeyCDN deployments:

### Changes Made:

1. **Added SSH options** to suppress output: `-o LogLevel=ERROR -o BatchMode=yes`
2. **Use non-interactive shell** for KeyCDN: `bash --norc`
3. **Clean environment** for rsync: `unset PROMPT_COMMAND && export PS1='$'`
4. **Separate execution path** for KeyCDN vs other deployments

### Updated Script Logic:

```bash
if [ "$command_key" = "keycdn" ]; then
    # Special handling for KeyCDN deployment (rsync protocol sensitive)
    remote_command="sudo -u http bash --norc -c 'export PATH=/usr/bin:/bin && unset PROMPT_COMMAND && /opt/cmesh/scripts/pushfin.sh -n \"$name\" -o \"$org\" -b \"$bucketName\" -k \"$command_key\" -c \"$client_id\" -s \"$client_secret\"'"
    
    echo "Executing KeyCDN deployment with clean environment"
    ssh $ssh_options -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "$remote_command" 2>&1
else
    # Standard deployment for other command keys
    remote_command="sudo -u http bash -xc \"/opt/cmesh/scripts/pushfin.sh -n '$name' -o '$org' -b '$bucketName' -k '$command_key' -c '$client_id' -s '$client_secret'\""
    ssh $ssh_options -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "$remote_command" 2>&1
fi
```

## What This Fixes:

1. **Shell output interference**: `--norc` prevents .bashrc from running
2. **Protocol contamination**: Clean PATH and unset PROMPT_COMMAND
3. **SSH verbosity**: `LogLevel=ERROR` and `BatchMode=yes` suppress SSH output
4. **Rsync protocol**: Clean environment for rsync to work properly

## Testing the Fix:

### Test 1: Check the updated script
```bash
# Make sure the script is updated
cat config/pushfin.sh | grep -A 10 "keycdn)"
```

### Test 2: Test KeyCDN deployment
```bash
# Try your KeyCDN deployment
php test_remote_command.php
# Look for the KeyCDN test case
```

### Test 3: Manual SSH test
```bash
# Test the SSH connection that's now being used
ssh -o StrictHostKeyChecking=no -o LogLevel=ERROR -o BatchMode=yes -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http bash --norc -c 'echo test'"
```

### Test 4: Check if it works
If you still get the error, we need to check the remote server shell configuration.

## If It Still Doesn't Work:

### Check Remote Server Shell

```bash
# SSH to the remote server
ssh backend@fin.consumermesh.com

# Check what's in the http user's shell startup
sudo -u http bash

# Check these files for any output
cat ~/.bashrc
cat ~/.profile
cat /etc/motd

# Look for anything that outputs text like:
# echo "Welcome..."
# printf "System..."
# Any color codes or formatting
```

### Clean Remote Shell (if needed)

```bash
# On remote server, clean up the http user's shell
sudo -u http bash -c 'cat > ~/.bashrc << EOF
# Minimal .bashrc for automation
export PATH=/usr/local/bin:/usr/bin:/bin
# No output or formatting
EOF'

# Also check system-wide files
sudo nano /etc/profile
# Remove any echo/printf statements
```

### Test Rsync Directly

```bash
# Test rsync manually to confirm it works
rsync -rtvz --dry-run /path/to/test/file spfoos@rsync.keycdn.com:your-push-zone/
```

## Verification

After the fix, your KeyCDN deployment should work without the protocol error. The command flow is now:

```
Drupal → CmeshPushContentService → Local pushfin.sh → SSH with clean options → Remote pushfin.sh with KeyCDN handling → Rsync to KeyCDN
```

The key difference is that KeyCDN deployments now use a clean, non-interactive shell environment that won't interfere with rsync's protocol.