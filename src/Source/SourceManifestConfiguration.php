<?php

declare(strict_types=1);

namespace App\Source;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The shape of the source manifest, as a Symfony config tree.
 *
 * The tree is rooted at the entries rather than at the file, so a validation
 * error names the path a maintainer sees in the manifest. Each `info()` is
 * suffixed to that error as a hint, which is the moment a field's rationale is
 * needed.
 *
 * @see config/sources.yaml
 * @see docs/adr/007-source-manifest.md
 */
final readonly class SourceManifestConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('sources');

        $tree->getRootNode()
            // The import selects a data set by its key exactly as written in
            // the manifest. Key normalization rewrites a key that contains
            // dashes and no underscore, which breaks that lookup silently.
            ->normalizeKeys(false)
            ->requiresAtLeastOneElement()
            ->arrayPrototype()
                ->beforeNormalization()
                    ->always(self::blankToNull())
                ->end()
                ->children()
                    ->scalarNode('title')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('What the data set is called where it is published.')
                    ->end()
                    ->scalarNode('access_url')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('Where the feed is read from; an import cannot run without it.')
                    ->end()
                    ->scalarNode('crs')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('The CRS the feed publishes coordinates in, e.g. "EPSG:25832". A wrong value yields well-formed coordinates in the wrong place.')
                    ->end()
                    ->scalarNode('model')
                        ->isRequired()
                        ->cannotBeEmpty()
                        ->info('Smart Data Model the data set is published as.')
                    ->end()
                    ->scalarNode('description')->defaultNull()->end()
                    ->scalarNode('publisher')->defaultNull()->end()
                    ->scalarNode('contact')->defaultNull()->end()
                    ->scalarNode('landing_page')->defaultNull()->end()
                    ->scalarNode('media_type')->defaultNull()->end()
                    ->scalarNode('update_frequency')->defaultNull()->end()
                    ->scalarNode('licence')
                        ->defaultNull()
                        ->info('Empty means the terms are unsettled; DCAT-AP requires one before the data set can be registered.')
                    ->end()
                    ->arrayNode('omitted_fields')
                        ->defaultValue([])
                        ->normalizeKeys(false)
                        ->scalarPrototype()
                            ->beforeNormalization()
                                ->always(self::blankToNullValue())
                            ->end()
                            ->cannotBeEmpty()
                            ->info('The reason a field is withheld cannot be recovered from the code, so a bare list of names is not accepted.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }

    /**
     * An empty value means "not filled in", the same as an absent key, so both
     * become null rather than an empty string.
     *
     * Normalization runs before the type check, so an entry that is not a
     * mapping has to pass through untouched for the tree to report it as one.
     */
    private static function blankToNull(): \Closure
    {
        return static function (mixed $entry): mixed {
            if (!\is_array($entry)) {
                return $entry;
            }

            return array_map(self::blankToNullValue(), $entry);
        };
    }

    private static function blankToNullValue(): \Closure
    {
        return static function (mixed $value): mixed {
            if (!\is_string($value)) {
                return $value;
            }

            $value = trim($value);

            return '' === $value ? null : $value;
        };
    }
}
