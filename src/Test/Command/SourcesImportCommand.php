<?php

namespace App\Test\Command;

use App\Source\SourceInterface;
use App\SourceManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'test:sources:import',
    description: 'Import all test sources',
)]
#[When('dev')]
class SourcesImportCommand
{
    public function __invoke(
        SymfonyStyle $io,
        SourceManager $manager,
        HttpClientInterface $httpClient,
        Filesystem $filesystem,
        OutputInterface $output,
        Application $application,
        #[Option(description: 'If specified, all successfully read test sources will be imported into the broker')]
        bool $import = false,
    ): int {
        /** @var SourceInterface[] $testSources */
        $testSources = array_filter($manager->getSources(), static fn (SourceInterface $source) => str_starts_with($source->definition->id, 'test:'));
        foreach ($testSources as $source) {
            try {
                $io->section($source);
                $input = new ArrayInput([
                    'command' => 'app:source:import',
                    'source' => $source->definition->id,
                ]);
                $input->setInteractive(false);

                $application->doRun($input, $output);
            } catch (\Exception $e) {
                $io->error($e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
