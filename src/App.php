<?php

declare(strict_types=1);

namespace Vapehaus;

use PDO;
use Vapehaus\Execution\Address\AddressExecution;
use Vapehaus\Execution\Admin\AdminCreateCategoryExecution;
use Vapehaus\Execution\Admin\AdminCreateProductExecution;
use Vapehaus\Execution\Admin\AdminCreateVariantExecution;
use Vapehaus\Execution\Admin\AdminGetDashboardExecution;
use Vapehaus\Execution\Admin\AdminListCustomersExecution;
use Vapehaus\Execution\Admin\AdminListOrdersExecution;
use Vapehaus\Execution\Admin\AdminUpdateOrderStatusExecution;
use Vapehaus\Execution\Admin\AdminUpdateStockExecution;
use Vapehaus\Execution\Auth\AuthenticateUserExecution;
use Vapehaus\Execution\Auth\GetProfileExecution;
use Vapehaus\Execution\Auth\LogoutUserExecution;
use Vapehaus\Execution\Auth\RegisterUserExecution;
use Vapehaus\Execution\Cart\AddToCartExecution;
use Vapehaus\Execution\Cart\ClearCartExecution;
use Vapehaus\Execution\Cart\GetCartExecution;
use Vapehaus\Execution\Cart\RemoveFromCartExecution;
use Vapehaus\Execution\Cart\UpdateCartItemExecution;
use Vapehaus\Execution\Catalog\GetCatalogFacetsExecution;
use Vapehaus\Execution\Catalog\GetProductDetailExecution;
use Vapehaus\Execution\Catalog\ListCategoriesExecution;
use Vapehaus\Execution\Catalog\ListProductsExecution;
use Vapehaus\Execution\Coupon\CouponExecution;
use Vapehaus\Execution\Kyc\KycExecution;
use Vapehaus\Execution\Order\AdminShipOrderExecution;
use Vapehaus\Execution\Order\CancelOrderExecution;
use Vapehaus\Execution\Order\GetOrderDetailExecution;
use Vapehaus\Execution\Order\GetUserOrdersExecution;
use Vapehaus\Execution\Order\PlaceOrderExecution;
use Vapehaus\Execution\Payment\PaymentExecution;
use Vapehaus\Execution\Review\ReviewExecution;
use Vapehaus\Execution\Rma\RmaExecution;
use Vapehaus\Execution\Wishlist\WishlistExecution;
use Vapehaus\Infrastructure\Database\DatabaseConnection;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Infrastructure\Notification\PdoNotificationService;
use Vapehaus\Infrastructure\Repository\PdoAddressRepository;
use Vapehaus\Infrastructure\Repository\PdoCartRepository;
use Vapehaus\Infrastructure\Repository\PdoCategoryRepository;
use Vapehaus\Infrastructure\Repository\PdoCouponRepository;
use Vapehaus\Infrastructure\Repository\PdoInventoryAuditRepository;
use Vapehaus\Infrastructure\Repository\PdoKycRepository;
use Vapehaus\Infrastructure\Repository\PdoOrderRepository;
use Vapehaus\Infrastructure\Repository\PdoPaymentTransactionRepository;
use Vapehaus\Infrastructure\Repository\PdoProductRepository;
use Vapehaus\Infrastructure\Repository\PdoReturnRepository;
use Vapehaus\Infrastructure\Repository\PdoReviewRepository;
use Vapehaus\Infrastructure\Repository\PdoSessionRepository;
use Vapehaus\Infrastructure\Repository\PdoUserRepository;
use Vapehaus\Infrastructure\Repository\PdoWishlistRepository;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Infrastructure\Security\PasswordHasher;
use Vapehaus\Presentation\Controller\AddressController;
use Vapehaus\Presentation\Controller\AdminController;
use Vapehaus\Presentation\Controller\AuthController;
use Vapehaus\Presentation\Controller\CartController;
use Vapehaus\Presentation\Controller\CatalogController;
use Vapehaus\Presentation\Controller\CouponController;
use Vapehaus\Presentation\Controller\KycController;
use Vapehaus\Presentation\Controller\OrderController;
use Vapehaus\Presentation\Controller\PaymentWebhookController;
use Vapehaus\Presentation\Controller\ReviewController;
use Vapehaus\Presentation\Controller\RmaController;
use Vapehaus\Presentation\Controller\UserController;
use Vapehaus\Presentation\Controller\WishlistController;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Middleware\AuthMiddleware;
use Vapehaus\Presentation\Middleware\RateLimiterMiddleware;
use Vapehaus\Presentation\Middleware\RequireRoleMiddleware;
use Vapehaus\Presentation\Router\Router;

