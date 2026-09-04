<?php

declare(strict_types=1);

namespace App\Command;

use App\Broker\NgsiLdBroker;
use App\Source\SourceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsCommand(
    name: 'app:import',
    description: 'Convert a data source to NGSI-LD and upsert it into the context broker.',
)]
final class ImportCommand extends Command
{
    private const JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    /**
     * @param iterable<SourceInterface> $sources
     */
    public function __construct(
        #[AutowireIterator('app.source')]
        private readonly iterable $sources,
        private readonly NgsiLdBroker $broker,
        #[Autowire(env: 'ENTER_NGSI_CONTEXT_URLS')]
        private readonly string $contextUrls,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::OPTIONAL, 'Source to import. Omit to list the available sources.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the NGSI-LD payload instead of sending it.')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Import at most this many entities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sources = [];
        foreach ($this->sources as $source) {
            $sources[$source->key()] = $source;
        }

        if ([] === $sources) {
            $io->error('No data sources are registered.');
            $io->listing([
                'A source must implement App\Source\SourceInterface.',
                'Implementations are picked up automatically — check the class exists and is not excluded from the container.',
            ]);

            return Command::FAILURE;
        }

        $key = $input->getArgument('source');

        if (null === $key) {
            // Options only make sense together with a source. Listing the
            // sources and exiting successfully would look like an import ran.
            if ($input->getOption('dry-run') || null !== $input->getOption('limit')) {
                $io->error(\sprintf(
                    'No source given. Available: %s.',
                    implode(', ', array_keys($sources))
                ));

                return Command::INVALID;
            }

            $io->section('Available sources');
            $io->listing(array_keys($sources));

            return Command::SUCCESS;
        }

        if (!isset($sources[$key])) {
            $io->error(\sprintf('Unknown source "%s". Available: %s.', $key, implode(', ', array_keys($sources))));

            return Command::INVALID;
        }

        $limit = null !== $input->getOption('limit') ? max(1, (int) $input->getOption('limit')) : null;
        $contexts = $this->contexts();

        $payload = [];
        foreach ($sources[$key]->entities() as $entity) {
            $payload[] = $entity->toArray($contexts);

            if (null !== $limit && \count($payload) >= $limit) {
                break;
            }
        }

        // A source that yields nothing is almost always misconfigured rather
        // than genuinely empty, and it fails silently by construction: a
        // record skipped for a missing field looks exactly like a feed with no
        // records. Fail loudly so it cannot be mistaken for a successful run.
        if ([] === $payload) {
            $io->error(\sprintf('Source "%s" produced no entities.', $key));
            $io->text(
                'The source ran to completion without raising an exception, so every record was '
                .'discarded by the source\'s own guards rather than failing. Verbosity flags will '
                .'not reveal more: there is no exception to show.'
            );
            $io->listing([
                'Does the configured path or URL point at the intended document?',
                'Does the document match the shape the source expects — envelope, nesting, field names?',
                'Which guard returns early — a missing identifier, or a missing geometry?',
            ]);

            return Command::FAILURE;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(json_encode($payload, self::JSON_FLAGS));
            $io->note(\sprintf('Dry run: %d entities were not sent.', \count($payload)));

            return Command::SUCCESS;
        }

        try {
            $status = $this->broker->upsert($payload);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Upserted %d entities into %s (HTTP %d).',
            \count($payload),
            $this->broker->brokerUrl(),
            $status
        ));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function contexts(): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $this->contextUrls))));
    }
}
