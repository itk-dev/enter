<?php

declare(strict_types=1);

namespace App\Source;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The manifest of data sets this application publishes.
 *
 * The record's shape is a Symfony config tree rather than hand-written checks,
 * and SourceCatalogWarmer reads the whole manifest during container warm-up, so
 * a malformed entry fails the build instead of waiting for the one import that
 * happens to select it.
 *
 * @see config/sources.yaml
 * @see SourceManifestConfiguration
 * @see docs/adr/007-source-manifest.md
 */
final class SourceCatalog
{
    /** @var array<string, SourceDescriptor>|null */
    private ?array $descriptors = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/sources.yaml')]
        private readonly string $manifest,
    ) {
    }

    /**
     * @throws \RuntimeException when the manifest cannot be read, or carries no entry for the key
     */
    public function get(string $key): SourceDescriptor
    {
        $descriptors = $this->all();

        if (!isset($descriptors[$key])) {
            throw new \RuntimeException(\sprintf('No entry for source "%s" in %s. Entries: %s.', $key, $this->manifest, [] === $descriptors ? 'none' : implode(', ', array_keys($descriptors))));
        }

        return $descriptors[$key];
    }

    /**
     * @return array<string, SourceDescriptor> keyed by source key
     *
     * @throws \RuntimeException when the manifest cannot be read
     */
    public function all(): array
    {
        return $this->descriptors ??= $this->load();
    }

    /**
     * @return array<string, SourceDescriptor>
     */
    private function load(): array
    {
        $descriptors = [];

        foreach ($this->validated() as $key => $entry) {
            $descriptors[$key] = new SourceDescriptor(
                key: $key,
                title: $entry['title'],
                accessUrl: $entry['access_url'],
                crs: $entry['crs'],
                model: $entry['model'],
                description: $entry['description'],
                publisher: $entry['publisher'],
                contact: $entry['contact'],
                landingPage: $entry['landing_page'],
                mediaType: $entry['media_type'],
                updateFrequency: $entry['update_frequency'],
                licence: $entry['licence'],
                omittedFields: $entry['omitted_fields'],
            );
        }

        return $descriptors;
    }

    /**
     * @return array<string, array{title: string, access_url: string, crs: string, model: string, description: string|null, publisher: string|null, contact: string|null, landing_page: string|null, media_type: string|null, update_frequency: string|null, licence: string|null, omitted_fields: array<string, string>}>
     */
    private function validated(): array
    {
        if (!is_file($this->manifest)) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" does not exist.', $this->manifest));
        }

        try {
            $parsed = Yaml::parseFile($this->manifest);
        } catch (ParseException $exception) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" is not valid YAML: %s', $this->manifest, $exception->getMessage()), previous: $exception);
        }

        // The tree is rooted at the entries themselves, so it never sees the
        // key holding them. Without this the wrong shape yields an empty
        // catalogue, which reads as "no data sets are registered" rather than
        // as a broken file.
        $sources = \is_array($parsed) ? $parsed['sources'] ?? null : null;
        if (!\is_array($sources)) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" must contain a "sources" mapping at the top level.', $this->manifest));
        }

        try {
            /** @var array<string, array{title: string, access_url: string, crs: string, model: string, description: string|null, publisher: string|null, contact: string|null, landing_page: string|null, media_type: string|null, update_frequency: string|null, licence: string|null, omitted_fields: array<string, string>}> $processed */
            $processed = new Processor()->processConfiguration(new SourceManifestConfiguration(), [$sources]);
        } catch (InvalidConfigurationException $exception) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" is invalid: %s', $this->manifest, $exception->getMessage()), previous: $exception);
        }

        return $processed;
    }
}
