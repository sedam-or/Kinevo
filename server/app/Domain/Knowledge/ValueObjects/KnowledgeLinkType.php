<?php

namespace App\Domain\Knowledge\ValueObjects;

use InvalidArgumentException;

/**
 * Explicit link type between a knowledge item and a domain object
 * (SRS §10.5 Knowledge Linking).
 */
final class KnowledgeLinkType
{
    public const SUPPORTS = 'supports';

    public const REFERENCES = 'references';

    public const DERIVED_FROM = 'derived_from';

    public const EVIDENCE_FOR = 'evidence_for';

    public const RELATED_TO = 'related_to';

    private const TYPES = [
        self::SUPPORTS,
        self::REFERENCES,
        self::DERIVED_FROM,
        self::EVIDENCE_FOR,
        self::RELATED_TO,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported knowledge link type: {$value}");
        }
    }

    public static function supports(): self
    {
        return new self(self::SUPPORTS);
    }

    public static function references(): self
    {
        return new self(self::REFERENCES);
    }

    public static function derivedFrom(): self
    {
        return new self(self::DERIVED_FROM);
    }

    public static function evidenceFor(): self
    {
        return new self(self::EVIDENCE_FOR);
    }

    public static function relatedTo(): self
    {
        return new self(self::RELATED_TO);
    }

    public static function all(): array
    {
        return self::TYPES;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
