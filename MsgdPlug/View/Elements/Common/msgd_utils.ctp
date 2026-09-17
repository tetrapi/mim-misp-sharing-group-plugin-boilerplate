<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

App::uses('Configure', 'Core');

/**
 * Passes backend settings and route URLs to the JavaScript runtime.
 *
 * @var array<string, string>|null $urls
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.View.Elements.Common
 */

try {
    /** @var array<string, string> $urlsData */
    $urlsData = isset($urls) && is_array($urls) ? $urls : [];

    if ($urlsData === []) {
        MsgdLoggerUtility::log(
            'warning',
            '[MsgdPlug CTP: utils] The $urls array is missing or empty. Frontend API calls may fail.'
        );
    } else {
        $expectedRouteKeys = [
            MsgdPluginActionEnum::CHECK_USER_PERMISSION->getRouteKey(),
            MsgdPluginActionEnum::PROCESS_GROUPS->getRouteKey(),
            MsgdPluginActionEnum::GET_SHARING_GROUPS->getRouteKey(),
            MsgdPluginActionEnum::CHECK_BLUEPRINT->getRouteKey(),
            MsgdPluginActionEnum::GET_BLUEPRINT_RULES_GROUPS->getRouteKey(),
            MsgdMispActionEnum::VIEW->getRouteKey(),
        ];

        $invalidRouteKeys = [];

        foreach ($expectedRouteKeys as $routeKey) {
            if (
                !isset($urlsData[$routeKey])
                || !is_string($urlsData[$routeKey])
                || trim($urlsData[$routeKey]) === ''
            ) {
                $invalidRouteKeys[] = $routeKey;
            }
        }

        if ($invalidRouteKeys !== []) {
            MsgdLoggerUtility::log(
                'warning',
                sprintf(
                    '[MsgdPlug CTP: utils] Missing or empty endpoint URLs detected for keys: %s',
                    implode(', ', $invalidRouteKeys)
                )
            );
        }
    }

    $utilitiesConfiguration = [
        'statusTypes' => [
            'SUCCESS' => MsgdPluginStatusEnum::SUCCESS->value,
            'ERROR' => MsgdPluginStatusEnum::ERROR->value,
            'INFO' => MsgdPluginStatusEnum::INFO->value,
            'WARNING' => MsgdPluginStatusEnum::WARNING->value,
        ],
        'routeKeys' => [
            'SG_VIEW_BASE_URL' => MsgdMispActionEnum::VIEW->value,
            'CHECK_USER_PERMISSION' => MsgdPluginActionEnum::CHECK_USER_PERMISSION->getRouteKey(),
            'CHECK_BLUEPRINT' => MsgdPluginActionEnum::CHECK_BLUEPRINT->getRouteKey(),
            'GET_SHARING_GROUPS' => MsgdPluginActionEnum::GET_SHARING_GROUPS->getRouteKey(),
            'PROCESS_GROUPS' => MsgdPluginActionEnum::PROCESS_GROUPS->getRouteKey(),
            'GET_BLUEPRINT_RULES_GROUPS' => MsgdPluginActionEnum::GET_BLUEPRINT_RULES_GROUPS->getRouteKey(),
        ],
        'useGroupsIds' => [
            'USE_IDS' => (bool)Configure::read(
                MsgdPluginConfigEnum::USE_IDS->value
            ),
        ],
    ];

    $jsonFlags = JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR;

    $urlsJson = json_encode($urlsData, $jsonFlags);

    $utilitiesConfigurationJson = json_encode(
        $utilitiesConfiguration,
        $jsonFlags
    );

    $combinedJavaScript = implode("\n", [
        'window.MsgdPlugData = Object.freeze(Object.assign('
        . '{}, window.MsgdPlugData || {}, '
        . $urlsJson
        . '));',

        'window.MsgdUtilsConfig = Object.freeze(Object.assign('
        . '{}, window.MsgdUtilsConfig || {}, '
        . $utilitiesConfigurationJson
        . '));',
    ]);

    echo $this->Html->scriptBlock(
        $combinedJavaScript,
        ['inline' => true]
    );

    echo $this->Html->script(
        MsgdPluginFileEnum::UTILS_JS->getPath(),
        ['inline' => true]
    );
} catch (Throwable $exception) {
    MsgdLoggerUtility::logException(
        $exception,
        '[MsgdPlug CTP: utils] Error building utility JavaScript payload'
    );
}
