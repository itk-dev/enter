<?php

namespace App\Command;

use App\GeometryHelper;
use App\GeometryHelper\Crs;
use proj4php\Point;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:broker:import:geojson',
)]
class BrokerImportGeoJson
{
    private const string RESERVED_PROPERTY_NAME_PREFIX = '_prop__';

    public function __invoke(
        SymfonyStyle $io,
        GeometryHelper $helper,
        HttpClientInterface $brokerClient,
        HttpClientInterface $httpClient,
        GeometryHelper $geometryHelper,
        #[Argument()]
        string $entityType,
        #[Argument()]
        string $dataUrl,
    ): int {
        $data = $httpClient->request(Request::METHOD_GET, $dataUrl)->toArray();
        $features = $data['features'] ?? null;
        $sourceProjection = $data['crs']['properties']['name'] ?? null;
        $sourceProjection = null !== $sourceProjection
            ? Crs::from($sourceProjection)
            : Crs::geoJson();

        $count = count($features);
        if ($count > 0) {
            $io->writeln(match ($count) {
                1 => sprintf('Creating one %s entity …', $entityType),
                default => sprintf('Creating %d %s entities …', $count, $entityType),
            });
            $io->progressStart($count);
            foreach ($features as $index => $feature) {
                $entity = [
                    'type' => $entityType,
                    'id' => sprintf('%s:%04d', $entityType, $index),
                ];
                foreach ($feature['properties'] as $name => $value) {
                    if (null === $value) {
                        continue;
                    }
                    if (in_array($name, ['id', 'type'])) {
                        $name = self::RESERVED_PROPERTY_NAME_PREFIX.$name;
                    }
                    $entity[$name] = [
                        'type' => 'Property',
                        'value' => $value,
                    ];
                }
                $geometry = $geometryHelper->transformGeoJsonGeometry($feature['geometry'], from: $sourceProjection);
                $entity['geometry'] = $geometry;
                $centroid = $geometryHelper->getCentroid($geometry);
                // Compute location from geometry. The location must be a single point (the "spatial location" of the entity).
                $entity['location'] = [
                    'type' => 'geo:json',
                    'value' => [
                        'type' => 'Point',
                        'coordinates' => [$centroid->x, $centroid->y],
                    ],
                ];
                $response = $brokerClient->request(Request::METHOD_POST, '/ngsi-ld/v1/entities', [
                    'json' => $entity,
                ]);

                if (Response::HTTP_CREATED !== $response->getStatusCode()) {
                    $io->error($response->getContent(false));
                    $io->writeln(json_encode($entity, JSON_PRETTY_PRINT));

                    return Command::FAILURE;
                }
                $io->progressAdvance();
            }
            $io->progressFinish();
        }

        return Command::SUCCESS;
    }
}
