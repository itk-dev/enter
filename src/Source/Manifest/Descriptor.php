<?php

declare(strict_types=1);

namespace App\Source\Manifest;

/**
 * Everything about a feed except its field mapping, which belongs to the
 * source class. The names follow DCAT-AP.
 *
 * @see config/sources.yaml
 * @see docs/adr/007-source-manifest.md
 */
final readonly class Descriptor
{
    /**
     * @param string                $key           identifier the import selects this data set by
     * @param string                $accessUrl     where the feed is read from
     * @param string                $crs           the CRS the feed publishes coordinates in, e.g. "EPSG:25832"
     * @param string                $model         Smart Data Model the data set is published as
     * @param array<string, string> $omittedFields source field name => why it is not published
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $accessUrl,
        public string $crs,
        public string $model,
        public string $contextUrl,
        public ?string $description = null,
        public ?string $publisher = null,
        public ?string $contact = null,
        public ?string $landingPage = null,
        public ?string $mediaType = null,
        public ?string $updateFrequency = null,
        public ?string $licence = null,
        public array $omittedFields = [],
    ) {
    }
}
