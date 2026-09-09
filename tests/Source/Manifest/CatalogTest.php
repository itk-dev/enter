<?php

declare(strict_types=1);

namespace App\Tests\Source\Manifest;

use App\Source\Manifest\Catalog;
use PHPUnit\Framework\TestCase;

class CatalogTest extends TestCase
{
    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->written = [];
    }

    public function testTheShippedManifestIsUsable(): void
    {
        $catalog = new Catalog(\dirname(__DIR__, 3).'/config/sources.yaml');

        $this->assertNotSame([], $catalog->all(), 'The manifest registers no data sets.');
    }

    /**
     * A wrong URL scheme or a CRS the transformer does not know only surfaces
     * mid-import otherwise, after the feed has been fetched.
     */
    public function testEveryShippedEntryCanBeImportedFrom(): void
    {
        $catalog = new Catalog(\dirname(__DIR__, 3).'/config/sources.yaml');

        foreach ($catalog->all() as $key => $descriptor) {
            $this->assertSame($key, $descriptor->key);
            $this->assertMatchesRegularExpression('#^https?://#', $descriptor->accessUrl, $key);
            $this->assertMatchesRegularExpression('/^EPSG:\d+$/', $descriptor->crs, $key);
            $this->assertNotSame('', $descriptor->model, $key);
            $this->assertMatchesRegularExpression('#^https?://#', $descriptor->contextUrl, $key);
        }
    }

    public function testItNamesTheKnownEntriesWhenAskedForAnUnknownOne(): void
    {
        $catalog = new Catalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    context_url: https://example.com/context.jsonld
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entries: a-source.');

        $catalog->get('no-such-source');
    }

    public function testItRejectsAManifestWithoutASourcesMapping(): void
    {
        $catalog = new Catalog($this->manifest("data_sets:\n    a-source: {}\n"));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must contain a "sources" mapping');

        $catalog->all();
    }

    public function testItRejectsAnEntryMissingAFieldTheImportNeeds(): void
    {
        $catalog = new Catalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    model: Example
                    context_url: https://example.com/context.jsonld
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The child config "crs" under "sources.a-source" must be configured');

        $catalog->all();
    }

    public function testItRejectsAnOmittedFieldWithoutAReason(): void
    {
        $catalog = new Catalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    context_url: https://example.com/context.jsonld
                    omitted_fields:
                        some_field: ~
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"sources.a-source.omitted_fields.some_field" cannot contain an empty value');

        $catalog->all();
    }

    /**
     * The import selects a data set by the key written in the manifest, and the
     * config tree rewrites a key that has dashes and no underscore unless told
     * otherwise. A rewritten key stops matching without saying so.
     */
    public function testItKeepsADashedSourceKeyIntact(): void
    {
        $catalog = new Catalog($this->manifest(<<<'YAML'
            sources:
                handicap-parking:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    context_url: https://example.com/context.jsonld
            YAML));

        $this->assertSame(['handicap-parking'], array_keys($catalog->all()));
        $this->assertSame('handicap-parking', $catalog->get('handicap-parking')->key);
    }

    /**
     * A misspelled optional field was dropped in silence before, which loses a
     * fact the record exists to carry.
     */
    public function testItRejectsAFieldTheManifestDoesNotDefine(): void
    {
        $catalog = new Catalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    context_url: https://example.com/context.jsonld
                    license: CC-BY-4.0
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unrecognized option "license" under "sources.a-source"');

        $catalog->all();
    }

    public function testItRejectsAnEntryThatIsNotAMapping(): void
    {
        $catalog = new Catalog($this->manifest("sources:\n    a-source: just a string\n"));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid type for path "sources.a-source"');

        $catalog->all();
    }

    public function testItRejectsAManifestThatRegistersNothing(): void
    {
        $catalog = new Catalog($this->manifest("sources: {}\n"));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('should have at least 1 element');

        $catalog->all();
    }

    /**
     * An empty value means "not filled in", the same as an absent key, so an
     * unanswered question reads the same either way.
     */
    public function testItReadsABlankOptionalFieldAsUnknown(): void
    {
        $catalog = new Catalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    context_url: https://example.com/context.jsonld
                    publisher: '   Aarhus Kommune   '
                    contact: ''
                    licence: ~
            YAML));

        $descriptor = $catalog->get('a-source');

        $this->assertSame('Aarhus Kommune', $descriptor->publisher);
        $this->assertNull($descriptor->contact);
        $this->assertNull($descriptor->licence);
    }

    public function testItReportsAManifestThatIsNotThere(): void
    {
        $catalog = new Catalog('/no/such/sources.yaml');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $catalog->all();
    }

    public function testItReportsUnparsableYaml(): void
    {
        $catalog = new Catalog($this->manifest("sources:\n  - [unbalanced\n"));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not valid YAML');

        $catalog->all();
    }

    private function manifest(string $yaml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sources-');

        if (false === $path) {
            $this->fail('Could not create a temporary manifest.');
        }

        file_put_contents($path, $yaml);
        $this->written[] = $path;

        return $path;
    }
}
