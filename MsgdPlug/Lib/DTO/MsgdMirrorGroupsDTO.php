<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Represents resolved mirror Sharing Group identifiers.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 */
readonly final class MsgdMirrorGroupsDTO
{
    /**
     * @param array<int, int> $ids
     * @param array<int, string> $uuids
     */
    public function __construct(
        public array $ids = [],
        public array $uuids = []
    ) {
    }

    /**
     * Creates a DTO from resolved Sharing Group identifiers.
     *
     * @param array<int, string> $resultUuids
     * @param array<int, int> $resultIds
     *
     * @return self
     */
    public static function fromArray(array $resultUuids, array $resultIds): self
    {
        return new self(
            ids: array_values(array_unique($resultIds)),
            uuids: array_values(array_unique($resultUuids))
        );
    }
}
