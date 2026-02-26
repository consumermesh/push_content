#!/usr/bin/env bash

# Systemd wrapper for pushfin.sh and custom deployment scripts
# Parses the instance name and calls the appropriate script
# Instance format: org:name:command (e.g., mars:mpvg:cloudflare or ramsalt:playbunny:default)
# Note: Using colon as delimiter since names can contain dashes

set -e  # Exit on error
set -u  # Exit on undefined variable

INSTANCE="${1:-}"

if [[ -z "$INSTANCE" ]]; then
    echo "Error: No instance provided" >&2
    exit 1
fi

# Split instance into components using colon delimiter
# Format: org:name:command[:bucket] becomes -o org -n name with custom command and optional bucket
# Note: org, name, and bucket may have URL-encoded colons (%3A)
if [[ "$INSTANCE" =~ ^([^:]+):([^:]+):([^:]+):(.+)$ ]]; then
    # Format: org:name:command:bucket (with bucket)
    ORG_ENCODED="${BASH_REMATCH[1]}"
    NAME_ENCODED="${BASH_REMATCH[2]}"
    COMMAND_KEY="${BASH_REMATCH[3]}"
    BUCKET_ENCODED="${BASH_REMATCH[4]}"
    
    # Decode URL-encoded colons
    ORG="${ORG_ENCODED//%3A/:}"
    NAME="${NAME_ENCODED//%3A/:}"
    BUCKET="${BUCKET_ENCODED//%3A/:}"
elif [[ "$INSTANCE" =~ ^([^:]+):([^:]+):(.+)$ ]]; then
    # Format: org:name:command (without bucket)
    ORG_ENCODED="${BASH_REMATCH[1]}"
    NAME_ENCODED="${BASH_REMATCH[2]}"
    COMMAND_KEY="${BASH_REMATCH[3]}"
    
    # Decode URL-encoded colons
    ORG="${ORG_ENCODED//%3A/:}"
    NAME="${NAME_ENCODED//%3A/:}"
    BUCKET=""
elif [[ "$INSTANCE" =~ ^([^:]+):([^:]+)$ ]]; then
    # Legacy format: org:name (backward compatibility)
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
    COMMAND_KEY="default"
    BUCKET=""
