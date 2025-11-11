#!/bin/bash

# Setup script for custom commands functionality
# This script helps users migrate to the new custom commands feature

set -e
set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
echo "=== Cmesh Push Content - Custom Commands Setup ==="
echo "Script directory: $SCRIPT_DIR"
echo ""

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to create directory if it doesn't exist
create_directory() {
    local dir="$1"
    if [[ ! -d "$dir" ]]; then
        echo "Creating directory: $dir"
        mkdir -p "$dir"
    fi
}

# Function to copy and make executable
copy_executable() {
    local src="$1"
    local dest="$2"
    echo "Copying: $src -> $dest"
    cp "$src" "$dest"
    chmod +x "$dest"
}

# Check if running as root (not required but warn)
if [[ $EUID -eq 0 ]]; then
    echo "⚠️  Warning: Running as root. This is not required and may cause permission issues."
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Step 1: Create scripts directory
echo "Step 1: Creating scripts directory"
create_directory "/opt/cmesh/scripts"
echo ""

# Step 2: Copy example deployment scripts
echo "Step 2: Copying example deployment scripts"
copy_executable "$SCRIPT_DIR/config/deploy-cloudflare.sh.example" "/opt/cmesh/scripts/deploy-cloudflare.sh"
copy_executable "$SCRIPT_DIR/config/deploy-bunny.sh.example" "/opt/cmesh/scripts/deploy-bunny.sh"
copy_executable "$SCRIPT_DIR/config/deploy-aws.sh.example" "/opt/cmesh/scripts/deploy-aws.sh"
echo ""

# Step 3: Create configuration directory
echo "Step 3: Creating configuration directory"
CONFIG_DIR="sites/default/files/cmesh-config"
create_directory "$CONFIG_DIR"
echo ""

# Step 4: Copy example configuration files
echo "Step 4: Copying example configuration files"
if [[ -f "$SCRIPT_DIR/config/dev.env.inc.example" ]]; then
    copy_executable "$SCRIPT_DIR/config/dev.env.inc.example" "$CONFIG_DIR/dev.env.inc.example"
fi
if [[ -f "$SCRIPT_DIR/config/staging.env.inc.example" ]]; then
    copy_executable "$SCRIPT_DIR/config/staging.env.inc.example" "$CONFIG_DIR/staging.env.inc.example"
fi
if [[ -f "$SCRIPT_DIR/config/prod.env.inc.example" ]]; then
    copy_executable "$SCRIPT_DIR/config/prod.env.inc.example" "$CONFIG_DIR/prod.env.inc.example"
fi
echo ""

# Step 5: Set proper permissions
echo "Step 5: Setting proper permissions"
chmod 755 "/opt/cmesh/scripts"
chmod 644 "$CONFIG_DIR"/*.example 2>/dev/null || true
echo ""

# Step 6: Check for existing configuration files
echo "Step 6: Checking for existing configuration files"
EXISTING_FILES=()
for env in dev staging prod; do
    if [[ -f "$CONFIG_DIR/$env.env.inc" ]]; then
        EXISTING_FILES+=("$env.env.inc")
    fi
done

if [[ ${#EXISTING_FILES[@]} -gt 0 ]]; then
    echo "Found existing configuration files:"
    for file in "${EXISTING_FILES[@]}"; do
        echo "  - $CONFIG_DIR/$file"
    done
    echo ""
    echo "These files will continue to work with the default behavior."
    echo "To use custom commands, you'll need to update them manually."
    echo "See the example files for reference."
else
    echo "No existing configuration files found."
    echo "You can create them based on the example files."
fi
echo ""

# Step 7: Check for systemd service
echo "Step 7: Checking systemd service"
if command_exists systemctl; then
    if systemctl list-unit-files | grep -q "cmesh-build@"; then
        echo "✓ Systemd service found"
        echo ""
        echo "To use the new custom commands with systemd, make sure:"
        echo "1. The systemd wrapper script is updated: /opt/cmesh/scripts/pushfin-systemd.sh"
        echo "2. Your custom deployment scripts are in /opt/cmesh/scripts/"
        echo "3. Environment variables are configured for the systemd service"
    else
        echo "ℹ️  Systemd service not found"
        echo "If you're using PHP-FPM, you'll need to set up the systemd service."
        echo "See SYSTEMD_SETUP.md for instructions."
    fi
else
    echo "ℹ️  Systemd not available"
    echo "Using the standard command executor service."
fi
echo ""

# Step 8: Environment variables checklist
echo "Step 8: Environment Variables Checklist"
echo "Make sure to set these environment variables if using custom commands:"
echo ""
echo "For Cloudflare deployments:"
echo "  export CLOUDFLARE_API_TOKEN='your-api-token'"
echo "  export CLOUDFLARE_ZONE_ID='your-zone-id'"
echo ""
echo "For Bunny CDN deployments:"
echo "  export BUNNY_ACCESS_KEY='your-access-key'"
echo "  export BUNNY_STORAGE_ZONE='your-storage-zone'"
echo ""
echo "For AWS deployments:"
echo "  export AWS_ACCESS_KEY_ID='your-access-key'"
echo "  export AWS_SECRET_ACCESS_KEY='your-secret-key'"
echo "  export AWS_S3_BUCKET='your-bucket-name'"
echo "  export AWS_REGION='your-region'"
echo ""

# Step 9: Next steps
echo "Step 9: Next Steps"
echo "1. Review and customize the example deployment scripts in /opt/cmesh/scripts/"
echo "2. Create your .env.inc files based on the examples in $CONFIG_DIR/"
echo "3. Set up the required environment variables"
echo "4. Clear Drupal cache: drush cr"
echo "5. Test the new functionality"
echo ""
echo "For detailed instructions, see:"
echo "  - CUSTOM_COMMANDS_USAGE.md"
echo "  - CUSTOM_COMMANDS_IMPLEMENTATION.md"
echo ""

# Final message
echo "=== Setup Complete ==="
echo ""
echo "The custom commands functionality has been set up successfully!"
echo ""
echo "Key files created:"
echo "  Scripts: /opt/cmesh/scripts/"
echo "  Config examples: $CONFIG_DIR/"
echo "  Documentation: $SCRIPT_DIR/*.md"
echo ""
echo "To get started:"
echo "1. Edit your .env.inc files to add custom commands"
echo "2. Customize the deployment scripts for your needs"
echo "3. Set up environment variables"
echo "4. Test with a deployment"
echo ""
echo "Happy deploying! 🚀"