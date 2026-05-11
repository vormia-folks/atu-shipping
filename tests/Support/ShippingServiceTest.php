<?php

namespace Vormia\ATUShipping\Tests\Support;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Vormia\ATUShipping\Contracts\CartInterface;
use Vormia\ATUShipping\Contracts\OrderInterface;
use Vormia\ATUShipping\Support\FeeCalculator;
use Vormia\ATUShipping\Support\RuleEvaluator;
use Vormia\ATUShipping\Support\ShippingService;

#[AllowMockObjectsWithoutExpectations]
class ShippingServiceTest extends TestCase
{
    public function test_options_returns_empty_array_without_context(): void
    {
        $service = $this->makeService();

        $this->assertSame([], $service->options());
    }

    public function test_options_returns_empty_array_without_countries(): void
    {
        $cart = $this->makeCart();
        $evaluator = $this->createMock(RuleEvaluator::class);
        // evaluator must NEVER be called when countries are missing.
        $evaluator->expects($this->never())->method('evaluateRules');

        $service = new ShippingService($evaluator, $this->createMock(FeeCalculator::class));
        $service->forCart($cart);

        $this->assertSame([], $service->options());
    }

    public function test_select_returns_null_when_no_context_set(): void
    {
        $service = $this->makeService();

        $this->assertNull($service->select('DHL'));
    }

    public function test_for_order_auto_populates_countries_from_order(): void
    {
        $order = $this->makeOrder(origin: 'ZA', destination: 'KE');
        $service = $this->makeService();

        $service->forOrder($order);

        // We can't directly read the protected props, but options() must
        // require countries — if forOrder pulled them in, the rule evaluator
        // would be invoked. Verify via the evaluator mock instead.
        $evaluator = $this->createMock(RuleEvaluator::class);
        $evaluator->expects($this->once())
            ->method('evaluateRules')
            ->with($order, 'ZA', 'KE')
            ->willReturn([]);

        $service = new ShippingService($evaluator, $this->createMock(FeeCalculator::class));
        $service->forOrder($order)->options();
    }

    public function test_for_cart_clears_order_context(): void
    {
        $service = $this->makeService();
        $order = $this->makeOrder('ZA', 'KE');
        $cart = $this->makeCart();

        $service->forOrder($order)->forCart($cart);

        // After forCart, the active context must be the cart, not the order.
        $evaluator = $this->createMock(RuleEvaluator::class);
        $evaluator->expects($this->once())
            ->method('evaluateRules')
            ->with($cart, 'ZA', 'KE')
            ->willReturn([]);

        $service = new ShippingService($evaluator, $this->createMock(FeeCalculator::class));
        $service->forOrder($order)->forCart($cart)->from('ZA')->to('KE')->options();
    }

    public function test_from_and_to_set_countries_used_by_evaluator(): void
    {
        $cart = $this->makeCart();
        $evaluator = $this->createMock(RuleEvaluator::class);
        $evaluator->expects($this->once())
            ->method('evaluateRules')
            ->with($cart, 'ZA', 'KE')
            ->willReturn([]);

        $service = new ShippingService($evaluator, $this->createMock(FeeCalculator::class));
        $service->forCart($cart)->from('ZA')->to('KE')->options();
    }

    private function makeService(): ShippingService
    {
        return new ShippingService(
            $this->createMock(RuleEvaluator::class),
            $this->createMock(FeeCalculator::class)
        );
    }

    private function makeCart(float $subtotal = 0.0, float $weight = 0.0): CartInterface
    {
        return new class($subtotal, $weight) implements CartInterface {
            public function __construct(private float $subtotal, private float $weight) {}
            public function getSubtotal(): float { return $this->subtotal; }
            public function getTotalWeight(): float { return $this->weight; }
            public function getItems(): array { return []; }
        };
    }

    private function makeOrder(?string $origin, ?string $destination): OrderInterface
    {
        return new class($origin, $destination) implements OrderInterface {
            public function __construct(private ?string $origin, private ?string $destination) {}
            public function getSubtotal(): float { return 0.0; }
            public function getTotalWeight(): float { return 0.0; }
            public function getItems(): array { return []; }
            public function getDestinationCountry(): ?string { return $this->destination; }
            public function getOriginCountry(): ?string { return $this->origin; }
        };
    }
}
