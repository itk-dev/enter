<?php

namespace App\Command;

use App\Source\DataSourceReader;
use App\Source\SourceInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:source:read',
)]
class SourceReadCommand
{
    public function __invoke(SymfonyStyle $io,
        DataSourceReader $reader,
        #[Argument]
        SourceInterface $source): int
    {
        throw new \RuntimeException('Lazy programmer exception!');
        // $reader = $readerFactory->getReader($source);
        // $reader->read($source);
        // …
    }
}
