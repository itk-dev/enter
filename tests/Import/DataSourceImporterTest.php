<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Broker\NgsiLdBroker;
use App\Import\DataSourceImporter;
use App\Import\Exception\EmptySourceException;
use App\Import\Exception\UnknownSourceException;
use App\Import\Exception\UpsertFailedException;
use App\Source\Manifest\Catalog;
use App\Source\SourceInterface;
use App\Tests\Source\FakeSource;
use App\Tests\Source\Manifest\WritesManifests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class DataSourceImporterTest extends TestCase
{
    use WritesManifests;
    private const string BROKER_URL = 'http://broker.invalid';

    private const string CORE_CONTEXT = 'https://example.com/core.jsonld';

    /**
     * @param iterable<SourceInterface> $sources each is registered in the manifest under its own key
     */
    private function importer(
        iterable $sources,
        ?MockHttpClient $client = null,
        string $contextUrls = self::CORE_CONTEXT,
    ): DataSourceImporter {
        $keys = [];
        foreach ($sources as $source) {
            $keys[] = $source->key();
        }

        return new DataSourceImporter(
            $sources,
            new Catalog($this->manifestFor($keys)),
            new NgsiLdBroker($client ?? new MockHttpClient(), self::BROKER_URL),
            $contextUrls,
        );
    }

    public function testItListsTheRegisteredSourceKeys(): void
    {
        $importer = $this->importer([FakeSource::withEntities('a-source'), FakeSource::withEntities('b-source')]);

        $this->assertSame(['a-source', 'b-source'], $importer->keys());
    }

    public function testItListsNothingWhenNoSourceIsRegistered(): void
    {
        $this->assertSame([], $this->importer([])->keys());
    }

    public function testItRejectsAnUnknownSourceAndNamesTheKnownOnes(): void
    {
        $importer = $this->importer([FakeSource::withEntities('some-source')]);

        $this->expectException(UnknownSourceException::class);
        $this->expectExceptionMessage('Unknown source "nope". Available: some-source.');

        $importer->payload('nope');
    }

    /**
     * The important one: a source yielding nothing used to be reported as a
     * completed import, which is indistinguishable from a working one.
     */
    public function testItFailsWhenASourceProducesNothing(): void
    {
        $importer = $this->importer([new FakeSource('empty-source')]);

        $this->expectException(EmptySourceException::class);
        $this->expectExceptionMessage('Source "empty-source" produced no entities.');

        $importer->payload('empty-source');
    }

    /**
     * The guard belongs to the conversion rather than to the dry run, so an
     * import that intends to send is held to it too.
     */
    public function testItSendsNothingWhenASourceProducesNothing(): void
    {
        $client = new MockHttpClient();

        try {
            $this->importer([new FakeSource('empty-source')], $client)->import('empty-source');
            $this->fail('An empty source was reported as a completed import.');
        } catch (EmptySourceException) {
            $this->assertSame(0, $client->getRequestsCount(), 'An empty payload was sent to the broker.');
        }
    }

    /**
     * Later entries win term conflicts, so the data set's own context comes
     * first and the core context last, where it stays authoritative over the
     * NGSI-LD terms a domain context may also define.
     */
    public function testEveryEntityCarriesItsDataSetContextThenTheCoreContext(): void
    {
        $importer = $this->importer([FakeSource::withEntities('one-entity', 'urn:ngsi-ld:Example:1')]);

        $payload = $importer->payload('one-entity');

        $this->assertSame(
            [self::dataSetContext('one-entity'), self::CORE_CONTEXT],
            $payload[0]['@context']
        );
    }

    /**
     * Two data sets published under different models must not advertise each
     * other's vocabulary, which a single app-wide list cannot avoid.
     */
    public function testEachDataSetCarriesOnlyItsOwnContext(): void
    {
        $importer = $this->importer([
            FakeSource::withEntities('first-source', 'urn:ngsi-ld:Example:1'),
            FakeSource::withEntities('second-source', 'urn:ngsi-ld:Example:2'),
        ]);

        $this->assertSame(
            [self::dataSetContext('first-source'), self::CORE_CONTEXT],
            $importer->payload('first-source')[0]['@context']
        );
        $this->assertSame(
            [self::dataSetContext('second-source'), self::CORE_CONTEXT],
            $importer->payload('second-source')[0]['@context']
        );
    }

    /**
     * A source registered in the container but not in the manifest has no
     * context to publish under, so it fails rather than emitting entities a
     * consumer cannot resolve.
     */
    public function testItFailsWhenASourceHasNoManifestEntry(): void
    {
        $importer = new DataSourceImporter(
            [FakeSource::withEntities('unregistered', 'urn:ngsi-ld:Example:1')],
            new Catalog($this->manifestFor(['other-source'])),
            new NgsiLdBroker(new MockHttpClient(), self::BROKER_URL),
            self::CORE_CONTEXT,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No entry for source "unregistered"');

        $importer->payload('unregistered');
    }

    /**
     * The contexts arrive as one comma-separated environment variable, so they
     * are written by hand and carry whatever spacing that produces.
     */
    public function testItIgnoresSpacingAndEmptyEntriesInTheConfiguredContexts(): void
    {
        $importer = $this->importer(
            [FakeSource::withEntities('one-entity', 'urn:ngsi-ld:Example:1')],
            contextUrls: ' '.self::CORE_CONTEXT.' , ,',
        );

        $payload = $importer->payload('one-entity');

        $this->assertSame(
            [self::dataSetContext('one-entity'), self::CORE_CONTEXT],
            $payload[0]['@context']
        );
    }

    public function testALimitCapsThePayload(): void
    {
        $importer = $this->importer([FakeSource::withEntities('many', 'urn:1', 'urn:2', 'urn:3', 'urn:4', 'urn:5')]);

        $this->assertCount(2, $importer->payload('many', 2));
    }

    /**
     * A real source reads a whole feed, so a limit that converts everything
     * and then trims would do all the work it was given to avoid.
     */
    public function testALimitStopsPullingFromTheSource(): void
    {
        $source = FakeSource::withEntities('many', 'urn:1', 'urn:2', 'urn:3', 'urn:4', 'urn:5');

        $this->importer([$source])->payload('many', 2);

        $this->assertSame(2, $source->produced());
    }

    /**
     * --limit 0 is a mistyped option. Honouring it would produce an empty
     * payload, which is the one outcome an import refuses to call a success.
     */
    public function testALimitBelowOneStillImportsOneEntity(): void
    {
        $importer = $this->importer([FakeSource::withEntities('many', 'urn:1', 'urn:2')]);

        $this->assertCount(1, $importer->payload('many', 0));
    }

    public function testBuildingThePayloadSendsNothing(): void
    {
        $client = new MockHttpClient();

        $this->importer([FakeSource::withEntities('one-entity', 'urn:1')], $client)->payload('one-entity');

        $this->assertSame(0, $client->getRequestsCount());
    }

    public function testItUpsertsThePayloadAndReportsWhatTheBrokerDid(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 204]));

        $result = $this->importer([FakeSource::withEntities('many', 'urn:1', 'urn:2')], $client)->import('many');

        $this->assertSame(2, $result->count);
        $this->assertSame(204, $result->status);
        $this->assertSame(self::BROKER_URL, $result->brokerUrl);
        $this->assertSame(1, $client->getRequestsCount(), 'The entities were not sent as one batch.');
    }

    public function testItReportsWhatTheBrokerSaidWhenTheUpsertIsRejected(): void
    {
        $client = new MockHttpClient(new MockResponse('{"title":"Bad Request"}', ['http_code' => 400]));

        try {
            $this->importer([FakeSource::withEntities('one-entity', 'urn:1')], $client)->import('one-entity');
            $this->fail('A rejected upsert was reported as a completed import.');
        } catch (UpsertFailedException $exception) {
            $this->assertStringContainsString('HTTP 400', $exception->getMessage());
            $this->assertStringContainsString('Bad Request', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious(), 'The broker\'s own exception was discarded.');
        }
    }

    /**
     * A broker that cannot be reached fails in the HTTP client rather than in
     * the broker's status check, and the two are the same thing to a caller.
     */
    public function testItFailsTheSameWayWhenTheBrokerCannotBeReached(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('Connection refused');
        });

        $this->expectException(UpsertFailedException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->importer([FakeSource::withEntities('one-entity', 'urn:1')], $client)->import('one-entity');
    }
}
