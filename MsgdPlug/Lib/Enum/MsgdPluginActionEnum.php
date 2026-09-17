<?php

declare(strict_types=1);

/**
 * Supported plugin actions.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.Enum
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */
enum MsgdPluginActionEnum: string
{
    case PROCESS_GROUPS = 'processGroups';
    case GET_SHARING_GROUPS = 'getSharingGroups';
    case CHECK_BLUEPRINT = 'checkBlueprint';
    case GET_BLUEPRINT_RULES_GROUPS = 'getBlueprintRulesGroups';
    case CHECK_USER_PERMISSION = 'checkUserPermission';

    /**
     * Generates the route payload key used for frontend URL passing.
     *
     * @return string
     */
    public function getRouteKey(): string
    {
        return $this->value . 'Url';
    }
}
