<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Represents a normalized Sharing Group entity return.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 */
readonly final class MsgdSharingGroupDTO
{
    /**
     * @param int $id
     * @param string $uuid
     * @param string $name
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name
    ) {
    }

    /**
     * Factory to build DTO directly from CakePHP array structure.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawGroupData = $data['SharingGroup'] ?? $data;
        $groupData = is_array($rawGroupData) ? $rawGroupData : $data;

        $rawId = $groupData['id'] ?? 0;
        $id = is_numeric($rawId) ? (int)$rawId : 0;

        $rawUuid = $groupData['uuid'] ?? '';
        $uuid = is_scalar($rawUuid) ? (string)$rawUuid : '';

        $rawName = $groupData['name'] ?? '';
        $name = is_scalar($rawName) ? (string)$rawName : '';

        return new self(
            id: $id,
            uuid: MsgdSanitizerUtility::sanitizeString($uuid),
            name: MsgdSanitizerUtility::sanitizeString($name)
        );
    }
}
