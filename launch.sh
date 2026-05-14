#!/bin/bash
# launch.sh
# Retries vopono (new IP each time) until MyCanal is reachable, then runs the export.
# Usage: ./launch.sh
#
# vopono assigns a fresh IP on every exec call, so each retry naturally rotates the IP.
# Exit code 2 from check_mycanal.php means "IP blocked, try again".
# Any other non-zero exit code (export failure, etc.) stops the loop immediately.

MAX_RETRIES=10

for i in $(seq 1 $MAX_RETRIES); do
    echo "[launch] Attempt $i / $MAX_RETRIES — starting VPN session..."

    vopono exec -i eth0 --custom "./openvpn.ovpn" \
        "./php_commands.sh"

    EXIT_CODE=$?

    if [ $EXIT_CODE -eq 0 ]; then
        echo "[launch] Export completed successfully."
        exit 0
    elif [ $EXIT_CODE -eq 2 ]; then
        echo "[launch] MyCanal blocked on attempt $i, rotating IP..."
        continue
    else
        echo "[launch] Export failed with exit code $EXIT_CODE. Aborting."
        exit $EXIT_CODE
    fi
done

# All retries exhausted — run export anyway without the MyCanal check
echo "[launch] MyCanal still blocked after $MAX_RETRIES attempts, running export anyway..."
vopono exec -i eth0 --custom "./openvpn.ovpn" "php manager.php export"
