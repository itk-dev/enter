<?php

namespace App\Controller;

use App\SourceManager;
use App\Test\Map\MapConfig;
use App\Test\Map\SourceFeatures;
use App\Test\Source\TestDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Yaml\Yaml;

#[When('dev')]
#[When('test')]
#[Route('/test', name: 'test_')]
final class TestController extends AbstractController
{
    private const string FORMAT_JSON = 'json';
    private const string FORMAT_GEOJSON = 'geojson';

    private const string APPLICATION_GEOJSON = 'application/geo+json';
    private const string APPLICATION_JSON = 'application/json';

    private const string TYPE_ON_STREET_PARKING = 'https://smartdatamodels.org/dataModel.Parking/OnStreetParking';

    #[Route('/{path}', name: 'default', requirements: ['path' => Requirement::CATCH_ALL], methods: [Request::METHOD_GET], priority: -99)]
    public function index(?string $path = null): Response
    {
        return $this->render(null === $path ? 'test/index.html.twig' : sprintf('test/%s.html.twig', $path));
    }

    #[Route(
        path: '/data/{path}.{_format}',
        methods: [Request::METHOD_GET],
        requirements: [
            'path' => Requirement::CATCH_ALL,
            '_format' => 'json|geojson',
        ],
        defaults: ['_format' => self::FORMAT_JSON],
        priority: -98,
    )]
    public function data(Request $request, string $path, string $_format): Response
    {
        // Remove "/test/"
        $path = substr($request->getRequestUri(), 6);
        $path = realpath(__DIR__.'/../../tests/resources/'.$path);
        if (!file_exists($path)) {
            throw new NotFoundHttpException($path);
        }

        $contentType = match ($_format) {
            self::FORMAT_GEOJSON => self::APPLICATION_GEOJSON,
            default => self::APPLICATION_JSON,
        };

        return new BinaryFileResponse($path, headers: [
            'content-type' => $contentType,
        ]);
    }

    #[Route('/config', name: 'config', methods: [Request::METHOD_GET])]
    public function config(
        #[MapQueryParameter('type')]
        string $type,
        SourceManager $manager,
        MapConfig $mapConfig,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $configName = match ($type) {
            self::TYPE_ON_STREET_PARKING => 'Parking/OnStreetParking',
            default => throw new BadRequestHttpException('Invalid type'),
        };

        $base = Yaml::parseFile(__DIR__.'/../../tests/resources/config/'.$configName.'.yaml');

        return new JsonResponse($mapConfig->build(
            $base,
            $this->testSources($manager),
            static fn (string $id): string => $urlGenerator->generate('test_map', [
                'sourceId' => $id,
                '_format' => self::FORMAT_GEOJSON,
                'type' => $type,
            ])
        ));
    }

    /**
     * The features of one test source, as a layer of its own.
     *
     * Every source publishes into the same model, so the map cannot ask the
     * broker for one data set at a time: what separates them is an attribute
     * the broker expanded against a default vocabulary and can no longer be
     * queried on. Splitting them here is what lets each become a layer that
     * carries its own colour and can be switched off.
     */
    #[Route(
        path: '/map/{sourceId}.{_format}',
        name: 'map',
        methods: [Request::METHOD_GET],
        requirements: ['sourceId' => '[^/]+', '_format' => self::FORMAT_GEOJSON],
        defaults: ['_format' => self::FORMAT_GEOJSON],
    )]
    public function map(
        string $sourceId,
        #[MapQueryParameter('type')]
        string $type,
        SourceManager $manager,
        SourceFeatures $features,
    ): JsonResponse {
        $sources = $this->testSources($manager);

        // One id stands for all of them: what groups coinciding points can
        // only group what it holds, so counting across data sets means
        // serving them together.
        $collection = MapConfig::COMBINED_ID === $sourceId
            ? $features->forSources($sources, $type)
            : $features->forSource(
                $sources[$sourceId] ?? throw new NotFoundHttpException(sprintf('No test source "%s".', $sourceId)),
                $type
            );

        return new JsonResponse($collection, headers: ['content-type' => self::APPLICATION_GEOJSON]);
    }

    /**
     * @return array<string, \App\Source\SourceInterface>
     */
    private function testSources(SourceManager $manager): array
    {
        return array_filter(
            $manager->getSources(),
            static fn ($source): bool => $source->definition instanceof TestDefinition
        );
    }
}
