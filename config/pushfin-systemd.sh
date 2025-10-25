#!/usr/bin/env bash

# Systemd wrapper for pushfin.sh
# Parses the instance name and calls the actual script
# Instance format: org-name (e.g., mars-mpvg)

set -e  # Exit on error
set -u  # Exit on undefined variable

INSTANCE="${1:-}"

if [[ -z "$INSTANCE" ]]; then
    echo "Error: No instance provided" >&2
    exit 1
fi

# Split instance into org and name
# Format: org-name becomes -o org -n name
if [[ "$INSTANCE" =~ ^([^-]+)-(.+)$ ]]; then
    ORG="${BASH_REMATCH[1]}"
    NAME="${BASH_REMATCH[2]}"
else
    echo "Error: Invalid instance format: $INSTANCE" >&2
    echo "Expected format: org-name (e.g., mars-mpvg)" >&2
    exit 1
fi

echo "=== Cmesh Build Started ==="
echo "Instance: $INSTANCE"
echo "Organization: $ORG"
echo "Name: $NAME"
echo "Started: $(date '+%Y-%m-%d %H:%M:%S')"
echo "User: $(whoami)"
echo "Working Directory: $(pwd)"
echo "NODE_OPTIONS: ${NODE_OPTIONS:-not set}"
echo ""

# Verify pushfin.sh exists
if [[ ! -f "/opt/cmesh/scripts/pushfin.sh" ]]; then
    echo "Error: /opt/cmesh/scripts/pushfin.sh not found" >&2
    exit 1
fi

if [[ ! -x "/opt/cmesh/scripts/pushfin.sh" ]]; then
    echo "Error: /opt/cmesh/scripts/pushfin.sh is not executable" >&2
    exit 1
fi

# Execute the actual build script
echo "Executing: /opt/cmesh/scripts/pushfin.sh -o '$ORG' -n '$NAME'"
exec /opt/cmesh/scripts/pushfin.sh -o "$ORG" -n "$NAME"
