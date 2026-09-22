#!/bin/bash

# ==========================================
# CONFIGURATION
# ==========================================
MISP_PATH="/var/www/MISP"
PLUGIN_SOURCE="./MsgdPlug"
PLUGIN_DEST="${MISP_PATH}/app/Plugin/MsgdPlug"

FILES=(
    "app/Config/config.php"
    "app/Config/bootstrap.php"
    "app/Model/Server.php"
    "app/Controller/Component/ACLComponent.php"
)

# ---------------- BACKUP ----------------

backup() {
    local filepath="$1"
    if [ ! -f "$filepath" ]; then
        return
    fi
    local ts
    ts=$(date +"%Y%m%d%H%M%S")
    local backup_path="${filepath}.bak_${ts}"
    cp -p "$filepath" "$backup_path"
    echo "[BACKUP] ${backup_path}"
}

# ---------------- RESTORE (INTERACTIVE) ----------------

restore_interactive() {
    echo -e "\n=== RESTORING BACKUPS (INTERACTIVE) ===\n"
    local restored_any=false

    if [ ! -d "$PLUGIN_DEST" ]; then
        echo "[WARNING] Plugin folder not found in destination ($PLUGIN_DEST)."
        read -r -p "Do you want to reinstall/copy the plugin folder back? (y/N): " install_choice
        case "$install_choice" in
            [yY][eE][sS]|[yY])
                echo "[INFO] Copying source files..."
                copy_plugin
                ;;
            *)
                echo "[INFO] Skipping plugin folder restoration. Only config files will be restored."
                ;;
        esac
    fi

    for f in "${FILES[@]}"; do
        local path="${MISP_PATH}/${f}"

        local backups=()
        while IFS= read -r backup_file; do
            [ -n "$backup_file" ] && backups+=("$backup_file")
        done < <(find "$(dirname "$path")" -maxdepth 1 -name "$(basename "$path").bak_*" 2>/dev/null | sort -r)

        if [ ${#backups[@]} -eq 0 ]; then
            echo "[INFO] No backups found for ${f}"
            continue
        fi

        echo "--------------------------------------------------"
        echo "Available backups for: ${f}"
        echo "--------------------------------------------------"
        local idx=1
        for b in "${backups[@]}"; do
            echo "  ${idx}) $(basename "$b")"
            ((idx++))
        done
        echo "  0) Skip this file"
        echo ""

        local choice
        while true; do
            read -r -p "Select backup to restore [1-${#backups[@]}] (Default: 1 - Newest): " choice

            if [ -z "$choice" ]; then
                choice=1
            fi

            if [[ "$choice" =~ ^[0-9]+$ ]] && [ "$choice" -ge 0 ] && [ "$choice" -le "${#backups[@]}" ]; then
                break
            else
                echo "[ERROR] Invalid selection. Enter a number between 0 and ${#backups[@]}."
            fi
        done

        if [ "$choice" -eq 0 ]; then
            echo "[INFO] Skipped restoration for ${f}"
            continue
        fi

        local selected_backup="${backups[$((choice-1))]}"

        if cp -p "$selected_backup" "$path"; then
            echo "[OK] Restored ${f} <-- $(basename "$selected_backup")"
            restored_any=true
        else
            echo "[ERROR] Failed to restore ${f}"
        fi
        echo ""
    done

    if [ "$restored_any" = true ]; then
        verify
    else
        echo "[INFO] Nothing was restored."
    fi
    echo -e "\n=== DONE RESTORE ===\n"
}

# ---------------- PLUGIN COPY ----------------

copy_plugin() {
    if [ -d "$PLUGIN_SOURCE" ]; then
        cp -R "$PLUGIN_SOURCE" "$PLUGIN_DEST"
        echo "[OK] Plugin copied"
    fi
}

remove_plugin() {
    if [ -d "$PLUGIN_DEST" ]; then
        rm -rf "$PLUGIN_DEST"
        echo "[OK] Plugin removed"
    else
        echo "[INFO] Plugin not found"
    fi
}

# ---------------- BOOTSTRAP ----------------

install_bootstrap() {
    local path="${MISP_PATH}/app/Config/bootstrap.php"
    backup "$path"

    if grep -q "MsgdPlug" "$path"; then
        echo "[OK] bootstrap already set"
        return
    fi

    local block="\n # Multi Sharing Group Plugin \n if (Configure::read('Plugin.MsgdPlug_enabled')) {\n    CakePlugin::load('MsgdPlug', array('bootstrap' => true, 'routes' => true));\n}"

    echo -e "\n${block}" >> "$path"
    echo "[OK] bootstrap installed (with routes enabled)"
}

remove_bootstrap() {
    local path="${MISP_PATH}/app/Config/bootstrap.php"
    backup "$path"

    perl -0777 -i -pe "s/\n?if\s*\(\s*Configure::read\(['\"]Plugin\.MsgdPlug_enabled['\"]\)\s*\)\s*\{\s*CakePlugin::load\(['\"]MsgdPlug['\"]\s*,\s*array\(['\"]bootstrap['\"]\s*=>\s*true(?:,\s*['\"]routes['\"]\s*=>\s*true)?\)\);\s*\}//gs" "$path"
    sed -i '/MsgdPlug/d' "$path"
    echo "[OK] bootstrap removed"
}

# ---------------- CONFIG ----------------

install_config() {
    local path="${MISP_PATH}/app/Config/config.php"
    backup "$path"

    if grep -q "MsgdPlug_enabled" "$path"; then
        echo "[OK] config: MsgdPlug_enabled already set"
    else
        perl -0777 -i -pe "s/('Plugin'\s*=>\s*(?:array\s*\(|\()?)/\$1\n        'MsgdPlug_enabled' => true,/gi" "$path"
        echo "[OK] config: MsgdPlug_enabled installed"
    fi

    if grep -q "MsgdPlug_use_ids" "$path"; then
        echo "[OK] config: MsgdPlug_use_ids already set"
    else
        perl -0777 -i -pe "s/('Plugin'\s*=>\s*(?:array\s*\(|\()?)/\$1\n        'MsgdPlug_use_ids' => false,/gi" "$path"
        echo "[OK] config: MsgdPlug_use_ids installed"
    fi

    if grep -q "MsgdPlug_debug" "$path"; then
        echo "[OK] config: MsgdPlug_debug already set"
    else
        perl -0777 -i -pe "s/('Plugin'\s*=>\s*(?:array\s*\(|\()?)/\$1\n        'MsgdPlug_debug' => false,/gi" "$path"
        echo "[OK] config: MsgdPlug_debug installed"
    fi

    if grep -q "MsgdPlug_controller_whitelist" "$path"; then
        echo "[OK] config: MsgdPlug_controller_whitelist already set"
    else
        perl -0777 -i -pe "s/('Plugin'\s*=>\s*(?:array\s*\(|\()?)/\$1\n        'MsgdPlug_controller_whitelist' => '*',/gi" "$path"
        echo "[OK] config: MsgdPlug_controller_whitelist installed"
    fi

    if grep -q "MsgdPlug_user_permissions_whitelist" "$path"; then
        echo "[OK] config: MsgdPlug_user_permissions_whitelist already set"
    else
        perl -0777 -i -pe "s/('Plugin'\s*=>\s*(?:array\s*\(|\()?)/\$1\n        'MsgdPlug_user_permissions_whitelist' => 'none',/gi" "$path"
        echo "[OK] config: MsgdPlug_user_permissions_whitelist installed"
    fi
}

remove_config() {
    local path="${MISP_PATH}/app/Config/config.php"
    backup "$path"

    sed -i '/MsgdPlug_enabled/d' "$path"
    sed -i '/MsgdPlug_use_ids/d' "$path"
    sed -i '/MsgdPlug_debug/d' "$path"
    sed -i '/MsgdPlug_controller_whitelist/d' "$path"
    sed -i '/MsgdPlug_user_permissions_whitelist/d' "$path"

    echo "[OK] config removed"
}

# ---------------- ACL (Necessary for View AJAX calls) ----------------

install_acl() {
    local path="${MISP_PATH}/app/Controller/Component/ACLComponent.php"
    backup "$path"

    if grep -q "'msgdApi'" "$path"; then
        echo "[OK] ACL already set"
        return
    fi

    local patch="        'msgdApi' => array(\n            'processGroups' => array('*'),\n            'process-groups' => array('*'),\n            'getSharingGroups' => array('*'),\n            'get-sharing-groups' => array('*'),\n            'getBlueprintRulesGroups' => array('*'),\n            'get-blueprint-rules-groups' => array('*'),\n            'checkBlueprint' => array('*'),\n            'check-blueprint' => array('*'),\n            'checkUserPermission' => array('*'),\n            'check-user-permission' => array('*'),\n        ),"

    perl -i -pe "s/(const ACL_LIST\s*=\s*array\s*\()/\$1\n$patch/g" "$path"
    echo "[OK] ACL installed"
}

remove_acl() {
    local path="${MISP_PATH}/app/Controller/Component/ACLComponent.php"
    backup "$path"

    perl -0777 -i -pe "s/\s*'msgdApi'\s*=>\s*array\s*\((?:[^()]+|\([^()]*\))*\)\s*,?//gs" "$path"
    echo "[OK] ACL removed cleanly"
}

# ---------------- SERVER ----------------

install_server() {
    local path="${MISP_PATH}/app/Model/Server.php"
    backup "$path"

    if grep -q "MsgdPlug_enabled" "$path"; then
        echo "[OK] server: MsgdPlug_enabled already set"
    else
        local block_enabled="                'MsgdPlug_enabled' => array(
                    'level' => 1,
                    'description' => 'Enable or disable plugin.',
                    'value' => true,
                    'test' => 'testBool',
                    'type' => 'boolean',
                    'null' => true,
                ),"

        perl -0777 -i -pe "s#('Plugin'\s*=>\s*(?:array\s*\(|\()?[\s\n]*'branch'\s*=>\s*1\s*,)#\$1\n$block_enabled#gi" "$path"
        echo "[OK] server: MsgdPlug_enabled installed"
    fi

    if grep -q "MsgdPlug_use_ids" "$path"; then
        echo "[OK] server: MsgdPlug_use_ids already set"
    else
        local block_use_ids="                'MsgdPlug_use_ids' => array(
                    'level' => 2,
                    'description' => 'Use IDs instead of UUIDs in blueprint rules to identify sharing groups.',
                    'value' => false,
                    'test' => 'testBool',
                    'type' => 'boolean',
                    'null' => true,
                ),"

        perl -0777 -i -pe "s#('Plugin'\s*=>\s*(?:array\s*\(|\()?[\s\n]*'branch'\s*=>\s*1\s*,)#\$1\n$block_use_ids#gi" "$path"
        echo "[OK] server: MsgdPlug_use_ids installed"
    fi

    if grep -q "MsgdPlug_debug" "$path"; then
        echo "[OK] server: MsgdPlug_debug already set"
    else
        local block_debug="                'MsgdPlug_debug' => array(
                    'level' => 2,
                    'description' => 'Enable or disable debug logs.',
                    'value' => false,
                    'test' => 'testBool',
                    'type' => 'boolean',
                    'null' => true,
                ),"

        perl -0777 -i -pe "s#('Plugin'\s*=>\s*(?:array\s*\(|\()?[\s\n]*'branch'\s*=>\s*1\s*,)#\$1\n$block_debug#gi" "$path"
        echo "[OK] server: MsgdPlug_debug installed"
    fi

    if grep -q "MsgdPlug_controller_whitelist" "$path"; then
        echo "[OK] server: MsgdPlug_controller_whitelist already set"
    else
        local block_whitelist="                'MsgdPlug_controller_whitelist' => array(
                    'level' => 2,
                    'description' => 'Comma-separated list of controllers where MsgdPlug is injected (e.g. events, attributes, collections). Use * to enable globally, none to disable globally.',
                    'value' => '*',
                    'test' => 'testForEmpty',
                    'type' => 'string',
                    'null' => true,
                ),"

        perl -0777 -i -pe "s#('Plugin'\s*=>\s*(?:array\s*\(|\()?[\s\n]*'branch'\s*=>\s*1\s*,)#\$1\n$block_whitelist#gi" "$path"
        echo "[OK] server: MsgdPlug_controller_whitelist installed"
    fi

    if grep -q "MsgdPlug_user_permissions_whitelist" "$path"; then
        echo "[OK] server: MsgdPlug_user_permissions_whitelist already set"
    else
        local block_user_perms="                'MsgdPlug_user_permissions_whitelist' => array(
                    'level' => 0,
                    'description' => 'Allowlist of authorized users without [perm_sharing_group] who can generate blueprints for multiple combinations. (* = all, none = nobody, or (email_1, email_2) list of allowed users emails). Users with perm_sharing_group always have access.',
                    'value' => 'none',
                    'test' => 'testForEmpty',
                    'type' => 'string',
                    'null' => true,
                ),"

        perl -0777 -i -pe "s#('Plugin'\s*=>\s*(?:array\s*\(|\()?[\s\n]*'branch'\s*=>\s*1\s*,)#\$1\n$block_user_perms#gi" "$path"
        echo "[OK] server: MsgdPlug_user_permissions_whitelist installed"
    fi
}

