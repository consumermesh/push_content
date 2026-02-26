#!/bin/bash

# KeyCDN deployment script with rsync protocol fixes
# This script handles the SSH/rsync protocol mismatch issue
# Usage: deploy-keycdn.sh -o <org> -n <name> --bucket <bucket>

set -e  # Exit on error
set -u  # Exit on undefined variable

# Initialize variables
ORG=""
NAME=""
BUCKET=""

# Parse command line arguments
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
    --bucket)
      BUCKET="$2"
      shift 2
      ;;
    -h|--help)
      echo "Usage: $0 -o <org> -n <name> --bucket <bucket>"
      echo "Deploy to KeyCDN push zone via rsync"
      echo ""
      echo "Options:"
      echo "  -o <org>         Organization name"
      echo "  -n <name>        Site name" 
      echo "  --bucket <bucket> KeyCDN push zone/bucket name"
      echo "  -h, --help       Show this help message"
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

# Validate required parameters
if [[ -z "$ORG" ]] || [[ -z "$NAME" ]] || [[ -z "$BUCKET" ]]; then
  echo "Error: Missing required parameters"
  echo "Usage: $0 -o <org> -n <name> --bucket <bucket>"
  exit 1
fi

# Build paths
BUILD_DIR="/opt/cmesh/previews/$ORG-$NAME/fe/build"

# Check if build directory exists
if [[ ! -d "$BUILD_DIR" ]]; then
  echo "Error: Build directory not found: $BUILD_DIR"
  exit 1
fi

echo "Build directory: $BUILD_DIR"
echo "KeyCDN Push Zone: $BUCKET"
echo ""

# CRITICAL: For KeyCDN deployment, ensure clean environment for rsync
# This prevents the "protocol version mismatch" error
echo "Preparing clean environment for KeyCDN rsync deployment..."

# 1. Ensure clean shell environment (no output that interferes with rsync)
unset PROMPT_COMMAND
export PS1='$ '

# 2. Set up clean PATH
export PATH=/usr/bin:/bin:/usr/local/bin

# 3. Change to build directory
cd "$BUILD_DIR"

# 4. Deploy to KeyCDN push zone via rsync with clean protocol
echo "Deploying to KeyCDN via rsync..."
echo "Source: $BUILD_DIR"
echo "Destination: spfoos@rsync.keycdn.com:$BUCKET/"
echo ""

# CRITICAL: Use rsync with options that work cleanly over SSH
# The key is ensuring no shell output contaminates the rsync protocol
rsync -rtvz --chmod=D2755,F644 \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='.env' \
  . \
  spfoos@rsync.keycdn.com:"$BUCKET/"

rsync_result=$?

if [ $rsync_result -eq 0 ]; then
    echo ""
    echo "✓ Successfully deployed to KeyCDN push zone: $BUCKET"
    echo "✓ Rsync completed cleanly without protocol errors"
else
    echo ""
    echo "✗ KeyCDN deployment failed with exit code: $rsync_result"
    echo "This might be due to:"
    echo "  - SSH key authentication issues"
    echo "  - KeyCDN push zone not configured"
    echo "  - Network connectivity problems"
    echo "  - Shell environment contamination"
    exit $rsync_result
fi

echo ""
echo "=== KeyCDN Deployment Completed Successfully ==="

# Return success
exit 0