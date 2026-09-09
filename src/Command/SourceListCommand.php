<?php

namespace App\Command;

use App\SourceManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:source:list',
)]
class SourceListCommand
{
    public function __invoke(
        SymfonyStyle $io,
        SourceManager $manager,
    ): int {
        $sources = $manager->getSources();

        $io->writeln(sprintf('#sources: %d', \count($sources)));
        foreach ($sources as $source) {
            $io->writeln((string) $source);
        }

        return Command::SUCCESS;
    }
}
