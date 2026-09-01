<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DataController extends AbstractController
{
    private const string FORMAT_JSON = 'json';
    private const string FORMAT_GEOJSON = 'geojson';

    private const string APPLICATION_GEOJSON = 'application/geo+json';

    #[Route(
        path: '/data/{path}.{_format}',
        name: 'app_data',
        methods: [Request::METHOD_GET],
        requirements: [
            'path' => '[^.]+',
            '_format' => 'json|geojson',
        ],
        defaults: ['_format' => self::FORMAT_JSON],
    )]
    public function index(Request $request, string $path, string $_format,
        HttpClientInterface $brokerClient,
    ): JsonResponse {
        $path = '/'.ltrim($path, '/');
        $headers = $request->headers->all();
        // Set "accept" header for clients that cannot do it themselves.
        if (self::FORMAT_GEOJSON === $_format) {
            $headers['accept'] = self::APPLICATION_GEOJSON;
        }
        // Exclude some headers from the proxy call to the broker.
        $headers = array_filter(
            $headers,
            static fn (string $name) => !in_array($name, ['authorization', 'host'])
                && !str_starts_with($name, 'x-forwarded-'),
            ARRAY_FILTER_USE_KEY
        );
        $response = $brokerClient->request($request->getMethod(), $path, [
            'query' => $request->query->all(),
            'headers' => $headers,
        ]);

        return new JsonResponse(
            data: $response->getContent(),
            status: $response->getStatusCode(),
            headers: $response->getHeaders(),
            json: true,
        );
    }

    #[Route('/test', name: 'data_test')]
    public function test(): JsonResponse
    {
        $data = Yaml::parseFile(__DIR__.'/data.yaml');

        return new JsonResponse($data);
    }
}
