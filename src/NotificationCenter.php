<?php

declare(strict_types=1);

namespace Terminal42\NotificationCenterBundle;

use Contao\CoreBundle\Util\LocaleUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Terminal42\NotificationCenterBundle\Config\ConfigLoader;
use Terminal42\NotificationCenterBundle\Event\CreateParcelEvent;
use Terminal42\NotificationCenterBundle\Event\GetTokenDefinitionsEvent;
use Terminal42\NotificationCenterBundle\Exception\CouldNotCreateParcelException;
use Terminal42\NotificationCenterBundle\Exception\CouldNotDeliverParcelException;
use Terminal42\NotificationCenterBundle\Exception\InvalidNotificationTypeException;
use Terminal42\NotificationCenterBundle\Gateway\GatewayRegistry;
use Terminal42\NotificationCenterBundle\MessageType\MessageTypeRegistry;
use Terminal42\NotificationCenterBundle\Parcel\ParcelInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\TokenDefinitionInterface;
use Terminal42\NotificationCenterBundle\Token\Token;
use Terminal42\NotificationCenterBundle\Token\TokenCollection;

class NotificationCenter
{
    public function __construct(
        private Connection $connection,
        private MessageTypeRegistry $messageTypeRegistry,
        private GatewayRegistry $gatewayRegistry,
        private ConfigLoader $configLoader,
        private EventDispatcherInterface $eventDispatcher,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string> $tokenDefinitionTypes
     *
     * @return array<TokenDefinitionInterface>
     */
    public function getTokenDefinitionsForMessageType(string $typeName, array $tokenDefinitionTypes = []): array
    {
        $messageType = $this->messageTypeRegistry->getByName($typeName);

        if (null === $messageType) {
            throw InvalidNotificationTypeException::becauseTypeDoesNotExist($typeName);
        }

        $event = new GetTokenDefinitionsEvent($messageType, $messageType->getTokenDefinitions());

        $this->eventDispatcher->dispatch($event);

        return $event->getTokenDefinitions($tokenDefinitionTypes);
    }

    /**
     * @return array<int, string>
     */
    public function getNotificationsForMessageType(string $typeName): array
    {
        if (null === $this->messageTypeRegistry->getByName($typeName)) {
            return [];
        }

        return $this->connection->createQueryBuilder()
            ->select('id', 'title')
            ->from('tl_nc_notification')
            ->where('type = :type')
            ->orderBy('title')
            ->setParameter('type', $typeName)
            ->executeQuery()
            ->fetchAllKeyValue()
        ;
    }

    /**
     * @param array<string, mixed> $rawTokens
     */
    public function createTokenCollectionFromArray(array $rawTokens, string $messageTypeName): TokenCollection
    {
        $messageType = $this->messageTypeRegistry->getByName($messageTypeName);

        if (null === $messageType) {
            throw InvalidNotificationTypeException::becauseTypeDoesNotExist($messageTypeName);
        }

        $collection = new TokenCollection($messageType);
        $tokenDefinitions = $this->getTokenDefinitionsForMessageType($messageTypeName);

        foreach ($rawTokens as $rawTokenName => $rawTokenValue) {
            foreach ($tokenDefinitions as $definition) {
                if ($definition->matchesTokenName($rawTokenName)) {
                    $collection->add(new Token($definition, $rawTokenName, $rawTokenValue));
                }
            }
        }

        return $collection;
    }

    /**
     * @throw CannotCreateParcelException
     *
     * @return array<ParcelInterface>
     */
    public function createParcelsForNotification(int $id, TokenCollection $tokenCollection, string $locale = null): array
    {
        $parcels = [];

        foreach ($this->configLoader->loadMessagesForNotification($id) as $messageConfig) {
            $parcels[] = $this->createParcelForMessage($messageConfig->getId(), $tokenCollection, $locale);
        }

        return $parcels;
    }

    /**
     * @param string|null $locale The locale for the message. Passing none will try to automatically take
     *                            the one of the current request.
     *
     * @throw CannotCreateParcelException
     */
    public function createParcelForMessage(int $id, TokenCollection $tokenCollection, string $locale = null): ParcelInterface
    {
        if (null === ($messageConfig = $this->configLoader->loadMessage($id))) {
            throw CouldNotCreateParcelException::becauseOfNonExistentMessage($id);
        }

        if (null === ($notificationConfig = $this->configLoader->loadNotification($messageConfig->getNotification()))) {
            throw CouldNotCreateParcelException::becauseOfNonExistentNotification($messageConfig->getNotification());
        }

        if (null === ($gatewayConfig = $this->configLoader->loadGateway($messageConfig->getGateway()))) {
            throw CouldNotCreateParcelException::becauseOfNonExistentGateway($messageConfig->getGateway());
        }

        if (null === ($gateway = $this->gatewayRegistry->getByName($gatewayConfig->getType()))) {
            throw CouldNotCreateParcelException::becauseOfNonExistentGatewayType($gatewayConfig->getType());
        }

        if (
            null === $locale
            && ($request = $this->requestStack->getCurrentRequest())
            && ($pageModel = $request->attributes->get('pageModel'))
        ) {
            // We do not want to use $request->getLocale() here because this is never empty. If we're not on a Contao
            // page, $request->getLocale() would return the configured default locale which in Symfony always falls back
            // to English. But we want $locale to remain null in case we really have no Contao page language so that our
            // own fallback mechanism can kick in (loading the language marked as fallback by the user).
            $locale = $pageModel->language ? LocaleUtil::formatAsLocale($pageModel->language) : null;
        }

        $languageConfig = $this->configLoader->loadLanguageForMessageAndLocale($messageConfig->getId(), $locale);
        $parcel = $gateway->createParcelFromConfigs(
            $tokenCollection,
            $notificationConfig,
            $messageConfig,
            $gatewayConfig,
            $languageConfig
        );

        $event = new CreateParcelEvent(
            $parcel,
            $tokenCollection,
            $notificationConfig,
            $messageConfig,
            $gatewayConfig,
            $languageConfig
        );

        $this->eventDispatcher->dispatch($event);

        return $event->getParcel();
    }

    /**
     * @throws CouldNotDeliverParcelException
     */
    public function sendParcel(ParcelInterface $parcel): bool
    {
        $gateway = $this->gatewayRegistry->getByParcel($parcel);

        if (null === $gateway) {
            throw CouldNotDeliverParcelException::becauseOfNoGatewayIsResponsibleForParcel($parcel::class);
        }

        $gateway->sendParcel($parcel); // TODO: result?

        return true;
    }

    /**
     * Shortcut to send an entire set of messages that belong to the same notification.
     *
     * @param string|null $locale The locale for the message. Passing none will try to automatically take
     *                            the one of the current request.
     *
     * @throws CouldNotCreateParcelException
     * @throws CouldNotDeliverParcelException
     */
    public function sendNotification(int $id, TokenCollection $tokenCollection, string $locale = null): bool
    {
        foreach ($this->createParcelsForNotification($id, $tokenCollection, $locale) as $parcel) {
            $this->sendParcel($parcel); // TODO result?
        }

        return true; // TODO: Convert to proper result object coming from the gateway
    }
}
