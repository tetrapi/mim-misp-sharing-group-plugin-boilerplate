<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Plugin utility file paths.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.Enum
 */
enum MsgdPluginFileEnum: string
{
    case MSGD_INJECTOR_PHP = 'MsgdInjector';
    case STYLE_CSS = 'msgd_style';
    case VIEW_JS = 'msgd_view';
    case FORM_JS = 'msgd_form';
    case FORM_UI_JS = 'msgd_form_ui';
    case UTILS_JS = 'msgd_utils';
    case TEMPLATES_CTP = 'Common/msgd_templates';
    case UTILS_CTP = 'Common/msgd_utils';
    case FORM_CTP = 'Form/msgd_form';
    case VIEW_CTP = 'View/msgd_view';

    /**
     * Returns the full CakePHP file path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return 'MsgdPlug.' . $this->value;
    }
}
