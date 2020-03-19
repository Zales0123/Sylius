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

namespace spec\Sylius\Bundle\ApiBundle\Normalizer;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\ApiBundle\Provider\ApiTransitionsProviderInterface;
use Sylius\Component\Core\Model\ProductReview;

final class StateMachineTransitionsNormalizerSpec extends ObjectBehavior
{
    function let(
        AbstractItemNormalizer $decoratedNormalizer,
        ResourceMetadataFactoryInterface $metadataFactory,
        ApiTransitionsProviderInterface $apiTransitionsProvider
    ): void {
        $this->beConstructedWith($decoratedNormalizer, $metadataFactory, $apiTransitionsProvider);
    }

    function it_add_available_transitions_to_normalized_data(
        AbstractItemNormalizer $decoratedNormalizer,
        ResourceMetadataFactoryInterface $metadataFactory,
        ApiTransitionsProviderInterface $apiTransitionsProvider
    ): void {
        $metadata = new ResourceMetadata(
            'Review',
            '',
            '',
            ['accept' => ['path' => '/review/123/accept', 'method' => 'PATCH']],
            ['reject' => ['path' => '/review/123/reject', 'method' => 'PATCH']]
        );

        $review = new ProductReview();

        $decoratedNormalizer->normalize($review, null, [])->willReturn(['id' => 123, 'rating' => 5]);

        $metadataFactory->create(ProductReview::class)->willReturn($metadata);
        $apiTransitionsProvider->getPossibleApiTransitions($review, $metadata)->willReturn(['accept']);

        $this->normalize($review)->shouldReturn([
            'id' => 123,
            'rating' => 5,
            'transitions' => [
                ['name' => 'accept', 'href' => '/review/123/accept', 'method' => 'PATCH'],
            ]
        ]);
    }

    function it_does_nothing_if_there_is_no_available_transitions(
        AbstractItemNormalizer $decoratedNormalizer,
        ResourceMetadataFactoryInterface $metadataFactory,
        ApiTransitionsProviderInterface $apiTransitionsProvider
    ): void {
        $metadata = new ResourceMetadata('Review');
        $review = new ProductReview();

        $decoratedNormalizer->normalize($review, null, [])->willReturn(['id' => 123, 'rating' => 5]);

        $metadataFactory->create(ProductReview::class)->willReturn($metadata);
        $apiTransitionsProvider->getPossibleApiTransitions($review, $metadata)->willReturn([]);

        $this->normalize($review)->shouldReturn(['id' => 123, 'rating' => 5]);
    }
}
