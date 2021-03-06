<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Test\Unit\Helper;

use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadata;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Helper\Guest;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GuestTest extends TestCase
{
    /** @var Guest */
    protected $guest;

    /** @var Session|MockObject */
    protected $sessionMock;

    /** @var CookieManagerInterface|MockObject */
    protected $cookieManagerMock;

    /** @var CookieMetadataFactory|MockObject */
    protected $cookieMetadataFactoryMock;

    /** @var ManagerInterface|MockObject */
    protected $managerInterfaceMock;

    /** @var MockObject */
    protected $orderFactoryMock;

    /** @var ViewInterface|MockObject */
    protected $viewInterfaceMock;

    /** @var Store|MockObject */
    protected $storeModelMock;

    /** @var Order|MockObject */
    protected $salesOrderMock;

    /**
     * @var OrderRepositoryInterface|MockObject
     */
    private $orderRepository;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private $searchCriteriaBuilder;

    protected function setUp(): void
    {
        $appContextHelperMock = $this->createMock(Context::class);
        $storeManagerInterfaceMock = $this->getMockForAbstractClass(StoreManagerInterface::class);
        $registryMock = $this->createMock(Registry::class);
        $this->sessionMock = $this->createMock(Session::class);
        $this->cookieManagerMock = $this->getMockForAbstractClass(CookieManagerInterface::class);
        $this->cookieMetadataFactoryMock = $this->createMock(
            CookieMetadataFactory::class
        );
        $this->managerInterfaceMock = $this->getMockForAbstractClass(ManagerInterface::class);
        $this->orderFactoryMock = $this->createPartialMock(OrderFactory::class, ['create']);
        $this->viewInterfaceMock = $this->getMockForAbstractClass(ViewInterface::class);
        $this->storeModelMock = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesOrderMock = $this->createPartialMock(
            Order::class,
            [
                'getProtectCode',
                'loadByIncrementIdAndStoreId',
                'loadByIncrementId',
                'getId',
                'getStoreId',
                'getBillingAddress'
            ]
        );
        $this->orderRepository = $this->getMockBuilder(OrderRepositoryInterface::class)
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->searchCriteriaBuilder = $this->getMockBuilder(SearchCriteriaBuilder::class)
            ->setMethods(['addFilter', 'create'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $orderSearchResult = $this->getMockBuilder(OrderSearchResultInterface::class)
            ->setMethods(['getTotalCount', 'getItems'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $resultRedirectFactory =
            $this->getMockBuilder(RedirectFactory::class)
                ->setMethods(['create'])
                ->disableOriginalConstructor()
                ->getMock();
        $this->orderRepository->method('getList')->willReturn($orderSearchResult);
        $orderSearchResult->method('getTotalCount')->willReturn(1);
        $orderSearchResult->method('getItems')->willReturn([ 2 => $this->salesOrderMock]);
        $searchCriteria = $this
            ->getMockBuilder(SearchCriteriaInterface::class)
            ->getMockForAbstractClass();
        $storeManagerInterfaceMock->expects($this->any())->method('getStore')->willReturn($this->storeModelMock);
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);
        $this->storeModelMock->method('getId')->willReturn(1);
        $this->salesOrderMock->expects($this->any())->method('getStoreId')->willReturn(1);
        $objectManagerHelper = new ObjectManagerHelper($this);
        $this->guest = $objectManagerHelper->getObject(
            Guest::class,
            [
                'context' => $appContextHelperMock,
                'storeManager' => $storeManagerInterfaceMock,
                'coreRegistry' => $registryMock,
                'customerSession' => $this->sessionMock,
                'cookieManager' => $this->cookieManagerMock,
                'cookieMetadataFactory' => $this->cookieMetadataFactoryMock,
                'messageManager' => $this->managerInterfaceMock,
                'orderFactory' => $this->orderFactoryMock,
                'view' => $this->viewInterfaceMock,
                'orderRepository' => $this->orderRepository,
                'searchCriteriaBuilder' => $this->searchCriteriaBuilder,
                'resultRedirectFactory' => $resultRedirectFactory
            ]
        );
    }

    public function testLoadValidOrderNotEmptyPost()
    {
        $post = [
            'oar_order_id' => 1,
            'oar_type' => 'email',
            'oar_billing_lastname' => 'oar_billing_lastname',
            'oar_email' => 'oar_email',
            'oar_zip' => 'oar_zip',

        ];
        $incrementId = $post['oar_order_id'];
        $protectedCode = 'protectedCode';
        $this->sessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $requestMock = $this->createMock(Http::class);
        $requestMock->expects($this->once())->method('getPostValue')->willReturn($post);
        $this->salesOrderMock->expects($this->any())->method('getId')->willReturn($incrementId);

        $billingAddressMock = $this->createPartialMock(
            Address::class,
            ['getLastname', 'getEmail']
        );
        $billingAddressMock->expects($this->once())->method('getLastname')->willReturn(($post['oar_billing_lastname']));
        $billingAddressMock->expects($this->once())->method('getEmail')->willReturn(($post['oar_email']));
        $this->salesOrderMock->expects($this->once())->method('getBillingAddress')->willReturn($billingAddressMock);
        $this->salesOrderMock->expects($this->once())->method('getProtectCode')->willReturn($protectedCode);
        $metaDataMock = $this->createMock(PublicCookieMetadata::class);
        $metaDataMock->expects($this->once())->method('setPath')
            ->with(Guest::COOKIE_PATH)
            ->willReturnSelf();
        $metaDataMock->expects($this->once())
            ->method('setHttpOnly')
            ->with(true)
            ->willReturnSelf();
        $this->cookieMetadataFactoryMock->expects($this->once())
            ->method('createPublicCookieMetadata')
            ->willReturn($metaDataMock);
        $this->cookieManagerMock->expects($this->once())
            ->method('setPublicCookie')
            ->with(Guest::COOKIE_NAME, $this->anything(), $metaDataMock);
        $this->assertTrue($this->guest->loadValidOrder($requestMock));
    }

    public function testLoadValidOrderStoredCookie()
    {
        $protectedCode = 'protectedCode';
        $incrementId = 1;
        $cookieData = $protectedCode . ':' . $incrementId;
        $cookieDataHash = base64_encode($cookieData);
        $this->sessionMock->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->cookieManagerMock->expects($this->once())
            ->method('getCookie')
            ->with(Guest::COOKIE_NAME)
            ->willReturn($cookieDataHash);
        $this->salesOrderMock->expects($this->any())->method('getId')->willReturn($incrementId);
        $this->salesOrderMock->expects($this->once())->method('getProtectCode')->willReturn($protectedCode);
        $metaDataMock = $this->createMock(PublicCookieMetadata::class);
        $metaDataMock->expects($this->once())
            ->method('setPath')
            ->with(Guest::COOKIE_PATH)
            ->willReturnSelf();
        $metaDataMock->expects($this->once())
            ->method('setHttpOnly')
            ->with(true)
            ->willReturnSelf();
        $this->cookieMetadataFactoryMock->expects($this->once())
            ->method('createPublicCookieMetadata')
            ->willReturn($metaDataMock);
        $this->cookieManagerMock->expects($this->once())
            ->method('setPublicCookie')
            ->with(Guest::COOKIE_NAME, $this->anything(), $metaDataMock);
        $requestMock = $this->createMock(Http::class);
        $this->assertTrue($this->guest->loadValidOrder($requestMock));
    }
}
