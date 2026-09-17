<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * MISP distribution levels.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.Enum
 */
enum MsgdMispDistributionLevelEnum: string
{
    case YOUR_ORGANISATION_ONLY = '0';
    case THIS_COMMUNITY_ONLY = '1';
    case CONNECTED_COMMUNITIES = '2';
    case ALL_COMMUNITIES = '3';
    case SHARING_GROUP = '4';
    case INHERIT = '5';
}
