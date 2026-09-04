<?php

declare(strict_types=1);

namespace App\Tests\Source;

use App\Source\SourceCatalog;
use PHPUnit\Framework\TestCase;

class SourceCatalogTest extends TestCase
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
        $catalog = new SourceCatalog(\dirname(__DIR__, 2).'/config/sources.yaml');

        $this->assertNotSame([], $catalog->all(), 'The manifest registers no data sets.');
    }

    /**
     * A wrong URL scheme or a CRS the transformer does not know only surfaces
     * mid-import otherwise, after the feed has been fetched.
     */
    public function testEveryShippedEntryCanBeImportedFrom(): void
    {
        $catalog = new SourceCatalog(\dirname(__DIR__, 2).'/config/sources.yaml');

        foreach ($catalog->all() as $key => $descriptor) {
            $this->assertSame($key, $descriptor->key);
            $this->assertMatchesRegularExpression('#^https?://#', $descriptor->accessUrl, $key);
            $this->assertMatchesRegularExpression('/^EPSG:\d+$/', $descriptor->crs, $key);
            $this->assertNotSame('', $descriptor->model, $key);
        }
    }

    public function testItNamesTheKnownEntriesWhenAskedForAnUnknownOne(): void
    {
        $catalog = new SourceCatalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Entries: a-source.');

        $catalog->get('no-such-source');
    }

    public function testItRejectsAManifestWithoutASourcesMapping(): void
    {
        $catalog = new SourceCatalog($this->manifest("data_sets:\n    a-source: {}\n"));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must contain a "sources" mapping');

        $catalog->all();
    }

    public function testItRejectsAnEntryMissingAFieldTheImportNeeds(): void
    {
        $catalog = new SourceCatalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    model: Example
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing the required field "crs"');

        $catalog->all();
    }

    public function testItRejectsAnOmittedFieldWithoutAReason(): void
    {
        $catalog = new SourceCatalog($this->manifest(<<<'YAML'
            sources:
                a-source:
                    title: A source
                    access_url: https://example.com/feed.json
                    crs: EPSG:25832
                    model: Example
                    omitted_fields:
                        some_field: ~
            YAML));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('needs a reason');

        $catalog->all();
    }

    public function testItReportsAManifestThatIsNotThere(): void
    {
        $catalog = new SourceCatalog('/no/such/sources.yaml');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $catalog->all();
    }

    public function testItReportsUnparsableYaml(): void
    {
        $catalog = new SourceCatalog($this->manifest("sources:\n  - [unbalanced\n"));

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
