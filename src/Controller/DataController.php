<?php

namespace App\Controller;

use App\Broker\PagedBrokerReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DataController extends AbstractController
{
    private const string FORMAT_JSON = 'json';
    private const string FORMAT_GEOJSON = 'geojson';

    private const string APPLICATION_GEOJSON = 'application/geo+json';
    private const string APPLICATION_JSON = 'application/json';

    /**
     * Asks for the complete set rather than the page the broker defaults to.
     */
    private const string PARAMETER_ALL = 'all';

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
        PagedBrokerReader $pagedReader,
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
        $query = $request->query->all();

        // Opting in is left to the caller: a client that pages for itself
        // passes its own limit and offset, and answering those with the whole
        // set instead would break it.
        if ($request->query->getBoolean(self::PARAMETER_ALL)) {
            unset($query[self::PARAMETER_ALL]);

            return new JsonResponse(
                data: $pagedReader->readAll($path, $query, $headers),
                headers: ['content-type' => match ($_format) {
                    self::FORMAT_GEOJSON => self::APPLICATION_GEOJSON,
                    default => self::APPLICATION_JSON,
                }],
            );
        }

        $response = $brokerClient->request($request->getMethod(), $path, [
            'query' => $query,
            'headers' => $headers,
        ]);

        return new JsonResponse(
            data: $response->getContent(),
            status: $response->getStatusCode(),
            headers: $response->getHeaders(),
            json: true,
        );
    }
}
