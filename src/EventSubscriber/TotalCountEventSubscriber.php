<?php declare(strict_types = 1);

namespace WhiteDigital\Translation\EventSubscriber;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use JsonException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use WhiteDigital\Translation\Api\Resource\TransUnitResource;
use WhiteDigital\Translation\Service\CollectionCount;

use function ceil;
use function http_build_query;
use function json_decode;
use function json_encode;
use function strtok;

use const JSON_THROW_ON_ERROR;

final readonly class TotalCountEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CollectionCount $count,
    ) {
    }

    public function getPager(array $filters): array
    {
        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? 30);
        $page = (int) ($filters['page'] ?? 1);

        return [
            'offset' => ($page - 1) * $itemsPerPage,
            'itemsPerPage' => $itemsPerPage,
            'page' => $page,
        ];
    }

    /**
     * @throws NonUniqueResultException
     * @throws JsonException
     * @throws NoResultException
     */
    public function onKernelView(ViewEvent $event): void
    {
        $request = $event->getRequest();
        $at = $request->attributes;
        if ($at->has('_api_resource_class') && TransUnitResource::class === $at->get('_api_resource_class') && '/trans_units' === $at->get('_api_operation')->getUriTemplate() && $at->get('_api_operation') instanceof GetCollection) {
            $serialized = $event->getControllerResult();
            $data = json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
            $params = $request->query->all();
            $pager = $this->getPager($params);

            $uri = strtok($request->attributes->get('_api_normalization_context')['request_uri'], '?');

            $previousView = $nextView = $firstView = $lastView = $pager;

            $page = ($pager['page'] ?? 1);
            $firstView['page'] = 1;
            $data['hydra:view']['hydra:first'] = $uri . '?' . http_build_query($firstView);
            if (1 < $page) {
                $previousView['page'] = $page - 1;
                $data['hydra:view']['hydra:previous'] = $uri . '?' . http_build_query($previousView);
            }

            $total = ceil($this->count->getCount() / ($pager['itemsPerPage'] ?? 30));
            $lastView['page'] = $total;
            $data['hydra:view']['hydra:last'] = $uri . '?' . http_build_query($lastView);
            if ($total > $page) {
                $nextView['page'] = $page + 1;
                $data['hydra:view']['hydra:next'] = $uri . '?' . http_build_query($nextView);
            }

            $data['hydra:totalItems'] = $this->count->getCount();
            $event->setControllerResult(json_encode($data, JSON_THROW_ON_ERROR));
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                'onKernelView',
                EventPriorities::POST_SERIALIZE,
            ],
        ];
    }
}
