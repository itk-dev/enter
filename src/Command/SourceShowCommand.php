<?php

namespace App\Command;

use App\Source\SourceInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'app:source:show',
)]
class SourceShowCommand
{
    public function __invoke(SymfonyStyle $io,
        #[Argument('The source ID')]
        SourceInterface $source): int
    {
        $io->writeln(Yaml::dump($source->toArray(), PHP_INT_MAX));

        return Command::SUCCESS;
    }
}