remove_server() {
    local path="${MISP_PATH}/app/Model/Server.php"
    backup "$path"

    perl -0777 -i -pe "s/\s*'MsgdPlug_enabled'\s*=>\s*array\s*\([\s\S]*?'null'\s*=>\s*true\s*,?\s*\)\s*,//gs" "$path"
    perl -0777 -i -pe "s/\s*'MsgdPlug_use_ids'\s*=>\s*array\s*\([\s\S]*?'null'\s*=>\s*true\s*,?\s*\)\s*,//gs" "$path"
    perl -0777 -i -pe "s/\s*'MsgdPlug_debug'\s*=>\s*array\s*\([\s\S]*?'null'\s*=>\s*true\s*,?\s*\)\s*,//gs" "$path"
    perl -0777 -i -pe "s/\s*'MsgdPlug_controller_whitelist'\s*=>\s*array\s*\([\s\S]*?'null'\s*=>\s*true\s*,?\s*\)\s*,//gs" "$path"
    perl -0777 -i -pe "s/\s*'MsgdPlug_user_permissions_whitelist'\s*=>\s*array\s*\([\s\S]*?'null'\s*=>\s*true\s*,?\s*\)\s*,//gs" "$path"

    echo "[OK] server removed"
}

# ---------------- VERIFY ----------------