else
    # Fallback: try dash delimiter (split on FIRST dash only)
    if [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
        ORG="${BASH_REMATCH[1]}"
        NAME="${BASH_REMATCH[2]}"
        COMMAND_KEY="default"
        BUCKET=""
    else
        echo "Error: Invalid instance format: $INSTANCE" >&2
        echo "Expected format: org:name:command[:bucket] or org-name" >&2
        echo "Examples: mars:mpvg:cloudflare, mars:mpvg:aws:my-bucket, mars-test" >&2
        exit 64  # EX_USAGE
    fi
fi

echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Command Key: $COMMAND_KEY"
if [[ -n "$BUCKET" ]]; then
    echo "Bucket: $BUCKET"
fi
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "User: $(whoami)"
echo "Working Directory: $(pwd)"
echo "NODE_OPTIONS: ${NODE_OPTIONS:-not set}"
echo "Script received parameters: ORG='$ORG' NAME='$NAME' COMMAND_KEY='$COMMAND_KEY'"
echo ""

# Function to execute a command and handle errors
execute_command() {
    local cmd="$1"
    local description="$2"
    
    echo "=== Executing: $description ==="
    echo "Command: $cmd"
    echo ""
    
    # Execute the command
    if eval "$cmd"; then
        echo ""
        echo "=== Successfully completed: $description ==="
        return 0
    else
        local exit_code=$?
        echo ""
        echo "=== Failed: $description (exit code: $exit_code) ==="
        return $exit_code
    fi
}

# Determine which script to execute based on command key
echo "Selecting command based on COMMAND_KEY='$COMMAND_KEY'"
case "$COMMAND_KEY" in
    "default")
        # Default pushfin.sh command
        echo "Executing default pushfin.sh command"
        if [[ ! -f "/opt/cmesh/scripts/pushfin.sh" ]]; then
            echo "Error: /opt/cmesh/scripts/pushfin.sh not found" >&2
            exit 1
        fi
        
        if [[ ! -x "/opt/cmesh/scripts/pushfin.sh" ]]; then
            echo "Error: /opt/cmesh/scripts/pushfin.sh is not executable" >&2
            exit 1
        fi
        
        execute_command "/opt/cmesh/scripts/pushfin.sh -o '$ORG' -n '$NAME'" "Default pushfin.sh"
        ;;
    
    "cloudflare")
        # Cloudflare deployment
        echo "Executing Cloudflare deployment command"
        if [[ ! -f "/opt/cmesh/scripts/deploy-cloudflare.sh" ]]; then
            echo "Error: /opt/cmesh/scripts/deploy-cloudflare.sh not found" >&2
            exit 1
        fi
        
        if [[ -z "${CLOUDFLARE_API_TOKEN:-}" ]]; then
            echo "Error: CLOUDFLARE_API_TOKEN environment variable not set" >&2
            exit 1
        fi
        
        execute_command "/opt/cmesh/scripts/deploy-cloudflare.sh -o '$ORG' -n '$NAME' --zone-id \$CLOUDFLARE_ZONE_ID --api-token \$CLOUDFLARE_API_TOKEN" "Cloudflare deployment"
        ;;
    
    "bunny")
        # Bunny CDN deployment
        echo "Executing Bunny CDN deployment command"
        if [[ ! -f "/opt/cmesh/scripts/deploy-bunny.sh" ]]; then
            echo "Error: /opt/cmesh/scripts/deploy-bunny.sh not found" >&2
            exit 1
        fi
        
        if [[ -z "${BUNNY_ACCESS_KEY:-}" ]]; then
            echo "Error: BUNNY_ACCESS_KEY environment variable not set" >&2
            exit 1
        fi
        
        execute_command "/opt/cmesh/scripts/deploy-bunny.sh -o '$ORG' -n '$NAME' --storage-zone \$BUNNY_STORAGE_ZONE --access-key \$BUNNY_ACCESS_KEY" "Bunny CDN deployment"
        ;;
    
    "aws")
        # AWS deployment
        echo "Executing AWS deployment command"
        if [[ ! -f "/opt/cmesh/scripts/deploy-aws.sh" ]]; then
            echo "Error: /opt/cmesh/scripts/deploy-aws.sh not found" >&2
            exit 1
        fi
        
        if [[ -z "${AWS_ACCESS_KEY_ID:-}" ]] || [[ -z "${AWS_SECRET_ACCESS_KEY:-}" ]]; then
            echo "Error: AWS credentials not set (AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY)" >&2
            exit 1
        fi
        
        # Use configured bucket if provided, otherwise use environment variable
        if [[ -n "$BUCKET" ]]; then
            execute_command "/opt/cmesh/scripts/deploy-aws.sh -o '$ORG' -n '$NAME' --bucket '$BUCKET' --region \$AWS_REGION" "AWS deployment"
        else
            execute_command "/opt/cmesh/scripts/deploy-aws.sh -o '$ORG' -n '$NAME' --bucket \$AWS_S3_BUCKET --region \$AWS_REGION" "AWS deployment"
        fi
        ;;
    
    "keycdn")
        # KeyCDN deployment via rsync
        echo "Executing KeyCDN deployment command"
        if [[ ! -f "/opt/cmesh/scripts/deploy-keycdn.sh" ]]; then
            echo "Error: /opt/cmesh/scripts/deploy-keycdn.sh not found" >&2
            exit 1
        fi
        
        # Use configured bucket (KeyCDN push zone) if provided, otherwise error
        if [[ -n "$BUCKET" ]]; then
            execute_command "/opt/cmesh/scripts/deploy-keycdn.sh -o '$ORG' -n '$NAME' --bucket '$BUCKET'" "KeyCDN deployment"
        else
            echo "Error: KeyCDN deployment requires a bucket (push zone) to be configured" >&2
            echo "Please set \$bucket in your .env.inc file" >&2
            exit 1
        fi
        ;;
    
    *)
        # Generic custom command - try to find a script with the command name
        echo "Executing custom command: $COMMAND_KEY"
        CUSTOM_SCRIPT="/opt/cmesh/scripts/deploy-$COMMAND_KEY.sh"
        if [[ -f "$CUSTOM_SCRIPT" ]]; then
            if [[ ! -x "$CUSTOM_SCRIPT" ]]; then
                echo "Error: $CUSTOM_SCRIPT is not executable" >&2
                exit 1
            fi
            execute_command "$CUSTOM_SCRIPT -o '$ORG' -n '$NAME'" "Custom deployment: $COMMAND_KEY"
        else
            echo "Error: Unknown command key: $COMMAND_KEY" >&2
            echo "Available commands: default, cloudflare, bunny, aws, or custom script deploy-{command}.sh" >&2
            exit 64  # EX_USAGE
        fi
        ;;
esac

echo ""
echo "=== Cmesh Build Completed ==="
echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Total duration: $SECONDS seconds"