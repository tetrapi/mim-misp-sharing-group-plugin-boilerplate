<?php

declare(strict_types=1);

/**
 * Status types.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.Enum
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */
enum MsgdPluginStatusEnum: string
{
    case SUCCESS = 'success';
    case ERROR = 'error';
    case INFO = 'info';
    case WARNING = 'warning';
}
