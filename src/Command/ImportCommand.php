<?php

declare(strict_types=1);

namespace App\Command;

use App\Import\DataSourceImporter;
use App\Import\Exception\EmptySourceException;
use App\Import\Exception\UnknownSourceException;
use App\Import\Exception\UpsertFailedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The console front for DataSourceImporter: selects a source, and turns what
 * the import raises into an exit code and something readable.
 */
#[AsCommand(
    name: 'app:import',
    description: 'Convert a data source to NGSI-LD and upsert it into the context broker.',
)]
final class ImportCommand extends Command
{
    private const JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly DataSourceImporter $importer,
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

        $keys = $this->importer->keys();

        if ([] === $keys) {
            $io->error('No data sources are registered.');
            $io->listing([
                'A source must implement App\Source\SourceInterface.',
                'Implementations are picked up automatically — check the class exists and is not excluded from the container.',
            ]);

            return Command::FAILURE;
        }

        $argument = $input->getArgument('source');

        if (null === $argument) {
            // Options only make sense together with a source. Listing the
            // sources and exiting successfully would look like an import ran.
            if ($input->getOption('dry-run') || null !== $input->getOption('limit')) {
                $io->error(\sprintf(
                    'No source given. Available: %s.',
                    implode(', ', $keys)
                ));

                return Command::INVALID;
            }

            $io->section('Available sources');
            $io->listing($keys);

            return Command::SUCCESS;
        }

        $key = (string) $argument;
        $limit = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        try {
            if ($input->getOption('dry-run')) {
                $payload = $this->importer->payload($key, $limit);

                $output->writeln(json_encode($payload, self::JSON_FLAGS));
                $io->note(\sprintf('Dry run: %d entities were not sent.', \count($payload)));

                return Command::SUCCESS;
            }

            $result = $this->importer->import($key, $limit);
        } catch (UnknownSourceException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        } catch (EmptySourceException $exception) {
            $io->error($exception->getMessage());
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
        } catch (UpsertFailedException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            'Upserted %d entities into %s (HTTP %d).',
            $result->count,
            $result->brokerUrl,
            $result->status
        ));

        return Command::SUCCESS;
    }
}
