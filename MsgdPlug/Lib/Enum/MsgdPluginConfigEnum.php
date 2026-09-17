<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Plugin configs options.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.Enum
 */
enum MsgdPluginConfigEnum: string
{
    case ENABLE = 'Plugin.MsgdPlug_enabled';
    case USE_IDS = 'Plugin.MsgdPlug_use_ids';
    case DEBUG = 'Plugin.MsgdPlug_debug';
    case CONTROLLER_WHITELIST = 'Plugin.MsgdPlug_controller_whitelist';
    case USER_PERMISSIONS_WHITELIST = 'Plugin.MsgdPlug_user_permissions_whitelist';
}
