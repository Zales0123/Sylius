<?php

declare(strict_types=1);

namespace Sylius\Bundle\ApiBundle\Tests\Functional;

use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Sylius\Bundle\ApiBundle\Provider\ApiTransitionsProvider;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;

final class ApiTransitionsProviderTest extends KernelTestCase
{
    protected function setUp(): void
    {
        static::bootKernel();

        /** @var Container $container */
        self::$container = self::$kernel->getContainer();
    }

    /** @test */
    public function it_returns_possible_transitions_for_object(): void
    {
        $stateMachineFactory = self::$container->get('sm.factory');
        $stateMachinesConfig = self::$container->getParameter('sm.configs');

        $provider = new ApiTransitionsProvider($stateMachineFactory, $stateMachinesConfig);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_NEW);

        $this->assertSame(
            ['complete', 'cancel'],
            $provider->getPossibleApiTransitions(
                $payment, new ResourceMetadata(Payment::class, '', '', ['complete' => [], 'cancel' => []])
            )
        );
    }

    /** @test */
    public function it_returns_empty_array_if_there_are_no_common_operations_and_available_transitions(): void
    {
        $stateMachineFactory = self::$container->get('sm.factory');
        $stateMachinesConfig = self::$container->getParameter('sm.configs');

        $provider = new ApiTransitionsProvider($stateMachineFactory, $stateMachinesConfig);

        $payment = new Payment();
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $this->assertEmpty($provider->getPossibleApiTransitions(
            $payment,
            new ResourceMetadata(Payment::class, '', '', ['complete' => [], 'cancel' => []])
        ));
    }

    /** @test */
    public function it_returns_empty_array_if_no_state_machine_is_defined_for_the_object(): void
    {
        $stateMachineFactory = self::$container->get('sm.factory');
        $stateMachinesConfig = self::$container->getParameter('sm.configs');

        $provider = new ApiTransitionsProvider($stateMachineFactory, $stateMachinesConfig);

        $this->assertEmpty(
            $provider->getPossibleApiTransitions(new \stdClass(), new ResourceMetadata('ObjectWithoutStateMachine'))
        );
    }
}
