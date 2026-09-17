<?php

namespace App\Controller;

use App\SourceManager;
use App\Test\Map\FeatureMultiplier;
use App\Test\Map\MapConfig;
use App\Test\Map\MapLayers;
use App\Test\Map\MapSpec;
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
    public function index(
        ?string $path = null,
        #[MapQueryParameter('multiply')]
        int $multiply = 1,
    ): Response {
        return $this->render(null === $path ? 'test/index.html.twig' : sprintf('test/%s.html.twig', $path), [
            // The map pages draw the same data set as many times over as this
            // says, which is how any of them can be seen under a load the
            // sources do not yet produce.
            'multiply' => max(1, min($multiply, FeatureMultiplier::MOST)),
            'library' => $path ?? 'septima',
            // The one model the test map draws, so that the three pages ask
            // for the same data without each naming it.
            'type' => self::TYPE_ON_STREET_PARKING,
        ]);
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
        #[MapQueryParameter('multiply')]
        int $multiply = 1,
    ): JsonResponse {
        return new JsonResponse($mapConfig->build(
            $this->base($type),
            $this->testSources($manager),
            $this->dataUrl($urlGenerator, $type, $multiply)
        ));
    }

    /**
     * The same map, told to a library that is not the widget.
     *
     * The widget is configured in its own vocabulary; Leaflet and MapLibre are
     * handed the data sets, the colours and the view, and left to draw them
     * however they draw things. Which is the point: the page offers the three
     * side by side so they can be told apart by how they perform.
     */
    #[Route('/spec', name: 'spec', methods: [Request::METHOD_GET])]
    public function spec(
        #[MapQueryParameter('type')]
        string $type,
        SourceManager $manager,
        MapSpec $mapSpec,
        UrlGeneratorInterface $urlGenerator,
        #[MapQueryParameter('multiply')]
        int $multiply = 1,
    ): JsonResponse {
        return new JsonResponse($mapSpec->build(
            $this->base($type),
            $this->testSources($manager),
            $this->dataUrl($urlGenerator, $type, $multiply)
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
        FeatureMultiplier $multiplier,
        #[MapQueryParameter('multiply')]
        int $multiply = 1,
    ): JsonResponse {
        $sources = $this->testSources($manager);

        // One id stands for all of them: what groups coinciding points can
        // only group what it holds, so counting across data sets means
        // serving them together.
        $collection = MapLayers::COMBINED_ID === $sourceId
            ? $features->forSources($sources, $type)
            : $features->forSource(
                $sources[$sourceId] ?? throw new NotFoundHttpException(sprintf('No test source "%s".', $sourceId)),
                $type
            );

        return new JsonResponse(
            $multiplier->multiply($collection, $multiply),
            headers: ['content-type' => self::APPLICATION_GEOJSON]
        );
    }

    /**
     * The map configuration read from file, for the one type there is.
     *
     * @return array<string, mixed>
     */
    private function base(string $type): array
    {
        $configName = match ($type) {
            self::TYPE_ON_STREET_PARKING => 'Parking/OnStreetParking',
            default => throw new BadRequestHttpException('Invalid type'),
        };

        return Yaml::parseFile(__DIR__.'/../../tests/resources/config/'.$configName.'.yaml');
    }

    /**
     * Where a map fetches one data set's features.
     *
     * @return callable(string): string
     */
    private function dataUrl(UrlGeneratorInterface $urlGenerator, string $type, int $multiply): callable
    {
        return static fn (string $id): string => $urlGenerator->generate('test_map', [
            'sourceId' => $id,
            '_format' => self::FORMAT_GEOJSON,
            'type' => $type,
            // Left off entirely when nothing is being multiplied, so the
            // ordinary map is fetched from the URL it has always had.
            ...(1 < $multiply ? ['multiply' => $multiply] : []),
        ]);
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
