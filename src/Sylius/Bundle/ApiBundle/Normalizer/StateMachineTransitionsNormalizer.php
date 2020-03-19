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

namespace Sylius\Bundle\ApiBundle\Normalizer;

use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Bridge\Symfony\Routing\RouteNameGenerator;
use ApiPlatform\Core\Bridge\Symfony\Routing\Router;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use Sylius\Bundle\ApiBundle\Provider\ApiTransitionsProviderInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class StateMachineTransitionsNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    /** @var AbstractItemNormalizer */
    private $decoratedNormalizer;

    /** @var ResourceMetadataFactoryInterface */
    private $metadataFactory;

    /** @var ApiTransitionsProviderInterface */
    private $apiTransitionsProvider;

    /** @var Router */
    private $router;

    public function __construct(
        AbstractItemNormalizer $decoratedNormalizer,
        ResourceMetadataFactoryInterface $metadataFactory,
        ApiTransitionsProviderInterface $apiTransitionsProvider,
        Router $router
    ) {
        $this->decoratedNormalizer = $decoratedNormalizer;
        $this->metadataFactory = $metadataFactory;
        $this->apiTransitionsProvider = $apiTransitionsProvider;
        $this->router = $router;
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $this->decoratedNormalizer->supportsNormalization($data, $format);
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        $normalizedData = $this->decoratedNormalizer->normalize($object, $format, $context);

        /** @var ResourceMetadata $metadata */
        $metadata = $this->metadataFactory->create(get_class($object));
        $transitions = $this->apiTransitionsProvider->getPossibleApiTransitions($object, $metadata);
        if (count($transitions) === 0) {
            return $normalizedData;
        }

        return $this->formatTransitionsSection($normalizedData, $transitions, $metadata);
    }

    public function supportsDenormalization($data, $type, string $format = null): bool
    {
        return $this->decoratedNormalizer->supportsDenormalization($data, $type, $format);
    }

    public function denormalize($data, $class, string $format = null, array $context = [])
    {
        return $this->decoratedNormalizer->denormalize($data, $class, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decoratedNormalizer instanceof SerializerAwareInterface) {
            $this->decoratedNormalizer->setSerializer($serializer);
        }
    }

    private function formatTransitionsSection(array $normalizedData, array $transitions, ResourceMetadata $metadata): array
    {
        $normalizedData['transitions'] = [];
        foreach ($transitions as $transition) {
            $operation = $metadata->getItemOperations()[$transition];

            $normalizedData['transitions'][] = [
                'name' => $transition,
                'href' => $this->generatePathForOperation($transition, $metadata, $normalizedData['id']),
                'method' => $operation['method'],
            ];
        }

        return $normalizedData;
    }

    private function generatePathForOperation(string $operation, ResourceMetadata $metadata, $id): string
    {
        $routeName = RouteNameGenerator::generate($operation, $metadata->getShortName(), OperationType::ITEM);

        return $this->router->generate($routeName, ['id' => $id]);
    }
}
