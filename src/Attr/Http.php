<?php
/**
 * HTTP関連 Attributes
 */

namespace Fzr\Attr\Http;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Csrf {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Api {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Role {
    public array $roles;
    public function __construct(string ...$roles) { $this->roles = $roles; }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AllowCors {
    public ?string $origin;
    public function __construct(?string $origin = null) { $this->origin = $origin; }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AllowCache {
    public int $maxAge;
    public function __construct(int $maxAge = 3600) { $this->maxAge = $maxAge; }
}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class AllowIframe {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsReadOnly {}

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IpWhitelist {}
