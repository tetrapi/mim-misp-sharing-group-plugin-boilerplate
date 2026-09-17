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
        $groupData = $data['SharingGroup'] ?? $data;

        return new self(
            id: (int)($groupData['id'] ?? 0),
            uuid: MsgdSanitizerUtility::sanitizeString((string)($groupData['uuid'] ?? '')),
            name: MsgdSanitizerUtility::sanitizeString((string)($groupData['name'] ?? ''))
        );
    }
}
