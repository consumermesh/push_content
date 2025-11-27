# KeyCDN Remote Script Fix - Rsync Protocol Issue

## The Issue Explained

You fixed the **local** pushfin.sh script, but the **remote** `deploy-keycdn.sh` script on the server (`fin.consumermesh.com`) still has the rsync protocol issue.

### The Flow:

```
Drupal → CmeshPushContentService → Local pushfin.sh → SSH Remote → Remote pushfin.sh → Remote deploy-keycdn.sh → Rsync to KeyCDN
                                             ↑                                ↑
                                         Your fix works here              Issue is here!
```

When you select "Push to Prod (KeyCDN)" in your Drupal form:

1. **Local pushfin.sh** (✅ Fixed) - Uses clean SSH options
2. **Remote pushfin.sh** (✅ Fixed) - Calls deploy-keycdn.sh with clean environment  
3. **Remote deploy-keycdn.sh** (❌ Problem) - Still has rsync protocol issues

## The Root Cause

The remote `deploy-keycdn.sh` script on `fin.consumermesh.com` doesn't have the same rsync protocol fixes we applied to your local scripts. It still outputs text or doesn't use a clean shell environment for rsync.

## Solution Options

### Option 1: Update Remote Script (Recommended)

**SSH to the remote server and update the deploy-keycdn.sh script:**

```bash
# SSH to remote server
ssh backend@fin.consumermesh.com

# Edit the KeyCDN deployment script
sudo -u http nano /opt/cmesh/scripts/deploy-keycdn.sh
```

**Add these fixes to the remote script:**

```bash
#!/bin/bash

# Add at the beginning - ensure clean environment for rsync
unset PROMPT_COMMAND
export PS1='$ '
export PATH=/usr/bin:/bin:/usr/local/bin

# For KeyCDN deployment specifically
echo "Deploying to KeyCDN"
cd "$BUILD_DIR"

# Use rsync with clean environment
rsync -rtvz --chmod=D2755,F644 \
  --exclude='.git' \
  --exclude='node_modules' \
  . \
  spfoos@rsync.keycdn.com:"$BUCKET/"
```

### Option 2: Create Clean Remote Script

**Create a new clean KeyCDN deployment script on the remote server:**

```bash
# SSH to remote server
ssh backend@fin.consumermesh.com

# Create new clean script
sudo -u http nano /opt/cmesh/scripts/deploy-keycdn-clean.sh

# Copy the clean script content from our deploy-keycdn-clean.sh file
# Make it executable
sudo -u http chmod +x /opt/cmesh/scripts/deploy-keycdn-clean.sh

# Update pushfin-systemd.sh to use the clean script
sudo nano /opt/cmesh/scripts/pushfin-systemd.sh
# Change line 182 from:
# execute_command "/opt/cmesh/scripts/deploy-keycdn.sh -o '$ORG' -n '$NAME' --bucket '$BUCKET'" "KeyCDN deployment"
# To:
# execute_command "/opt/cmesh/scripts/deploy-keycdn-clean.sh -o '$ORG' -n '$NAME' --bucket '$BUCKET'" "KeyCDN deployment"
```

### Option 3: Modify Remote pushfin.sh

**Update the remote pushfin.sh to handle KeyCDN specially:**

```bash
# SSH to remote server
ssh backend@fin.consumermesh.com
sudo nano /opt/cmesh/scripts/pushfin.sh

# Add KeyCDN handling with clean environment:
case "$command_key" in
    "keycdn")
        echo "Executing KeyCDN deployment"
        # Ensure clean environment for rsync
        unset PROMPT_COMMAND
        export PS1='$ '
        export PATH=/usr/bin:/bin:/usr/local/bin
        
        # Build and execute rsync with clean environment
        rsync -rtvz --chmod=D2755,F644 . spfoos@rsync.keycdn.com:$BUCKET/
        ;;
esac
```

## The Clean KeyCDN Script

Here's what the remote `deploy-keycdn.sh` should look like:

```bash
#!/bin/bash

set -e
set -u

# Parse arguments (same as before)
while [[ $# -gt 0 ]]; do
  case $1 in
    -o) ORG="$2"; shift 2 ;;
    -n) NAME="$2"; shift 2 ;;
    --bucket) BUCKET="$2"; shift 2 ;;
    *) echo "Unknown option: $1"; exit 1 ;;
  esac
done

# CRITICAL: Clean environment for rsync
unset PROMPT_COMMAND
export PS1='$ '
export PATH=/usr/bin:/bin:/usr/local/bin

# Build paths
BUILD_DIR="/opt/cmesh/previews/$ORG-$NAME/fe/build"

# Check build directory
if [[ ! -d "$BUILD_DIR" ]]; then
  echo "Error: Build directory not found: $BUILD_DIR"
  exit 1
fi

cd "$BUILD_DIR"

# Deploy with clean rsync
echo "Deploying to KeyCDN push zone: $BUCKET"
rsync -rtvz --chmod=D2755,F644 . spfoos@rsync.keycdn.com:"$BUCKET/"

echo "✓ Successfully deployed to KeyCDN push zone: $BUCKET"
exit 0
```

## Immediate Fix Steps

### Step 1: Identify the Current Remote Script
```bash
# Check what the current remote script looks like
ssh backend@fin.consumermesh.com "cat /opt/cmesh/scripts/deploy-keycdn.sh"
```

### Step 2: Apply the Fix
Choose one of the options above and implement it on the remote server.

### Step 3: Test
```bash
# Test the fix manually
ssh backend@fin.consumermesh.com "sudo -u http /opt/cmesh/scripts/deploy-keycdn.sh -o 'test' -n 'test' --bucket 'test-zone'"
```

### Step 4: Verify Through Drupal
Use your Drupal form to trigger a KeyCDN deployment and confirm it works without the protocol error.

## Verification

After applying the fix, your KeyCDN deployment should work like this:

```
Drupal → CmeshPushContentService → Local pushfin.sh (✅ clean) → SSH (✅ clean) → Remote pushfin.sh (✅ clean) → Remote deploy-keycdn.sh (✅ clean) → Rsync to KeyCDN (✅ works!)
```

The key is ensuring **every step** in the chain uses a clean shell environment for rsync.