<?php

namespace Akeneo\PimEnterprise\tests\v2_0\Api\Product;

use Akeneo\Pim\Pagination\PageInterface;
use Akeneo\Pim\Pagination\ResourceCursorInterface;
use Akeneo\Pim\Search\SearchBuilder;
use Akeneo\PimEnterprise\tests\Common\Api\PublishedProduct\AbstractPublishedProductApiTestCase;

/**
 * @author    Damien Carcel <damien.carcel@gmail.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ListPublishedProductApiIntegration extends AbstractPublishedProductApiTestCase
{
    public function testListPerPage()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $expectedPublishedProducts = $this->getExpectedPublishedProducts();
        $baseUri = $this->getConfiguration()['api']['baseUri'];

        $firstPage = $api->listPerPage(5);
        $this->assertInstanceOf(PageInterface::class, $firstPage);
        $this->assertNull($firstPage->getCount());
        $this->assertNull($firstPage->getPreviousLink());
        $this->assertNull($firstPage->getPreviousPage());
        $this->assertFalse($firstPage->hasPreviousPage());
        $this->assertTrue($firstPage->hasNextPage());
        $this->assertSame(
            $baseUri.'/api/rest/v1/published-products?page=2&with_count=false&pagination_type=page&limit=5',
            $firstPage->getNextLink()
        );

        $firstPageProducts = $this->sanitizePublishedProductData($firstPage->getItems());
        $firstPageExpectedPublishedProducts = $this->sanitizePublishedProductData(
            array_slice($expectedPublishedProducts, 0, 5)
        );

        $this->assertSameContent($firstPageExpectedPublishedProducts, $firstPageProducts);

        $secondPage = $firstPage->getNextPage();
        $this->assertInstanceOf(PageInterface::class, $secondPage);
        $this->assertTrue($secondPage->hasPreviousPage());
        $this->assertTrue($secondPage->hasNextPage());
        $this->assertSame(
            $baseUri.'/api/rest/v1/published-products?page=1&with_count=false&pagination_type=page&limit=5',
            $secondPage->getPreviousLink()
        );
        $this->assertSame(
            $baseUri.'/api/rest/v1/published-products?page=3&with_count=false&pagination_type=page&limit=5',
            $secondPage->getNextLink()
        );

        $secondPageProducts = $this->sanitizePublishedProductData($secondPage->getItems());
        $secondPageExpectedPublishedProducts = $this->sanitizePublishedProductData(
            array_slice($expectedPublishedProducts, 5, 5)
        );

        $this->assertSameContent($secondPageExpectedPublishedProducts, $secondPageProducts);

        $lastPage = $secondPage->getNextPage();
        $this->assertInstanceOf(PageInterface::class, $lastPage);
        $this->assertTrue($lastPage->hasPreviousPage());
        $this->assertFalse($lastPage->hasNextPage());
        $this->assertNull($lastPage->getNextPage());
        $this->assertNull($lastPage->getNextLink());
        $this->assertSame(
            $baseUri.'/api/rest/v1/published-products?page=2&with_count=false&pagination_type=page&limit=5',
            $lastPage->getPreviousLink()
        );

        $products = $lastPage->getItems();
        $this->assertCount(0, $products);

        $previousPage = $lastPage->getPreviousPage();
        $this->assertInstanceOf(PageInterface::class, $previousPage);
        $this->assertSame($secondPage->getItems(), $previousPage->getItems());
    }

    public function testListPerPageWithCount()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $baseUri = $this->getConfiguration()['api']['baseUri'];

        $firstPage = $api->listPerPage(2, true);
        $this->assertInstanceOf(PageInterface::class, $firstPage);
        $this->assertSame(10, $firstPage->getCount());
        $this->assertSame(
            $baseUri.'/api/rest/v1/published-products?page=2&with_count=true&pagination_type=page&limit=2',
            $firstPage->getNextLink()
        );
    }

    public function testListPerPageWithSpecificQueryParameter()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $expectedPublishedProducts = $this->getExpectedPublishedProducts();
        $baseUri = $this->getConfiguration()['api']['baseUri'];

        $firstPage = $api->listPerPage(2, false, ['foo' => 'bar']);

        $this->assertInstanceOf(PageInterface::class, $firstPage);
        $this->assertSame(
            $baseUri.'/api/rest/v1/published-products?page=2&with_count=false&pagination_type=page&limit=2&foo=bar',
            $firstPage->getNextLink()
        );

        $expectedPublishedProducts = $this->sanitizePublishedProductData(array_slice($expectedPublishedProducts, 0, 2));
        $actualProducts = $this->sanitizePublishedProductData($firstPage->getItems());

        $this->assertSameContent($expectedPublishedProducts, $actualProducts);
    }

    public function testAll()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->all();

        $this->assertInstanceOf(ResourceCursorInterface::class, $products);

        $expectedPublishedProducts = $this->sanitizePublishedProductData($this->getExpectedPublishedProducts());
        $products = $this->sanitizePublishedProductData(iterator_to_array($products));

        $this->assertSameContent($expectedPublishedProducts, $products);
    }

    public function testAllWithUselessQueryParameter()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->all(10, ['foo' => 'bar']);

        $this->assertInstanceOf(ResourceCursorInterface::class, $products);

        $expectedPublishedProducts = $this->sanitizePublishedProductData($this->getExpectedPublishedProducts());
        $products = $this->sanitizePublishedProductData(iterator_to_array($products));

        $this->assertSameContent($expectedPublishedProducts, $products);
    }

    public function testSearchOnProductProperties()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->listPerPage(
            10,
            true,
            [
                'search' => [
                    'family' => [
                        [
                            'operator' => 'IN',
                            'value' => ['sneakers'],
                        ],
                    ],
                ],
            ]
        );

        $this->assertSame(1, $products->getCount());

        $expectedProduct = $this->sanitizePublishedProductData($this->getExpectedProductByIdentifier('black_sneakers'));
        $actualProduct = $this->sanitizePublishedProductData($products->getItems()[0]);

        $this->assertSameContent($expectedProduct, $actualProduct);
    }

    public function testSearchOnProductValues()
    {
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->listPerPage(
            10,
            true,
            [
                'search' => [
                    'color' => [
                        [
                            'operator' => 'IN',
                            'value' => ['maroon'],
                        ],
                    ],
                ],
            ]
        );

        $this->assertSame(2, $products->getCount());

        $products = $products->getItems();

        $expectedProduct = $this->sanitizePublishedProductData($this->getExpectedProductByIdentifier('small_boot'));
        $actualProduct = $this->sanitizePublishedProductData($products[0]);
        $this->assertSameContent($expectedProduct, $actualProduct);

        $expectedProduct = $this->sanitizePublishedProductData($this->getExpectedProductByIdentifier('docks_maroon'));
        $actualProduct = $this->sanitizePublishedProductData($products[1]);
        $this->assertSameContent($expectedProduct, $actualProduct);
    }

    public function testSearchWithSeveralFiltersUsingSearchBuilder()
    {
        $api = $this->createClient()->getPublishedProductApi();

        $searchBuilder = new SearchBuilder();
        $searchBuilder
            ->addFilter('family', 'IN', ['sneakers'])
            ->addFilter('color', 'IN', ['white', 'black']);

        $products = $api->listPerPage(10, true, ['search' => $searchBuilder->getFilters()]);

        $this->assertSame(1, $products->getCount());

        $expectedProduct = $this->sanitizePublishedProductData($this->getExpectedProductByIdentifier('black_sneakers'));
        $actualProduct = $this->sanitizePublishedProductData($products->getItems()[0]);

        $this->assertSameContent($expectedProduct, $actualProduct);
    }

    public function testAllWithSelectedLocales()
    {
        $baseUri = $this->getConfiguration()['api']['baseUri'];
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->all(
            10,
            [
                'locales' => 'fr_FR',
                'search' => [
                    'categories' => [
                        [
                            'operator' => 'IN',
                            'value' => ['sandals'],
                        ],
                    ],
                ],
            ]
        );

        $expectedProduct = $this->sanitizePublishedProductData(
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/dance_shoe',
                    ],
                ],
                'identifier' => 'dance_shoe',
                'family' => 'sandals',
                'groups' => [],
                'categories' => [
                    'sandals',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'greem',
                        ],
                    ],
                    'description' => [
                        [
                            'locale' => 'fr_FR',
                            'scope' => 'ecommerce',
                            'data' => 'Dansez toute la nuit !',
                        ],
                    ],
                    'destocking_date' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '2017-06-30T00:00:00+00:00',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Converse',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'fr_FR',
                            'scope' => null,
                            'data' => 'Chaussure de dance',
                        ],
                    ],
                    'side_view' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '0/4/1/a/041a1570a3cefa2c79872c433f60c2e6301afd60_Akeneo_logo.png',
                            '_links' => [
                                'download' => [
                                    'href' => $baseUri.'/api/rest/v1/media-files/0/4/1/a/041a1570a3cefa2c79872c433f60c2e6301afd60_Akeneo_logo.png/download',
                                ],
                            ],
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '40',
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '99.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '90.50',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ]
        );

        $actualProduct = $this->sanitizePublishedProductData(iterator_to_array($products)[0]);

        $this->assertSameContent($expectedProduct, $actualProduct);
    }

    public function testAllWithSelectedAttributes()
    {
        $baseUri = $this->getConfiguration()['api']['baseUri'];
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->all(1, ['attributes' => 'name,color']);

        $expectedProduct = $this->sanitizePublishedProductData(
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/small_boot',
                    ],
                ],
                'identifier' => 'small_boot',
                'family' => 'boots',
                'groups' => [
                    'similar_boots',
                ],
                'categories' => [
                    'summer_collection',
                    'winter_boots',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'maroon',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Small boot',
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ]
        );

        $actualProduct = $this->sanitizePublishedProductData($products->current());

        $this->assertSameContent($expectedProduct, $actualProduct);
    }

    public function testAllWithSelectedScope()
    {
        $baseUri = $this->getConfiguration()['api']['baseUri'];
        $api = $this->createClient()->getPublishedProductApi();
        $products = $api->all(
            10,
            [
                'scope' => 'mobile',
                'search' => [
                    'family' => [
                        [
                            'operator' => 'IN',
                            'value' => ['sneakers'],
                        ],
                    ],
                ],
            ]
        );

        $expectedProduct = $this->sanitizePublishedProductData(
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/black_sneakers',
                    ],
                ],
                'identifier' => 'black_sneakers',
                'family' => 'sneakers',
                'groups' => [],
                'categories' => [
                    'summer_collection',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'black',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Converse',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Black sneakers',
                        ],
                    ],
                    'side_view' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '3/d/8/9/3d89680c85a835b5b0a5bd0e7dd2515b55a4b657_Ziggy_certification.jpg',
                            '_links' => [
                                'download' => [
                                    'href' => $baseUri.'/api/rest/v1/media-files/3/d/8/9/3d89680c85a835b5b0a5bd0e7dd2515b55a4b657_Ziggy_certification.jpg/download',
                                ],
                            ],
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '41',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'dry',
                                'wet',
                            ],
                        ],
                    ],
                    'length' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'amount' => 14,
                                'unit' => 'CENTIMETER',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '40.00',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '42.00',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ]
        );

        $actualProduct = $this->sanitizePublishedProductData(iterator_to_array($products)[0]);

        $this->assertSameContent($expectedProduct, $actualProduct);
    }

    /**
     * @param string $identifier
     *
     * @return mixed
     */
    protected function getExpectedProductByIdentifier($identifier)
    {
        foreach ($this->getExpectedPublishedProducts() as $product) {
            if ($identifier === $product['identifier']) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    protected function getExpectedPublishedProducts()
    {
        $baseUri = $this->getConfiguration()['api']['baseUri'];

        return [
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/small_boot',
                    ],
                ],
                'identifier' => 'small_boot',
                'family' => 'boots',
                'groups' => [
                    'similar_boots',
                ],
                'categories' => [
                    'summer_collection',
                    'winter_boots',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'maroon',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'TimberLand',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Small boot',
                        ],
                    ],
                    'side_view' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '4/9/1/2/4912ff747899d2ade4d7f8d965b6d3d9a771655c_Ziggy.png',
                            '_links' => [
                                'download' => [
                                    'href' => $baseUri.'/api/rest/v1/media-files/4/9/1/2/4912ff747899d2ade4d7f8d965b6d3d9a771655c_Ziggy.png/download',
                                ],
                            ],
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '110.00',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '110.00',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/docks_white',
                    ],
                ],
                'identifier' => 'docks_white',
                'family' => 'boots',
                'groups' => ['caterpillar_boots'],
                'categories' => [
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'white',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Caterpillar',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Docks',
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '44',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'cold',
                                'snowy',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '149.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '149.49',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/medium_boot',
                    ],
                ],
                'identifier' => 'medium_boot',
                'family' => 'boots',
                'groups' => [
                    'similar_boots',
                ],
                'categories' => [
                    'winter_boots',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'white',
                        ],
                    ],
                    'description' => [
                        [
                            'locale' => 'en_US',
                            'scope' => 'ecommerce',
                            'data' => 'The medium boot.',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Caterpillar',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Medium boot',
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '41',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'dry',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '100.50',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '100.49',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/big_boot',
                    ],
                ],
                'identifier' => 'big_boot',
                'family' => 'boots',
                'groups' => [
                    'similar_boots',
                ],
                'categories' => [
                    'summer_collection',
                    'winter_boots',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'black',
                        ],
                    ],
                    'description' => [
                        [
                            'locale' => 'en_US',
                            'scope' => 'ecommerce',
                            'data' => 'Big boot for a big foot.',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'TimberLand',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Big boot !',
                        ],
                    ],
                    'side_view' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '8/2/4/3/824336b53f78c7badf3538793ab3ba92ffa3c120_Ziggy_certification.jpg',
                            '_links' => [
                                'download' => [
                                    'href' => $baseUri.'/api/rest/v1/media-files/8/2/4/3/824336b53f78c7badf3538793ab3ba92ffa3c120_Ziggy_certification.jpg/download',
                                ],
                            ],
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '37',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'dry',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '120.00',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '110.00',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [
                    'PACK' => [
                        'groups' => [],
                        'products' => [],
                    ],
                    'SUBSTITUTION' => [
                        'groups' => [],
                        'products' => [],
                    ],
                    'UPSELL' => [
                        'groups' => [],
                        'products' => [],
                    ],'X_SELL' => [
                        'groups' => [],
                        'products' => [
                            'small_boot',
                            'medium_boot',
                        ],
                    ],
                ],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/black_sneakers',
                    ],
                ],
                'identifier' => 'black_sneakers',
                'family' => 'sneakers',
                'groups' => [],
                'categories' => [
                    'summer_collection',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'black',
                        ],
                    ],
                    'description' => [
                        [
                            'locale' => 'en_US',
                            'scope' => 'ecommerce',
                            'data' => 'The famous sneakers',
                        ],
                        [
                            'locale' => 'fr_FR',
                            'scope' => 'ecommerce',
                            'data' => 'Les fameuses sneakers',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Converse',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Black sneakers',
                        ],
                        [
                            'locale' => 'fr_FR',
                            'scope' => null,
                            'data' => 'Sneakers',
                        ],
                    ],
                    'side_view' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '3/d/8/9/3d89680c85a835b5b0a5bd0e7dd2515b55a4b657_Ziggy_certification.jpg',
                            '_links' => [
                                'download' => [
                                    'href' => $baseUri.'/api/rest/v1/media-files/3/d/8/9/3d89680c85a835b5b0a5bd0e7dd2515b55a4b657_Ziggy_certification.jpg/download',
                                ],
                            ],
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '41',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'dry',
                                'wet',
                            ],
                        ],
                    ],
                    'length' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'amount' => 14,
                                'unit' => 'CENTIMETER',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '40.00',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '42.00',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/dance_shoe',
                    ],
                ],
                'identifier' => 'dance_shoe',
                'family' => 'sandals',
                'groups' => [],
                'categories' => [
                    'sandals',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'greem',
                        ],
                    ],
                    'description' => [
                        [
                            'locale' => 'en_US',
                            'scope' => 'ecommerce',
                            'data' => 'To dance all night !',
                        ],
                        [
                            'locale' => 'fr_FR',
                            'scope' => 'ecommerce',
                            'data' => 'Dansez toute la nuit !',
                        ],
                    ],
                    'destocking_date' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '2017-06-30T00:00:00+00:00',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Converse',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Dance shoe',
                        ],
                        [
                            'locale' => 'fr_FR',
                            'scope' => null,
                            'data' => 'Chaussure de dance',
                        ],
                    ],
                    'side_view' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '0/4/1/a/041a1570a3cefa2c79872c433f60c2e6301afd60_Akeneo_logo.png',
                            '_links' => [
                                'download' => [
                                    'href' => $baseUri.'/api/rest/v1/media-files/0/4/1/a/041a1570a3cefa2c79872c433f60c2e6301afd60_Akeneo_logo.png/download',
                                ],
                            ],
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '40',
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '99.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '90.50',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/docks_black',
                    ],
                ],
                'identifier' => 'docks_black',
                'family' => 'boots',
                'groups' => ['caterpillar_boots'],
                'categories' => [
                    'winter_boots',
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'black',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Caterpillar',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Docks',
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '42',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'cold',
                                'snowy',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '149.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '149.49',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/docks_blue',
                    ],
                ],
                'identifier' => 'docks_blue',
                'family' => 'boots',
                'groups' => ['caterpillar_boots'],
                'categories' => [
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'blue',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Caterpillar',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Docks',
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '44',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'cold',
                                'snowy',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '149.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '149.49',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/docks_maroon',
                    ],
                ],
                'identifier' => 'docks_maroon',
                'family' => 'boots',
                'groups' => ['caterpillar_boots'],
                'categories' => [
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'maroon',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Caterpillar',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Docks',
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '44',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'cold',
                                'snowy',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '149.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '149.49',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
            [
                '_links' => [
                    'self' => [
                        'href' => $baseUri.'/api/rest/v1/published-products/docks_red',
                    ],
                ],
                'identifier' => 'docks_red',
                'family' => 'boots',
                'groups' => ['caterpillar_boots'],
                'categories' => [
                    'winter_collection',
                ],
                'enabled' => true,
                'values' => [
                    'color' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'red',
                        ],
                    ],
                    'manufacturer' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => 'Caterpillar',
                        ],
                    ],
                    'name' => [
                        [
                            'locale' => 'en_US',
                            'scope' => null,
                            'data' => 'Docks',
                        ],
                    ],
                    'size' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => '44',
                        ],
                    ],
                    'weather_conditions' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                'cold',
                                'snowy',
                                'wet',
                            ],
                        ],
                    ],
                    'price' => [
                        [
                            'locale' => null,
                            'scope' => null,
                            'data' => [
                                [
                                    'amount' => '149.49',
                                    'currency' => 'EUR',
                                ],
                                [
                                    'amount' => '149.49',
                                    'currency' => 'USD',
                                ],
                            ],
                        ],
                    ],
                ],
                'created' => '2017-06-26T07:33:09+00:00',
                'updated' => '2017-06-26T07:33:09+00:00',
                'associations' => [],
            ],
        ];
    }
}
