<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Broker\NgsiLdBroker;
use App\Command\ImportCommand;
use App\Import\DataSourceImporter;
use App\Source\Manifest\Catalog;
use App\Source\SourceInterface;
use App\Tests\Source\FakeSource;
use App\Tests\Source\Manifest\WritesManifests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The console surface only: the affordances around an import, and which exit
 * code each outcome maps to. What an import decides belongs to
 * DataSourceImporter and is covered by DataSourceImporterTest.
 */
class ImportCommandTest extends TestCase
{
    use WritesManifests;

    /**
     * @param iterable<SourceInterface> $sources
     */
    private function tester(iterable $sources, ?MockHttpClient $client = null): CommandTester
    {
        $keys = [];
        foreach ($sources as $source) {
            $keys[] = $source->id;
        }

        return new CommandTester(new ImportCommand(new DataSourceImporter(
            $sources,
            new Catalog($this->manifestFor($keys)),
            new NgsiLdBroker($client ?? new MockHttpClient(), 'http://broker.invalid'),
            'https://example.com/core.jsonld',
        )));
    }

    public function testItListsTheSourcesWhenCalledBare(): void
    {
        $tester = $this->tester([FakeSource::withEntities('some-source')]);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('some-source', $tester->getDisplay());
    }

    public function testItFailsWhenNoSourcesAreRegistered(): void
    {
        $tester = $this->tester([]);

        $status = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('No data sources are registered', $tester->getDisplay());
    }

    /**
     * Passing --dry-run with no source used to print the source listing and
     * exit successfully, silently ignoring the flag.
     */
    public function testItRejectsOptionsWithoutASource(): void
    {
        $tester = $this->tester([FakeSource::withEntities('some-source')]);

        $status = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::INVALID, $status);
        $this->assertStringContainsString('No source given', $tester->getDisplay());
    }

    public function testAnUnknownSourceIsTheCallersMistake(): void
    {
        $tester = $this->tester([FakeSource::withEntities('some-source')]);

        $status = $tester->execute(['source' => 'nope']);

        $this->assertSame(Command::INVALID, $status);
        $this->assertStringContainsString('Unknown source "nope"', $tester->getDisplay());
    }

    /**
     * The failure carries no exception to show, so the command has to supply
     * the places worth looking itself.
     */
    public function testAnEmptySourceFailsAndSuggestsCauses(): void
    {
        $tester = $this->tester([new FakeSource('empty-source')]);

        $status = $tester->execute(['source' => 'empty-source']);
        $display = $tester->getDisplay();

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('produced no entities', $display);
        $this->assertStringContainsString('path or URL', $display);
        $this->assertStringContainsString('envelope, nesting, field names', $display);
    }

    public function testDryRunPrintsThePayloadAndSendsNothing(): void
    {
        $client = new MockHttpClient();
        $tester = $this->tester([FakeSource::withEntities('one-entity', 'urn:ngsi-ld:Example:1')], $client);

        $status = $tester->execute(['source' => 'one-entity', '--dry-run' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('"urn:ngsi-ld:Example:1"', $display);
        $this->assertStringContainsString('1 entities were not sent', $display);
        $this->assertSame(0, $client->getRequestsCount());
    }

    public function testItReportsWhatWasUpserted(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 204]));
        $tester = $this->tester([FakeSource::withEntities('one-entity', 'urn:ngsi-ld:Example:1')], $client);

        $status = $tester->execute(['source' => 'one-entity']);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Upserted 1 entities', $display);
        $this->assertStringContainsString('HTTP 204', $display);
    }

    /**
     * The broker being down is an operational condition rather than a bug, so
     * it is reported as a message instead of an uncaught exception.
     */
    public function testABrokerFailureIsReportedAsAnError(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $tester = $this->tester([FakeSource::withEntities('one-entity', 'urn:ngsi-ld:Example:1')], $client);

        $status = $tester->execute(['source' => 'one-entity']);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('HTTP 500', $tester->getDisplay());
    }
}
