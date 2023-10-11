<?php

namespace iggyvolz\builder\example;

final readonly class DemoPodo
{
    public function __construct(
        public string $foo,
        public int    $bar = -1,
    )
    {
    }

    use DemoPodo_builderTrait;
}