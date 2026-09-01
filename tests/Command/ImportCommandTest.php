<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Broker\NgsiLdBroker;
use App\Command\ImportCommand;
use App\Ngsi\NgsiEntity;
use App\Source\SourceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;

class ImportCommandTest extends TestCase
{
    /**
     * @param iterable<SourceInterface> $sources
     */
    private function tester(iterable $sources): CommandTester
    {
        return new CommandTester(new ImportCommand(
            $sources,
            new NgsiLdBroker(new MockHttpClient(), 'http://broker.invalid'),
            'https://example.com/context.jsonld',
        ));
    }

    private function source(string $key, NgsiEntity ...$entities): SourceInterface
    {
        return new readonly class($key, $entities) implements SourceInterface {
            /** @param list<NgsiEntity> $entities */
            public function __construct(
                private string $key,
                private array $entities,
            ) {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function entities(): iterable
            {
                yield from $this->entities;
            }
        };
    }

    /**
     * The important one: a source yielding nothing used to exit successfully
     * with a warning, which is indistinguishable from a working import.
     */
    public function testItFailsWhenASourceProducesNothing(): void
    {
        $tester = $this->tester([$this->source('empty-source')]);

        $status = $tester->execute(['source' => 'empty-source', '--dry-run' => true]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('produced no entities', $tester->getDisplay());
    }

    public function testItSuggestsCausesWhenASourceProducesNothing(): void
    {
        $tester = $this->tester([$this->source('empty-source')]);
        $tester->execute(['source' => 'empty-source', '--dry-run' => true]);

        $display = $tester->getDisplay();

        $this->assertStringContainsString('path or URL', $display);
        $this->assertStringContainsString('envelope, nesting, field names', $display);
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
        $tester = $this->tester([$this->source('some-source')]);

        $status = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::INVALID, $status);
        $this->assertStringContainsString('No source given', $tester->getDisplay());
    }

    public function testItStillListsSourcesWhenCalledBare(): void
    {
        $tester = $this->tester([$this->source('some-source')]);

        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('some-source', $tester->getDisplay());
    }

    public function testItRejectsAnUnknownSource(): void
    {
        $tester = $this->tester([$this->source('some-source')]);

        $status = $tester->execute(['source' => 'nope']);

        $this->assertSame(Command::INVALID, $status);
        $this->assertStringContainsString('Unknown source "nope"', $tester->getDisplay());
    }

    public function testDryRunPrintsThePayloadAndSendsNothing(): void
    {
        $entity = new NgsiEntity('urn:ngsi-ld:Example:1', 'Example')
            ->property('name', 'Example');

        $tester = $this->tester([$this->source('one-entity', $entity)]);

        $status = $tester->execute(['source' => 'one-entity', '--dry-run' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('"urn:ngsi-ld:Example:1"', $display);
        $this->assertStringContainsString('1 entities were not sent', $display);
    }

    public function testLimitCapsThePayload(): void
    {
        $entities = [];
        foreach (range(1, 5) as $i) {
            $entities[] = new NgsiEntity(\sprintf('urn:ngsi-ld:Example:%d', $i), 'Example');
        }

        $tester = $this->tester([$this->source('many', ...$entities)]);
        $tester->execute(['source' => 'many', '--dry-run' => true, '--limit' => 2]);

        $this->assertStringContainsString('2 entities were not sent', $tester->getDisplay());
    }
}
