<?php

declare(strict_types=1);

namespace App\Tests\Source;

use App\Source\FeedReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FeedReaderTest extends TestCase
{
    /**
     * Content-agnostic on purpose: a minimal FeatureCollection with meaningless
     * properties, because nothing this class does depends on what the feed
     * contains — only on the envelope surviving.
     */
    private const string FEATURE_COLLECTION = '{"type":"FeatureCollection","features":[{"example":1},{"example":2}]}';

    private function reader(?MockHttpClient $client = null): FeedReader
    {
        return new FeedReader($client ?? new MockHttpClient());
    }

    public function testItReadsFromAnHttpUrl(): void
    {
        $client = new MockHttpClient(new MockResponse('[{"example": 1}]'));

        $document = $this->reader($client)->read('https://example.com/feed.json');

        $this->assertSame([['example' => 1]], $document);
    }

    public function testItAcceptsABareArrayDocument(): void
    {
        $client = new MockHttpClient(new MockResponse('[1, 2, 3]'));

        $this->assertSame([1, 2, 3], $this->reader($client)->read('http://example.com/feed.json'));
    }

    /**
     * The envelope is feed-specific knowledge, so it must survive intact for
     * the caller to interpret. Returning the features list directly would push
     * that knowledge into the wrong class.
     */
    public function testItDoesNotUnwrapTheEnvelope(): void
    {
        $client = new MockHttpClient(new MockResponse(self::FEATURE_COLLECTION));

        $document = $this->reader($client)->read('https://example.com/feed.json');

        $this->assertArrayHasKey('features', $document);
        $this->assertArrayNotHasKey(0, $document);
        $this->assertCount(2, $document['features']);
    }

    public function testItRejectsALocationThatIsNotAUrl(): void
    {
        $client = new MockHttpClient();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be an http\(s\) URL/');

        try {
            $this->reader($client)->read('data/feed.json');
        } finally {
            // Rejection has to happen before the request, or a mistyped
            // location becomes an opaque transport error instead.
            $this->assertSame(0, $client->getRequestsCount());
        }
    }

    /**
     * A string that merely begins with "http" is not a URL.
     */
    public function testItRejectsAPathThatMerelyStartsWithHttp(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must be an http\(s\) URL/');

        $this->reader()->read('https-export.json');
    }

    public function testItFailsOnInvalidJson(): void
    {
        $client = new MockHttpClient(new MockResponse('{ not json'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');

        $this->reader($client)->read('https://example.com/feed.json');
    }

    public function testItFailsWhenTheDocumentIsAScalar(): void
    {
        $client = new MockHttpClient(new MockResponse('"just a string"'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Expected a JSON array or object/');

        $this->reader($client)->read('https://example.com/feed.json');
    }

    public function testItWrapsTransportFailures(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Could not fetch/');

        $this->reader($client)->read('https://example.com/feed.json');
    }
}
