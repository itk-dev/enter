<?php

declare(strict_types=1);

namespace App\Test\Source\FindToilet;

use App\Geo\Wgs84Transformer;
use App\Ngsi\NgsiEntity;
use App\Source\AbstractSource;
use App\Source\DataType;
use App\Test\Source\TestDefinition;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Public toilets in Aarhus Municipality listed on findtoilet.dk.
 */
#[When('dev')]
#[When('test')]
#[TestDefinition(
    // By convention the ID as a test source must start with `test:`
    id: 'test:findtoilet-public-toilet',
    title: 'Test: Offentlige toiletter (FindToilet), Aarhus Kommune',
    accessUrl: 'http://nginx:8080/test/data/beta.findtoilet.dk/api/v3/toilets?findtoilet-public-toilet',
    dataType: DataType::FindToilet,
    mediaType: 'application/json',
    crs: 'EPSG:4326',
    model: 'PublicToilet',
    contextUrl: 'https://schema.org/docs/jsonldcontext.json',
    omittedFields: [
        'manned' => 'Constant "0" throughout the export.',
        'payment' => 'Constant "0" throughout the export.',
        'kontakt' => 'The service\'s own generic contact address, not a fact about the toilet.',
        'kontakttitle' => 'Duplicate of kontakt.',
        'region' => 'Constant for this municipality-scoped feed; the data set\'s own scope.',
        'needle_container' => 'Coded 0/1/2 with no documented meaning.',
        'changing_table' => 'Coded 0/1/2 with no documented meaning.',
    ],
    dataUrlBase: 'https://beta.findtoilet.dk/api/v3/toilets',
    dataUrlQuery: ['tid' => 8],
)]
final class TestPublicToilet extends AbstractSource
{
    /**
     * Maps one feed record onto an NgsiEntity.
     *
     * @param array<string, mixed> $data findtoilet.dk API v3 toilet record
     */
    public function createNgsiEntity(array $data, Wgs84Transformer $transformer): ?NgsiEntity
    {
        $id = $data['id'] ?? null;
        if (null === $id || '' === $id) {
            return null;
        }

        $location = \is_array($data['location'] ?? null) ? $data['location'] : [];
        $latitude = $location['lat'] ?? null;
        $longitude = $location['long'] ?? null;
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $geometry = ['type' => 'Point', 'coordinates' => [(float) $longitude, (float) $latitude]];

        $entity = new NgsiEntity(
            \sprintf('urn:ngsi-ld:%s:aarhus-toilet-findtoilet-%s', $this->definition->model, $id),
            $this->definition->model
        );

        [$placement, $openingHours] = $this->description((string) ($data['description'] ?? ''));

        return $entity
            ->setProperty('name', trim((string) ($data['title'] ?? '')))
            ->setProperty('address', trim((string) ($location['street'] ?? '')))
            ->setProperty('image', array_column(\is_array($data['images'] ?? null) ? $data['images'] : [], 'url'))
            ->setProperty('source', $this->definition->accessUrl)
            ->geoProperty('location', $transformer->transformGeometry($this->definition->crs, $geometry))
            ->additionalInformation([
                'category' => trim((string) ($data['type'] ?? '')),
                'placement' => $placement,
                'openingHours' => $openingHours,
                'tap' => trim((string) ($data['tap'] ?? '')),
            ]);
    }

    /**
     * @return array{0: string, 1: string} [placement, openingHours]
     */
    private function description(string $html): array
    {
        $placement = '';
        $openingHours = '';

        if (preg_match('/<b>Placering:<\/b>\s*([^\r\n]*)/u', $html, $matches)) {
            $placement = trim($matches[1]);
        }

        if (preg_match('/<b>Åbningstider:<\/b>\s*([^\r\n]*)/u', $html, $matches)) {
            $openingHours = trim($matches[1]);
        }

        return [$placement, $openingHours];
    }
}
