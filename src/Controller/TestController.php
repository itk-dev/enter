<?php

namespace App\Controller;

use App\SourceManager;
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

    /**
     * The developer map: what the test sources published, as the broker
     * holds it, whichever models they publish into.
     */
    #[Route('', name: 'default', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('test/index.html.twig');
    }

    #[Route(
        path: '/data/{path}.{_format}',
        methods: [Request::METHOD_GET],
        requirements: [
            'path' => Requirement::CATCH_ALL,
            '_format' => 'json|geojson',
        ],
        defaults: ['_format' => self::FORMAT_JSON],
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

    /**
     * The map, described for the page to draw: the data sets, their colours
     * and sizes, and where it opens.
     */
    #[Route('/spec', name: 'spec', methods: [Request::METHOD_GET])]
    public function spec(
        SourceManager $manager,
        MapSpec $mapSpec,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        return new JsonResponse($mapSpec->build(
            $this->base(),
            $this->testSources($manager),
            $this->dataUrl($urlGenerator)
        ));
    }

    /**
     * The features of one test source, as a layer of its own.
     *
     * The broker holds entities by type, not by where they came from: two
     * sources publishing into the one model are only told apart by the access
     * URL each stamped on its entities. Splitting them here is what lets each
     * become a layer that carries its own colour and can be switched off.
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
        SourceManager $manager,
        SourceFeatures $features,
    ): JsonResponse {
        $sources = $this->testSources($manager);

        // One id stands for all of them: what groups coinciding points can
        // only group what it holds, so counting across data sets means
        // serving them together.
        $collection = MapLayers::COMBINED_ID === $sourceId
            ? $features->forSources($sources)
            : $features->forSource(
                $sources[$sourceId] ?? throw new NotFoundHttpException(sprintf('No test source "%s".', $sourceId))
            );

        return new JsonResponse($collection, headers: ['content-type' => self::APPLICATION_GEOJSON]);
    }

    /**
     * The map configuration read from file.
     *
     * @return array<string, mixed>
     */
    private function base(): array
    {
        return Yaml::parseFile(__DIR__.'/../../tests/resources/config/map.yaml');
    }

    /**
     * Where the map fetches one data set's features.
     *
     * @return callable(string): string
     */
    private function dataUrl(UrlGeneratorInterface $urlGenerator): callable
    {
        return static fn (string $id): string => $urlGenerator->generate('test_map', [
            'sourceId' => $id,
            '_format' => self::FORMAT_GEOJSON,
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
