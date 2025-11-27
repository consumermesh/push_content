# KeyCDN Rsync SSH Protocol Mismatch Fix

## The Problem

The error occurs when running rsync over SSH:
```
Deploying to KeyCDN
protocol version mismatch -- is your shell clean?
(see the rsync manpage for an explanation)
rsync error: protocol incompatibility (code 2) at compat.c(622) [sender=3.4.1]
```

This happens because something in your shell startup files (`.bashrc`, `.bash_profile`, `.profile`, etc.) is outputting text that interferes with rsync's protocol.

## Root Cause

When rsync connects over SSH, it expects a clean protocol stream. If your shell outputs anything (echo statements, motd, aliases, etc.), it contaminates the rsync protocol and causes this error.

## Solutions

### Solution 1: Clean Shell on Remote Host (Recommended)

**On the remote server** (`fin.consumermesh.com`), check and clean these files:

```bash
# Check for output in shell startup files
ssh backend@fin.consumermesh.com 'grep -r "echo\|print\|printf" ~/.bashrc ~/.bash_profile ~/.profile ~/.zshrc 2>/dev/null || echo "No echo statements found"'

# Common culprits to look for:
# - MOTD (Message of the Day)
# - Custom prompts
# - Echo statements
# - Printf statements
# - Welcome messages
```

**Fix the remote shell**:

```bash
# SSH to the remote server
ssh backend@fin.consumermesh.com

# Edit the shell profile for the http user (since we use sudo -u http)
sudo -u http bash -c 'nano ~/.bashrc'

# Look for and comment out or remove any lines that output text:
# echo "Welcome to..."
# printf "System loaded in..."
# motd
# Custom PS1 with color codes that might output

# Also check system-wide files
sudo nano /etc/bash.bashrc
sudo nano /etc/profile
sudo nano /etc/motd
```

### Solution 2: Use Non-Interactive Shell (Quick Fix)

**Modify the SSH command** in your pushfin.sh to use a non-interactive shell:

```bash
# Before (in your local config/pushfin.sh):
remote_command="sudo -u http bash -xc \"/opt/cmesh/scripts/pushfin.sh -n '$name' -o '$org' -b '$bucketName' -k '$command_key' -c '$client_id' -s '$client_secret'\""

# After (use non-interactive shell):
remote_command="sudo -u http bash -c \"/opt/cmesh/scripts/pushfin.sh -n '$name' -o '$org' -b '$bucketName' -k '$command_key' -c '$client_id' -s '$client_secret'\""
```

**Alternative**: Force non-interactive mode explicitly:

```bash
# Add this to force non-interactive mode
remote_command="sudo -u http bash --norc -c \"/opt/cmesh/scripts/pushfin.sh -n '$name' -o '$org' -b '$bucketName' -k '$command_key' -c '$client_id' -s '$client_secret'\""
```

### Solution 3: Use SSH Options to Suppress Output

**Add SSH options** to suppress motd and other output:

```bash
# Update the SSH command in your pushfin.sh
ssh_command="ssh -o StrictHostKeyChecking=no -o LogLevel=ERROR -o BatchMode=yes"

# Or use this comprehensive set of options
ssh_command="ssh -o StrictHostKeyChecking=no -o LogLevel=ERROR -o BatchMode=yes -o ConnectTimeout=30"
```

### Solution 4: Check Remote pushfin.sh Script

**Ensure the remote pushfin.sh doesn't output anything** before rsync:

```bash
# SSH to remote and check
ssh backend@fin.consumermesh.com
sudo -u http nano /opt/cmesh/scripts/pushfin.sh

# Look for any echo/print statements before rsync commands
# Comment out or move them to after rsync execution
```

### Solution 5: Test with Minimal SSH Command

**Test the SSH connection directly**:

```bash
# Test basic SSH (should be silent)
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "echo 'test'" 2>/dev/null

# Test with http user (should be silent)
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http echo 'test'" 2>/dev/null

# Test the exact command structure (should be silent)
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http bash -c 'echo test'" 2>/dev/null
```

## Updated pushfin.sh Script

**Modify your config/pushfin.sh** to handle this cleanly:

```bash
#!/bin/bash

# Add this at the beginning to ensure clean output
exec 2>/dev/null  # Redirect stderr to null during connection

# Your existing code...

# Before rsync commands, ensure clean environment
unset PROMPT_COMMAND
export PS1='$ '

# For KeyCDN deployment specifically
case "$command_key" in
    "keycdn")
        echo "Deploying to KeyCDN"
        # Ensure no output before rsync
        
        # Build rsync command without any echo output
        rsync_cmd="rsync -rtvz --chmod=D2755,F644 . spfoos@rsync.keycdn.com:$BUCKET/"
        
        # Execute via SSH with clean environment
        ssh_command="ssh -o StrictHostKeyChecking=no -o LogLevel=ERROR -o BatchMode=yes"
        remote_exec="sudo -u http bash -c '$rsync_cmd'"
        
        # Execute the command
        $ssh_command -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "$remote_exec"
        ;;
esac
```

## Debugging Steps

### Step 1: Identify the Source

```bash
# Test SSH connection and see what outputs
ssh -v -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "echo 'test'" 2>&1

# Test with the exact user context
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "sudo -u http bash -c 'echo test'" 2>&1
```

### Step 2: Check System Files

```bash
# Check for problematic system files
ssh backend@fin.consumermesh.com "ls -la /etc/ssh/sshd_config"
ssh backend@fin.consumermesh.com "grep -i PrintMotd /etc/ssh/sshd_config"

# Check if motd is enabled
ssh backend@fin.consumermesh.com "ls -la /etc/motd"
```

### Step 3: Test Rsync Directly

```bash
# Test rsync directly (bypass pushfin.sh)
rsync -rtvz --dry-run /local/path/ spfoos@rsync.keycdn.com:your-push-zone/

# Test with SSH options
rsync -rtvz -e "ssh -o StrictHostKeyChecking=no -o LogLevel=ERROR" --dry-run /local/path/ spfoos@rsync.keycdn.com:your-push-zone/
```

## Quick Fix for Immediate Testing

**Update your config/pushfin.sh** with this minimal change:

```bash
# Add these SSH options to suppress output
ssh_options="-o StrictHostKeyChecking=no -o LogLevel=ERROR -o BatchMode=yes -o ConnectTimeout=30"

# Update the SSH command
command="sudo -u http bash -xc $ssh_options \"/opt/cmesh/scripts/pushfin.sh -n $name -o $org -c $client_id -s $client_secret -b $bucketName -k $command_key\" "
ssh $ssh_options -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com $command 2>&1
```

## Permanent Solution

**For production**, clean up the remote server:

1. **Disable MOTD** in SSH config
2. **Remove echo statements** from shell profiles
3. **Use non-interactive shells** for automation
4. **Test thoroughly** with the exact command structure

The key is ensuring that nothing outputs text when SSH connects, as rsync expects a clean protocol stream.