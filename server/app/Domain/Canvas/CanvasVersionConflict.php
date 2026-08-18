<?php

namespace App\Domain\Canvas;

final class CanvasVersionConflict extends \Exception
{
    public function __construct(
        public readonly int $baseVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct("Canvas version conflict: expected {$baseVersion} but found {$actualVersion}");
    }
}
