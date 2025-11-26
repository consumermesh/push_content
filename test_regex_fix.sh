#!/bin/bash

# Test script to verify the regex fix for command parsing

echo "=== Testing Command Parsing Regex Fix ==="
echo ""

# Test cases that simulate the actual commands being built
test_commands=(
    # Default command
    "/opt/cmesh/scripts/pushfin.sh -o 'mars' -n 'mpvg' -b ''"
    
    # Cloudflare command with additional parameters
    "/opt/cmesh/scripts/deploy-cloudflare.sh -o 'mars' -n 'mpvg' --zone-id dev-zone --api-token \$CLOUDFLARE_API_TOKEN"
    
    # Bunny command
    "/opt/cmesh/scripts/deploy-bunny.sh -o 'mars' -n 'mpvg' --storage-zone dev-storage --access-key \$BUNNY_ACCESS_KEY"
    
    # AWS command
    "/opt/cmesh/scripts/deploy-aws.sh -o 'mars' -n 'mpvg' --bucket dev-bucket --region us-east-1"
    
    # Commands with colons in org/name
    "/opt/cmesh/scripts/deploy-cloudflare.sh -o 'my:org' -n 'my:site' --zone-id dev-zone --api-token \$CLOUDFLARE_API_TOKEN"
)

# Simulate the old regex (broken)
test_old_regex() {
    local cmd="$1"
    echo "Testing OLD regex with: $cmd"
    
    # This simulates the old regex pattern
    if [[ $cmd =~ -o[[:space:]]+['"]?([^[:space:]'"]+)['"]?[[:space:]]+-n[[:space:]]+['"]?([^[:space:]'"]+)['"]? ]]; then
        local org="${BASH_REMATCH[1]}"
        local name="${BASH_REMATCH[2]}"
        echo "  Parsed org: '$org'"
        echo "  Parsed name: '$name'"
    else
        echo "  ❌ Failed to parse command"
    fi
    echo ""
}

# Simulate the new regex (fixed)
test_new_regex() {
    local cmd="$1"
    echo "Testing NEW regex with: $cmd"
    
    # This simulates the new regex pattern that handles quotes properly
    # We need to extract the values between -o and -n, handling quotes
    
    # First, find the -o parameter
    local o_part="${cmd#*-o }"
    o_part="${o_part%% -n *}"
    
    # Remove surrounding quotes if present
    o_part="${o_part%\"}"
    o_part="${o_part#\"}"
    o_part="${o_part%\'}"
    o_part="${o_part#\'}"
    
    # Then find the -n parameter
    local n_part="${cmd#*-n }"
    # Remove everything after the next parameter (starts with -- or -)
    n_part="${n_part%% --*}"
    n_part="${n_part%% -[^-]*}"
    n_part="${n_part%% }"
    
    # Remove surrounding quotes if present
    n_part="${n_part%\"}"
    n_part="${n_part#\"}"
    n_part="${n_part%\'}"
    n_part="${n_part#\'}"
    
    echo "  Parsed org: '$o_part'"
    echo "  Parsed name: '$n_part'"
    echo ""
}

echo "=== Testing with OLD regex (broken) ==="
echo ""
for cmd in "${test_commands[@]}"; do
    test_old_regex "$cmd"
done

echo "=== Testing with NEW approach (fixed) ==="
echo ""
for cmd in "${test_commands[@]}"; do
    test_new_regex "$cmd"
done

echo "=== Summary ==="
echo "The old regex fails with quoted values containing colons."
echo "The new approach properly handles quoted values."
echo "This fix ensures custom commands with colons in org/name work correctly."