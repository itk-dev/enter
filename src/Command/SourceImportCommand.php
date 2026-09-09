<?php

namespace App\Command;

use App\Source\DataSourceReader;
use App\Source\SourceInterface;
use App\SourceImporterFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:source:import',
)]
class SourceImportCommand
{
    public function __invoke(SymfonyStyle $io,
        DataSourceReader $reader,
        SourceImporterFactory $factory,
        #[Argument]
        SourceInterface $source): int
    {
        $importer = $factory->getSourceImporter($source);
        $result = $importer->import($source);

        $io->success(sprintf(
            'Upserted %d entities into %s (HTTP %d).',
            $result->count,
            $result->brokerUrl,
            $result->status
        ));

        return Command::SUCCESS;
    }
}
