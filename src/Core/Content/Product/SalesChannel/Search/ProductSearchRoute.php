<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package system-settings
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class ProductSearchRoute extends AbstractProductSearchRoute
{
    /**
     * @internal
     */
    public function __construct(private readonly ProductSearchBuilderInterface $searchBuilder, private readonly EventDispatcherInterface $eventDispatcher, private readonly ProductListingLoader $productListingLoader)
    {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     */
    #[Route(path: '/store-api/search', name: 'store-api.search', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        if (!$request->get('search')) {
            throw new MissingRequestParameterException('search');
        }

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $result = $this->productListingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SEARCH_RESULT
        );

        $result->addCurrentFilter('search', $request->get('search'));

        return new ProductSearchRouteResponse($result);
    }
}
