<?php

/**
 * MsgdPlug Plugin
 *
 * @author     TETRAPI SA, Lino Pacheco
 * @license    AGPL-3.0
 */

declare(strict_types=1);

/**
 * Represents normalized Sharing Group Blueprint rules.
 *
 * @package    MsgdPlug
 * @subpackage MsgdPlug.Lib.DTO
 */
readonly final class MsgdBlueprintRulesDTO
{
    /**
     * Initializes DTO with already parsed and strict properties.
     *
     * @param array<string, mixed> $raw
     * @param array<int, int> $sharingGroupsIds
     * @param array<int, string> $sharingGroupsUuids
     * @param array<int, int|string> $allSharingGroupIdentifiers
     */
    public function __construct(
        public array $raw = [],
        public array $sharingGroupsIds = [],
        public array $sharingGroupsUuids = [],
        public array $allSharingGroupIdentifiers = []
    ) {
    }

    /**
     * Creates a DTO instance from an array|json of rules.
     *
     * @param string|array<string, mixed> $rules
     *
     * @return self
     */
    public static function fromArray(string|array $rules): self
    {
        if (is_string($rules)) {
            try {
                $parsed = json_decode($rules, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidArgumentException(
                    sprintf('Blueprint rules contain invalid JSON: %s', $exception->getMessage()),
                    0,
                    $exception
                );
            }
        } else {
            $parsed = $rules;
        }

        if (!is_array($parsed)) {
            throw new InvalidArgumentException('Blueprint rules must be a valid JSON object.');
        }

        /** @var array<string, mixed> $parsed */
        $raw = $parsed;

        $andConditions = $parsed['AND'] ?? [];
        $andConditions = is_array($andConditions) ? $andConditions : [];

        $orConditions = $andConditions['OR'] ?? [];
        $orConditions = is_array($orConditions) ? $orConditions : [];

        $ids = $orConditions['sharing_group_id'] ?? [];
        $uuids = $orConditions['sharing_group_uuid'] ?? [];

        $rawIds = is_array($ids) ? array_values($ids) : [$ids];
        /** @var array<int|string> $rawIds */
        $sharingGroupsIds = self::cleanIds($rawIds);

        $rawUuids = is_array($uuids) ? array_values($uuids) : [$uuids];
        /** @var array<string> $rawUuids */
        $sharingGroupsUuids = self::cleanUuids($rawUuids);

        $allSharingGroupIdentifiers = array_values(
            array_unique(
                array_merge($sharingGroupsIds, $sharingGroupsUuids),
                SORT_REGULAR
            )
        );

        return new self(
            raw: $raw,
            sharingGroupsIds: $sharingGroupsIds,
            sharingGroupsUuids: $sharingGroupsUuids,
            allSharingGroupIdentifiers: $allSharingGroupIdentifiers
        );
    }

    /**
     * Creates blueprint rules from Sharing Group identifiers.
     *
     * @param array<int|string> $identifiers
     *
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public static function fromIdentifiers(array $identifiers): self
    {
        $ids = [];
        $uuids = [];

        foreach ($identifiers as $identifier) {
            if (is_int($identifier) && $identifier > 0) {
                $ids[] = $identifier;
                continue;
            }

            if (!is_string($identifier)) {
                continue;
            }

            $identifier = trim($identifier);

            if ($identifier === '') {
                continue;
            }

            if (ctype_digit($identifier) && (int)$identifier > 0) {
                $ids[] = (int)$identifier;
                continue;
            }

            if (MsgdSanitizerUtility::isValidUuid($identifier)) {
                $uuids[] = $identifier;
            }
        }

        $ids = array_values(array_unique($ids));
        $uuids = array_values(array_unique($uuids));

        $orConditions = [];

        if ($ids !== []) {
            $orConditions['sharing_group_id'] = count($ids) === 1 ? $ids[0] : $ids;
        }

        if ($uuids !== []) {
            $orConditions['sharing_group_uuid'] = count($uuids) === 1 ? $uuids[0] : $uuids;
        }

        return self::fromArray(['AND' => ['OR' => $orConditions]]);
    }

    /**
     * Returns the raw MISP rules array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * Returns rules as JSON for the MISP model.
     *
     * @return string
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->raw, JSON_THROW_ON_ERROR);
    }

    /**
     * Normalizes Sharing Group IDs.
     *
     * @param array<int|string> $items
     *
     * @return array<int, int>
     */
    private static function cleanIds(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (is_int($item) && $item > 0) {
                $result[] = $item;
                continue;
            }

            if (is_string($item) && ctype_digit($item) && (int)$item > 0) {
                $result[] = (int)$item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * Normalizes and validates Sharing Group UUIDs.
     *
     * @param array<string> $items
     *
     * @return array<int, string>
     */
    private static function cleanUuids(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $trimmed = trim($item);
            if (MsgdSanitizerUtility::isValidUuid($trimmed)) {
                $result[] = $trimmed;
            }
        }

        return array_values(array_unique($result));
    }
}
