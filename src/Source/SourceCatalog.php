<?php

declare(strict_types=1);

namespace App\Source;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The manifest of data sets this application publishes.
 *
 * Parsed on first use rather than during container warm-up, so a malformed
 * entry fails the import that needs it instead of every cache clear.
 *
 * @see config/sources.yaml
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
        if (!is_file($this->manifest)) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" does not exist.', $this->manifest));
        }

        try {
            $parsed = Yaml::parseFile($this->manifest);
        } catch (ParseException $exception) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" is not valid YAML: %s', $this->manifest, $exception->getMessage()), previous: $exception);
        }

        // Without this the wrong shape yields an empty catalogue, which reads
        // as "no data sets are registered" rather than as a broken file.
        $sources = \is_array($parsed) ? $parsed['sources'] ?? null : null;
        if (!\is_array($sources)) {
            throw new \RuntimeException(\sprintf('Source manifest "%s" must contain a "sources" mapping at the top level.', $this->manifest));
        }

        $descriptors = [];
        foreach ($sources as $key => $entry) {
            $key = (string) $key;

            if (!\is_array($entry)) {
                throw new \RuntimeException(\sprintf('Entry "%s" in %s must be a mapping, got %s.', $key, $this->manifest, get_debug_type($entry)));
            }

            $descriptors[$key] = $this->descriptor($key, $entry);
        }

        return $descriptors;
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function descriptor(string $key, array $entry): SourceDescriptor
    {
        return new SourceDescriptor(
            key: $key,
            title: $this->required($key, $entry, 'title'),
            accessUrl: $this->required($key, $entry, 'access_url'),
            crs: $this->required($key, $entry, 'crs'),
            model: $this->required($key, $entry, 'model'),
            description: $this->optional($key, $entry, 'description'),
            publisher: $this->optional($key, $entry, 'publisher'),
            contact: $this->optional($key, $entry, 'contact'),
            landingPage: $this->optional($key, $entry, 'landing_page'),
            mediaType: $this->optional($key, $entry, 'media_type'),
            updateFrequency: $this->optional($key, $entry, 'update_frequency'),
            licence: $this->optional($key, $entry, 'licence'),
            omittedFields: $this->omittedFields($key, $entry),
        );
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function required(string $key, array $entry, string $field): string
    {
        return $this->optional($key, $entry, $field)
            ?? throw new \RuntimeException(\sprintf('Entry "%s" in %s is missing the required field "%s"; an import cannot run without it.', $key, $this->manifest, $field));
    }

    /**
     * An empty value means "not filled in", the same as an absent key, so both
     * become null rather than an empty string.
     *
     * @param array<array-key, mixed> $entry
     */
    private function optional(string $key, array $entry, string $field): ?string
    {
        $value = $entry[$field] ?? null;

        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_scalar($value)) {
            throw new \RuntimeException(\sprintf('Field "%s" of entry "%s" in %s must be a single value, got %s.', $field, $key, $this->manifest, get_debug_type($value)));
        }

        return trim((string) $value);
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return array<string, string>
     */
    private function omittedFields(string $key, array $entry): array
    {
        $omitted = $entry['omitted_fields'] ?? [];

        if (!\is_array($omitted)) {
            throw new \RuntimeException(\sprintf('Field "omitted_fields" of entry "%s" in %s must map each field to the reason it is not published.', $key, $this->manifest));
        }

        $reasons = [];
        foreach ($omitted as $field => $reason) {
            // The reason is the half of the record that cannot be recovered
            // from the code, so a bare list of names is not accepted.
            if (!\is_string($reason) || '' === trim($reason)) {
                throw new \RuntimeException(\sprintf('Omitted field "%s" of entry "%s" in %s needs a reason.', $field, $key, $this->manifest));
            }

            $reasons[(string) $field] = trim($reason);
        }

        return $reasons;
    }
}
