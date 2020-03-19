<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\ApiBundle\Provider;

use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use SM\Factory\FactoryInterface;
use SM\SMException;
use Sylius\Component\Resource\StateMachine\StateMachineInterface;

final class ApiTransitionsProvider implements ApiTransitionsProviderInterface
{
    /** @var FactoryInterface */
    private $stateMachineFactory;

    /** @var array */
    private $stateMachinesConfig;

    public function __construct(FactoryInterface $stateMachineFactory, array $stateMachinesConfig)
    {
        $this->stateMachineFactory = $stateMachineFactory;
        $this->stateMachinesConfig = $stateMachinesConfig;
    }

    public function getPossibleApiTransitions(object $object, ResourceMetadata $metadata): array
    {
        $stateMachineConfigs = array_values(array_filter($this->stateMachinesConfig, function(array $config) use ($object) {
            return $config['class'] === get_class($object);
        }));

        if (count($stateMachineConfigs) === 0) {
            return [];
        }

        try {
            /** @var StateMachineInterface $stateMachine */
            $stateMachine = $this->stateMachineFactory->get($object, $stateMachineConfigs[0]['graph']);
        } catch (SMException $exception) {
            return [];
        }

        return array_intersect(array_keys($metadata->getItemOperations()), array_values($stateMachine->getPossibleTransitions()));
    }
}
