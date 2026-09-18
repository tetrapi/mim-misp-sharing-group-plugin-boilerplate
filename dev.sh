#!/usr/bin/env bash
# MsgdPlug Environment Management Script

set -e

COMPOSE_FILE="docker-compose-dev.yml"
CONTAINER_NAME="misp-core"
INSTALLER_PATH="/var/www/MISP/msgd_installer.sh"
ENV_FILE=".env"
EXAMPLE_ENV_FILE="example.env"

DOCKER_COMPOSE=(docker compose -f "$COMPOSE_FILE")

ensure_env_file() {
    if [ ! -f "$ENV_FILE" ]; then
        if [ -f "$EXAMPLE_ENV_FILE" ]; then
            echo "--> '$ENV_FILE' not found. Creating '$ENV_FILE' from '$EXAMPLE_ENV_FILE'..."
            cp "$EXAMPLE_ENV_FILE" "$ENV_FILE"
        else
            echo "--> [WARNING] Neither '$ENV_FILE' nor '$EXAMPLE_ENV_FILE' was found. Proceeding with defaults."
        fi
    fi
}

load_env_vars() {
    ensure_env_file

    if [ -f "$ENV_FILE" ]; then
        eval "$(grep -v '^#' "$ENV_FILE" | grep -E '.=' | xargs)" 2>/dev/null || true
    fi

    MISP_URL="${BASE_URL:-https://localhost}"
    MISP_EMAIL="${ADMIN_EMAIL:-admin@admin.test}"
    MISP_PASS="${ADMIN_PASSWORD:-admin}"
}

sync_stubs() {
    echo "--> Copying MISP core files for IDE autocomplete..."
    mkdir -p .misp-stubs

    CONTAINER_ID=$("${DOCKER_COMPOSE[@]}" ps -q "$CONTAINER_NAME")

    if [ -z "$CONTAINER_ID" ]; then
        echo "[ERROR] Container $CONTAINER_NAME is not running!"
        return 1
    fi

    docker cp "$CONTAINER_ID:/var/www/MISP/app/Lib/cakephp/lib/Cake" .misp-stubs/ 2>/dev/null || true
    docker cp "$CONTAINER_ID:/var/www/MISP/app/Controller" .misp-stubs/ 2>/dev/null || true
    docker cp "$CONTAINER_ID:/var/www/MISP/app/Model" .misp-stubs/ 2>/dev/null || true
    docker cp "$CONTAINER_ID:/var/www/MISP/app/Lib" .misp-stubs/ 2>/dev/null || true
    docker cp "$CONTAINER_ID:/var/www/MISP/app/Vendor" .misp-stubs/ 2>/dev/null || true
    docker cp "$CONTAINER_ID:/var/www/MISP/app/Test" .misp-stubs/ 2>/dev/null || true

    echo "--> Stubs copied to .misp-stubs/"
}

wait_for_healthy() {
    CONTAINER_ID=$("${DOCKER_COMPOSE[@]}" ps -q "$CONTAINER_NAME")

    if [ -z "$CONTAINER_ID" ]; then
        echo "[ERROR] Container $CONTAINER_NAME was not found!"
        exit 1
    fi

    while true; do
        STATUS=$(docker inspect --format "{{if .State.Health}}{{.State.Health.Status}}{{else}}missing{{end}}" "$CONTAINER_ID")

        if [ "$STATUS" == "healthy" ]; then
            echo "--> Container $CONTAINER_NAME is healthy!"
            break
        elif [ "$STATUS" == "missing" ]; then
            echo "--> [WARNING] No health check defined in docker-compose. Waiting 15 seconds..."
            sleep 15
            break
        elif [ "$STATUS" == "unhealthy" ]; then
            echo "--> [ERROR] Container $CONTAINER_NAME reported unhealthy status."
            exit 1
        fi

        echo "--> Waiting for $CONTAINER_NAME to become healthy..."
        sleep 5
    done
}

show_menu() {
    echo "=========================================="
    echo "       MsgdPlug Development CLI           "
    echo "=========================================="
    echo " 1) Deploy & Run Installer"
    echo " 2) Run All Unit Tests"
    echo " 3) Sync IDE Stubs"
    echo " 4) Stop Docker Containers"
    echo " 5) Purge Docker Data (Clean Reset)"
    echo " 0) Exit"
    echo "=========================================="
}

deploy_environment() {
    ensure_env_file

    echo "--> Starting Docker Containers..."
    "${DOCKER_COMPOSE[@]}" up -d

    wait_for_healthy

    echo "--> Executing Installer..."
    "${DOCKER_COMPOSE[@]}" exec "$CONTAINER_NAME" bash "$INSTALLER_PATH" --auto

    sync_stubs
    load_env_vars

    echo ""
    echo "--> Environment ready!"
    echo "    URL:      $MISP_URL"
    echo "    Email:    $MISP_EMAIL"
    echo "    Password: $MISP_PASS"
    exit 0
}

run_all_tests() {
    echo "--> Executing all tests for MsgdPlug..."
    "${DOCKER_COMPOSE[@]}" exec "$CONTAINER_NAME" /var/www/MISP/app/Vendor/bin/phpunit \
        --bootstrap /var/www/MISP/app/Lib/cakephp/lib/Cake/Test/bootstrap.php \
        /var/www/MISP/app/Plugin/MsgdPlug/Test/Case/ || true
    exit 0
}

stop_docker() {
    echo "--> Stopping Docker containers..."
    "${DOCKER_COMPOSE[@]}" stop
    echo "--> Containers stopped."
}

purge_docker() {
    read -r -p "WARNING: This will delete all containers, database volumes, and stubs. Continue? [y/N]: " confirm
    if [[ "$confirm" =~ ^[Yy]$ ]]; then
        echo "--> Purging environment..."
        sudo "${DOCKER_COMPOSE[@]}" down -v --remove-orphans
        sudo rm -rf .misp-stubs configs files gnupg logs ssl custom
        echo "--> Data purged successfully."
    else
        echo "--> Purge cancelled."
    fi
}

# Main Loop
while true; do
    show_menu
    read -r -p "Select an option [0-5]: " choice
    case $choice in
        1) deploy_environment ;;
        2) run_all_tests ;;
        3) sync_stubs ;;
        4) stop_docker ;;
        5) purge_docker ;;
        0) echo "Exiting..."; exit 0 ;;
        *) echo "Invalid option. Please select a valid number." ;;
    esac
    echo ""
done
