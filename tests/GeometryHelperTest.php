<?php

namespace App\Tests;

use App\GeometryHelper;
use PHPUnit\Framework\TestCase;
use proj4php\Point;

class GeometryHelperTest extends TestCase
{
    public function testTransformCoordinates(): void
    {
        $helper = new GeometryHelper();
        $input = [
            576933.0181395365, 6218035.22691216,
        ];
        $expected = [
            10.236808, 56.101227,
        ];
        $actual = $helper->transformCoordinates($input[0], $input[1], GeometryHelper::EPSG_GEO_JSON, GeometryHelper::EPSG_25832);
        $this->assertEquals($expected, $actual);
    }

    public function testTransformPoint(): void
    {
        $helper = new GeometryHelper();
        $input = new Point(
            576933.0181395365, 6218035.22691216,
        );
        $expected = new Point(
            10.236808, 56.101227,
        );
        $actual = $helper->transformPoint($input, GeometryHelper::EPSG_GEO_JSON, GeometryHelper::EPSG_25832);
        $this->assertEquals($expected, $actual);
    }

    public function testTransformGeoJsonGeometry(): void
    {
        $helper = new GeometryHelper();

        $input = [
            'type' => 'MultiPoint',
            'coordinates' => [
                [
                    576933.0181395365, 6218035.22691216,
                ],
            ],
        ];
        $expected = [
            'type' => 'MultiPoint',
            'coordinates' => [
                [
                    // https://epsg.io/transform#s_srs=25832&t_srs=4326&x=576933.0181395&y=6218035.2269122
                    10.236808, 56.101227,
                ],
            ],
        ];
        $actual = $helper->transformGeoJsonGeometry($input, from: GeometryHelper::EPSG_25832, to: GeometryHelper::EPSG_GEO_JSON);
        $this->assertSame($expected, $actual);
    }
}
