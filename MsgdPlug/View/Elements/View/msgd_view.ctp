<?php

declare(strict_types=1);

/**
 * Passes view configurations to the frontend JavaScript view module.
 *
 * @var MsgdMispActionEnum|null $action
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.View.Elements.View
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

try {
    $actionInstance = isset($action) && $action instanceof MsgdMispActionEnum ? $action : null;

    if ($actionInstance === null) {
        MsgdLoggerUtility::log(
            'warning',
            '[MsgdPlug CTP: view] The $action variable is missing or invalid. View activeMode will be null.'
        );
    } else {
        $expectedViewActions = [
            MsgdMispActionEnum::VIEW,
            MsgdMispActionEnum::INDEX,
        ];

        if (!in_array($actionInstance, $expectedViewActions, true)) {
            MsgdLoggerUtility::log(
                'warning',
                sprintf(
                    '[MsgdPlug CTP: view] Unexpected action mode "%s" passed to view element. Expected VIEW or INDEX.',
                    $actionInstance->value
                )
            );
        }
    }

    $viewConfiguration = [
        'activeMode' => $actionInstance?->value,
        'modes' => [
            'VIEW' => MsgdMispActionEnum::VIEW->value,
            'INDEX' => MsgdMispActionEnum::INDEX->value,
        ],
        'messages' => [
            'CONFIG_ERROR' => __d('msgd_plug', 'Configuration error: Invalid API endpoint.'),
            'EMPTY_BLUEPRINT_GROUPS' => __d('msgd_plug', 'No blueprint sharing group members found.'),
            'NETWORK_DETAILS_ERROR' => __d('msgd_plug', 'Network error while retrieving group details.'),
        ],
    ];

    $jsonFlags = JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;

    $configJson = json_encode($viewConfiguration, $jsonFlags);

    $viewConfigurationScript =
        'window.MsgdViewConfig = Object.freeze('
        . 'Object.assign({}, window.MsgdViewConfig || {}, '
        . $configJson
        . '));';

    echo $this->Html->scriptBlock($viewConfigurationScript, ['inline' => true]);
    echo $this->Html->script(MsgdPluginFileEnum::VIEW_JS->getPath(), ['inline' => true]);
} catch (Throwable $exception) {
    MsgdLoggerUtility::logException(
        $exception,
        '[MsgdPlug CTP: view] Error rendering view JavaScript module configuration'
    );
}