final class App
{
    private Router $router;
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseConnection::getPdo();
        $this->initializeDatabase();
        $this->router = new Router();
        $this->registerRoutes();
    }

    private function initializeDatabase(): void
    {
        $schema = new SchemaManager($this->pdo);
        $schema->createTables();
        $schema->seedDefaults();
    }

    private function registerRoutes(): void
    {
        // Infrastructure Repositories
        $userRepo = new PdoUserRepository($this->pdo);
        $categoryRepo = new PdoCategoryRepository($this->pdo);
        $productRepo = new PdoProductRepository($this->pdo);
        $cartRepo = new PdoCartRepository($this->pdo, $productRepo);
        $orderRepo = new PdoOrderRepository($this->pdo);
        $addressRepo = new PdoAddressRepository($this->pdo);
        $couponRepo = new PdoCouponRepository($this->pdo);
        $reviewRepo = new PdoReviewRepository($this->pdo);
        $kycRepo = new PdoKycRepository($this->pdo);
        $returnRepo = new PdoReturnRepository($this->pdo);
        $wishlistRepo = new PdoWishlistRepository($this->pdo);
        $paymentRepo = new PdoPaymentTransactionRepository($this->pdo);
        $auditRepo = new PdoInventoryAuditRepository($this->pdo);
        $notifService = new PdoNotificationService($this->pdo);
        $sessionRepo = new PdoSessionRepository($this->pdo);

        $hasher = new PasswordHasher();
        $tokenService = new JwtTokenService();

        // Middlewares
        $authRequired = new AuthMiddleware($tokenService, false, $sessionRepo);
        $authOptional = new AuthMiddleware($tokenService, true, $sessionRepo);
        $adminOnly = new RequireRoleMiddleware('admin');
        $rateLimiter = new RateLimiterMiddleware(120, 60);
        $authRateLimiter = new RateLimiterMiddleware(5, 60); // 5 attempts per minute (Audit spec)

        // Executions
        $registerExec = new RegisterUserExecution($userRepo, $hasher, $tokenService, 18, $sessionRepo);
        $authExec = new AuthenticateUserExecution($userRepo, $hasher, $tokenService, $sessionRepo);
        $logoutExec = new LogoutUserExecution($sessionRepo);
        $profileExec = new GetProfileExecution($userRepo);

        $listCategoriesExec = new ListCategoriesExecution($categoryRepo);
        $listProductsExec = new ListProductsExecution($productRepo);
        $productDetailExec = new GetProductDetailExecution($productRepo);
        $facetsExec = new GetCatalogFacetsExecution($this->pdo);

        $getCartExec = new GetCartExecution($cartRepo, $productRepo);
        $addToCartExec = new AddToCartExecution($cartRepo, $productRepo);
        $updateCartItemExec = new UpdateCartItemExecution($cartRepo, $productRepo);
        $removeFromCartExec = new RemoveFromCartExecution($cartRepo);
        $clearCartExec = new ClearCartExecution($cartRepo);

        $placeOrderExec = new PlaceOrderExecution(
            $orderRepo,
            $cartRepo,
            $productRepo,
            $userRepo,
            $addressRepo,
            $couponRepo,
            $auditRepo,
            $notifService
        );
        $userOrdersExec = new GetUserOrdersExecution($orderRepo);
        $orderDetailExec = new GetOrderDetailExecution($orderRepo);
        $cancelOrderExec = new CancelOrderExecution($orderRepo, $productRepo);
        $shipOrderExec = new AdminShipOrderExecution($orderRepo, $notifService);

        $addressExec = new AddressExecution($addressRepo);
        $couponExec = new CouponExecution($couponRepo, $cartRepo, $productRepo);
        $reviewExec = new ReviewExecution($reviewRepo, $productRepo, $orderRepo);
        $kycExec = new KycExecution($kycRepo, $userRepo, $notifService);
        $rmaExec = new RmaExecution($returnRepo, $orderRepo, $productRepo);
        $wishlistExec = new WishlistExecution($wishlistRepo, $productRepo);
        $paymentExec = new PaymentExecution($paymentRepo, $orderRepo, $productRepo, $auditRepo);

        $adminDashboardExec = new AdminGetDashboardExecution($orderRepo, $userRepo, $productRepo, $kycRepo);
        $adminCategoryExec = new AdminCreateCategoryExecution($categoryRepo);
        $adminProductExec = new AdminCreateProductExecution($productRepo, $categoryRepo);
        $adminVariantExec = new AdminCreateVariantExecution($productRepo);
        $adminStockExec = new AdminUpdateStockExecution($productRepo);
        $adminOrdersExec = new AdminListOrdersExecution($orderRepo);
        $adminStatusExec = new AdminUpdateOrderStatusExecution($orderRepo, $productRepo);
        $adminCustomersExec = new AdminListCustomersExecution($userRepo);

        // Controllers
        $authController = new AuthController($registerExec, $authExec, $profileExec, $logoutExec);
        $userController = new UserController($profileExec, $userOrdersExec, $orderDetailExec, $cancelOrderExec);
        $catalogController = new CatalogController($listCategoriesExec, $listProductsExec, $productDetailExec, $facetsExec);
        $cartController = new CartController($getCartExec, $addToCartExec, $updateCartItemExec, $removeFromCartExec, $clearCartExec);
        $orderController = new OrderController($placeOrderExec);
        $addressController = new AddressController($addressExec);
        $couponController = new CouponController($couponExec);
        $reviewController = new ReviewController($reviewExec);
        $kycController = new KycController($kycExec);
        $rmaController = new RmaController($rmaExec);
        $wishlistController = new WishlistController($wishlistExec);
        $paymentController = new PaymentWebhookController($paymentExec);
        $adminController = new AdminController(
            $adminDashboardExec,
            $adminCategoryExec,
            $adminProductExec,
            $adminVariantExec,
            $adminStockExec,
            $adminOrdersExec,
            $orderDetailExec,
            $adminStatusExec,
            $adminCustomersExec,
            $shipOrderExec,
            $auditRepo
        );

        // 1. Auth Endpoints
        $this->router->post('/api/v1/auth/register', fn (Request $r) => $authController->register($r), [$authRateLimiter]);
        $this->router->post('/api/v1/auth/login', fn (Request $r) => $authController->login($r), [$authRateLimiter]);
        $this->router->post('/api/v1/auth/logout', fn (Request $r) => $authController->logout($r));
        $this->router->get('/api/v1/auth/me', fn (Request $r) => $authController->me($r), [$authRequired]);

        // 2. User & Orders Endpoints (Compatible with both /orders and /user/orders)
        $this->router->get('/api/v1/user/profile', fn (Request $r) => $userController->getProfile($r), [$authRequired]);
        $this->router->get('/api/v1/user/addresses', fn (Request $r) => $addressController->list($r), [$authRequired]);
        $this->router->post('/api/v1/user/addresses', fn (Request $r) => $addressController->create($r), [$authRequired]);
        $this->router->patch('/api/v1/user/addresses/{id}/default', fn (Request $r, array $p) => $addressController->setDefault($r, $p), [$authRequired]);
        $this->router->delete('/api/v1/user/addresses/{id}', fn (Request $r, array $p) => $addressController->delete($r, $p), [$authRequired]);
        
        $this->router->get('/api/v1/orders', fn (Request $r) => $userController->getOrders($r), [$authRequired]);
        $this->router->get('/api/v1/orders/{id}', fn (Request $r, array $p) => $userController->getOrderDetail($r, $p), [$authRequired]);
        $this->router->post('/api/v1/orders/{id}/cancel', fn (Request $r, array $p) => $userController->cancelOrder($r, $p), [$authRequired]);
        $this->router->get('/api/v1/user/orders', fn (Request $r) => $userController->getOrders($r), [$authRequired]);
        $this->router->get('/api/v1/user/orders/{id}', fn (Request $r, array $p) => $userController->getOrderDetail($r, $p), [$authRequired]);
        $this->router->post('/api/v1/user/orders/{id}/cancel', fn (Request $r, array $p) => $userController->cancelOrder($r, $p), [$authRequired]);

        // 3. Vape KYC Regulatory Endpoints (18+ RA 11900)
        $this->router->get('/api/v1/kyc', fn (Request $r) => $kycController->getStatus($r), [$authRequired]);
        $this->router->post('/api/v1/kyc', fn (Request $r) => $kycController->submit($r), [$authRequired]);
        $this->router->get('/api/v1/user/kyc', fn (Request $r) => $kycController->getStatus($r), [$authRequired]);
        $this->router->post('/api/v1/user/kyc/submit', fn (Request $r) => $kycController->submit($r), [$authRequired]);

        // 4. Warranty & Returns (RMA) Endpoints
        $this->router->post('/api/v1/user/orders/{id}/returns', fn (Request $r) => $rmaController->create($r), [$authRequired]);
        $this->router->get('/api/v1/user/returns', fn (Request $r) => $rmaController->listUserReturns($r), [$authRequired]);

        // 5. Wishlists & Restock Alerts
        $this->router->get('/api/v1/wishlist', fn (Request $r) => $wishlistController->list($r), [$authRequired]);
        $this->router->post('/api/v1/wishlist/toggle/{productId}', fn (Request $r, array $p) => $wishlistController->toggle($r, $p), [$authRequired]);
        $this->router->post('/api/v1/products/variants/{variantId}/restock-alert', fn (Request $r, array $p) => $wishlistController->subscribeRestock($r, $p), [$authRequired]);

        // 6. Public Catalog & Facets
        $this->router->get('/api/v1/categories', fn (Request $r) => $catalogController->getCategories($r));
        $this->router->get('/api/v1/products', fn (Request $r) => $catalogController->getProducts($r));
        $this->router->get('/api/v1/products/facets', fn (Request $r) => $catalogController->getFacets($r));
        $this->router->get('/api/v1/products/{identifier}', fn (Request $r, array $p) => $catalogController->getProductDetail($r, $p));

        // 7. Verified Reviews
        $this->router->get('/api/v1/products/{productId}/reviews', fn (Request $r, array $p) => $reviewController->listForProduct($r, $p));
        $this->router->post('/api/v1/products/{productId}/reviews', fn (Request $r, array $p) => $reviewController->create($r, $p), [$authRequired]);

        // 8. Shopping Cart & Coupons
        $this->router->get('/api/v1/cart', fn (Request $r) => $cartController->getCart($r), [$authOptional]);
        $this->router->post('/api/v1/cart/items', fn (Request $r) => $cartController->addItem($r), [$authOptional]);
        $this->router->patch('/api/v1/cart/items/{itemId}', fn (Request $r, array $p) => $cartController->updateItem($r, $p), [$authOptional]);
        $this->router->delete('/api/v1/cart/items/{itemId}', fn (Request $r, array $p) => $cartController->removeItem($r, $p), [$authOptional]);
        $this->router->delete('/api/v1/cart', fn (Request $r) => $cartController->clear($r), [$authOptional]);
        $this->router->post('/api/v1/cart/coupon', fn (Request $r) => $couponController->apply($r), [$authOptional]);
        $this->router->delete('/api/v1/cart/coupon', fn (Request $r) => $couponController->remove($r), [$authOptional]);

        // 9. Checkout & Payment Webhooks
        $this->router->post('/api/v1/checkout', fn (Request $r) => $orderController->checkout($r), [$authOptional]);
        $this->router->post('/api/v1/webhooks/payments', fn (Request $r) => $paymentController->handleWebhook($r));

        // 10. Admin Endpoints
        $this->router->get('/api/v1/admin/dashboard', fn (Request $r) => $adminController->getDashboard($r), [$authRequired, $adminOnly]);
        $this->router->post('/api/v1/admin/categories', fn (Request $r) => $adminController->createCategory($r), [$authRequired, $adminOnly]);
        $this->router->post('/api/v1/admin/products', fn (Request $r) => $adminController->createProduct($r), [$authRequired, $adminOnly]);
        $this->router->post('/api/v1/admin/products/{id}/variants', fn (Request $r, array $p) => $adminController->createVariant($r, $p), [$authRequired, $adminOnly]);
        
        // Admin Inventory adjustments (both /variants/{id}/stock and /inventory/{id})
        $this->router->patch('/api/v1/admin/inventory/{id}', fn (Request $r, array $p) => $adminController->updateStock($r, $p), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/variants/{id}/stock', fn (Request $r, array $p) => $adminController->updateStock($r, $p), [$authRequired, $adminOnly]);
        
        $this->router->get('/api/v1/admin/orders', fn (Request $r) => $adminController->getOrders($r), [$authRequired, $adminOnly]);
        $this->router->get('/api/v1/admin/orders/{id}', fn (Request $r, array $p) => $adminController->getOrderDetail($r, $p), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/orders/{id}/status', fn (Request $r, array $p) => $adminController->updateOrderStatus($r, $p), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/orders/{id}/ship', fn (Request $r, array $p) => $adminController->shipOrder($r, $p), [$authRequired, $adminOnly]);
        $this->router->post('/api/v1/admin/orders/{id}/refund', fn (Request $r, array $p) => $paymentController->adminRefund($r, $p), [$authRequired, $adminOnly]);
        $this->router->get('/api/v1/admin/customers', fn (Request $r) => $adminController->getCustomers($r), [$authRequired, $adminOnly]);
        $this->router->get('/api/v1/admin/inventory/logs', fn (Request $r) => $adminController->getInventoryLogs($r), [$authRequired, $adminOnly]);

        // Admin Coupon Management
        $this->router->get('/api/v1/admin/coupons', fn (Request $r) => $couponController->adminList($r), [$authRequired, $adminOnly]);
        $this->router->post('/api/v1/admin/coupons', fn (Request $r) => $couponController->adminCreate($r), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/coupons/{id}/toggle', fn (Request $r, array $p) => $couponController->adminToggle($r, $p), [$authRequired, $adminOnly]);

        // Admin Review Moderation
        $this->router->get('/api/v1/admin/reviews', fn (Request $r) => $reviewController->adminList($r), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/reviews/{id}/status', fn (Request $r, array $p) => $reviewController->adminModerate($r, $p), [$authRequired, $adminOnly]);

        // Admin KYC Queue
        $this->router->get('/api/v1/admin/kyc', fn (Request $r) => $kycController->adminList($r), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/kyc/{id}/review', fn (Request $r, array $p) => $kycController->adminReview($r, $p), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/kyc/{id}/approve', fn (Request $r, array $p) => $kycController->adminReview($r->withBody(array_merge($r->getBody(), ['decision' => 'approved'])), $p), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/kyc/{id}/reject', fn (Request $r, array $p) => $kycController->adminReview($r->withBody(array_merge($r->getBody(), ['decision' => 'rejected'])), $p), [$authRequired, $adminOnly]);

        // Admin RMA Queue
        $this->router->get('/api/v1/admin/returns', fn (Request $r) => $rmaController->adminList($r), [$authRequired, $adminOnly]);
        $this->router->patch('/api/v1/admin/returns/{id}/status', fn (Request $r, array $p) => $rmaController->adminUpdateStatus($r, $p), [$authRequired, $adminOnly]);
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
