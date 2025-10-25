#!/bin/bash

# Systemd wrapper for pushfin.sh
# Parses the instance name and calls the actual script
# Instance format: org-name (e.g., mars-mpvg)

INSTANCE="$1"

# Split instance into org and name
# Format: org-name becomes -o org -n name
if [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
else
    echo "Error: Invalid instance format: $INSTANCE"
    echo "Expected format: org-name"
    exit 1
fi

echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Started: $(date)"
echo "NODE_OPTIONS: $NODE_OPTIONS"
echo ""

# Execute the actual build script
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
