#!/bin/bash

if [ "$EUID" -eq 0 ]; then
    echo "This script should not be run as root. Please run it as a regular user."
    exit 1
fi

function show_help() {
    echo "Usage: $0 -n <name> -o <org> [-b <bucket>] [-k <command_key>] [-c <client_id>] [-s <client_secret>] [-h]"
    echo
    echo "Options:"
    echo "  -n <name>        Specify the name"
    echo "  -o <org>         Specify the organization"
    echo "  -b <bucket>      Specify the bucket (optional)"
    echo "  -k <command_key> Specify the command key (default, cloudflare, bunny, aws, keycdn)"
    echo "  -c <client_id>   Specify CMESH_CLIENT_ID for build"
    echo "  -s <client_secret> Specify CMESH_CLIENT_SECRET for build"
    echo "  -h, --help       Show this help message and exit"
}

# Initialize variables
name=""
org=""
client_id=""
client_secret=""
bucket=""
command_key="default"
# Parse command-line options
while getopts ":n:o:b:k:c:s:h" opt; do
    case ${opt} in
        n )
            name="$OPTARG"
            ;;
        o )
            org="$OPTARG"
            ;;
        c )
            client_id="$OPTARG"
            ;;
        s )
            client_secret="$OPTARG"
            ;;
	b )
	    bucket="$OPTARG"
	    ;;
        h )
            show_help
            exit 0
            ;;
        \? )
            echo "Invalid option: -$OPTARG" 1>&2
            show_help
            exit 1
            ;;
        : )
            echo "Option -$OPTARG requires an argument." 1>&2
            show_help
            exit 1
            ;;
    esac
done
shift $((OPTIND -1))

# Check if all required options are provided
if [ -z "$name" ] || [ -z "$org" ]; then
    echo "Error: All required options (-n, -o) must be specified."
    show_help
    exit 1
fi

echo "args: name=$name, org=$org, bucket=$bucket, command_key=$command_key"

# Handle different command keys for remote execution
case "$command_key" in
    "default")
        echo "Executing default deployment"
        ;;
    "cloudflare")
        echo "Executing Cloudflare deployment"
        # Set Cloudflare-specific environment variables if needed
        export CLOUDFLARE_ZONE_ID="${CLOUDFLARE_ZONE_ID:-}"
        export CLOUDFLARE_API_TOKEN="${CLOUDFLARE_API_TOKEN:-}"
        ;;
    "bunny")
        echo "Executing Bunny CDN deployment"
        # Set Bunny-specific environment variables if needed
        export BUNNY_STORAGE_ZONE="${BUNNY_STORAGE_ZONE:-}"
        export BUNNY_ACCESS_KEY="${BUNNY_ACCESS_KEY:-}"
        ;;
    "aws")
        echo "Executing AWS deployment"
        # Set AWS-specific environment variables if needed
        export AWS_S3_BUCKET="${AWS_S3_BUCKET:-$bucket}"
        export AWS_REGION="${AWS_REGION:-us-east-1}"
        ;;
    "keycdn")
        echo "Executing KeyCDN deployment"
        # Set KeyCDN-specific environment variables if needed
        export KEYCDN_PUSH_ZONE="${KEYCDN_PUSH_ZONE:-$bucket}"
        ;;
    *)
        echo "Unknown command key: $command_key" >&2
        echo "Available command keys: default, cloudflare, bunny, aws, keycdn" >&2
        exit 1
        ;;
esac

set -e

# Set bucket name for deployment
bucketName=""
if [[ -z $bucket ]]; then
    echo "no bucket specified, using default"
    bucketName=$org.$name-app.consumermesh.site
else
    echo "bucket specified: $bucket"
    bucketName=$bucket
fi

# Build the remote command with all parameters including command_key
remote_command="sudo -u http bash -xc \"/opt/cmesh/scripts/pushfin.sh -n '$name' -o '$org' -b '$bucketName' -k '$command_key' -c '$client_id' -s '$client_secret'\""

echo "Executing remote command: $remote_command"
ssh -o StrictHostKeyChecking=no -i /opt/cmesh/scripts/.ssh/id_rsa backend@fin.consumermesh.com "$remote_command" 2>&1
echo "Remote execution completed!"