php_check() {
    local path="$1"
    if php -l "$path" >/dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

verify() {
    echo -e "\n=== VERIFYING FILES ===\n"

    for f in "${FILES[@]}"; do
        local path="${MISP_PATH}/${f}"
        if php_check "$path"; then
            echo "OK    - ${f}"
        else
            echo "ERROR - ${f} (PHP Syntax Error!)"
        fi
    done
}

# ---------------- INSTALL / REMOVE ALL ----------------

install_all() {
    echo -e "\n=== INSTALLING MSGDPLUG ===\n"
    copy_plugin
    install_config
    install_server
    install_acl
    install_bootstrap
    verify
    echo -e "\n=== DONE INSTALL ===\n"
}

remove_all() {
    echo -e "\n=== REMOVING MSGDPLUG ===\n"
    remove_plugin
    remove_config
    remove_server
    remove_bootstrap
    remove_acl
    verify
    echo -e "\n=== DONE REMOVE ===\n"
}

# ---------------- AUTO INSTALL ----------------
if [ "$1" == "--auto" ]; then
    install_all
    exit 0
fi

# ---------------- MENU ----------------

menu() {
    while true; do
        echo "========================="
        echo " MsgdPlug Installer (.sh)"
        echo "========================="
        echo "1) Install (Auto Backup)"
        echo "2) Remove"
        echo "3) Verify"
        echo "4) Restore (Select Backup)"
        echo "0) Exit"
        echo "========================="
        read -r -p "Select option: " choice

        case "$choice" in
            1) install_all ;;
            2) remove_all ;;
            3) verify ;;
            4) restore_interactive ;;
            0) echo "Bye"; exit 0 ;;
            *) echo "Invalid option!" ;;
        esac
        echo ""
    done
}

menu
