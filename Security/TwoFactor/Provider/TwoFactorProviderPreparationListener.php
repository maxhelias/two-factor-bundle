<?php

declare(strict_types=1);

namespace Scheb\TwoFactorBundle\Security\TwoFactor\Provider;

use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\Security\Core\Event\AuthenticationEvent;

class TwoFactorProviderPreparationListener
{
    /**
     * @var TwoFactorProviderRegistry
     */
    private $providerRegistry;

    /**
     * @var TwoFactorProviderPreparationRecorder
     */
    private $preparationRecorder;

    /**
     * @var TwoFactorToken|null
     */
    private $twoFactorToken;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(
        TwoFactorProviderRegistry $providerRegistry,
        TwoFactorProviderPreparationRecorder $preparationRecorder,
        ?LoggerInterface $logger
    )
    {
        $this->providerRegistry = $providerRegistry;
        $this->preparationRecorder = $preparationRecorder;
        $this->logger = $logger;
    }

    public function onAuthenticationSuccess(AuthenticationEvent $event): void
    {
        $token = $event->getAuthenticationToken();
        if ($token instanceof TwoFactorToken) {
            // After login, when the token is a TwoFactorToken, execute preparation
            $this->twoFactorToken = $token;
        }
    }

    public function onTwoFactorAuthenticationFormEvent(TwoFactorAuthenticationEvent $event): void
    {
        // Whenever two-factor authentication form is shown, execute preparation
        $this->twoFactorToken = $event->getToken();
    }

    public function onTwoFactorAuthenticationRequiredEvent(TwoFactorAuthenticationEvent $event): void
    {
        // Whenever two-factor authentication is required, execute preparation
        $this->twoFactorToken = $event->getToken();
    }

    public function onKernelFinishRequest(FinishRequestEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        if (!($this->twoFactorToken instanceof TwoFactorToken)) {
            return;
        }

        $providerName = $this->twoFactorToken->getCurrentTwoFactorProvider();
        $firewallName = $this->twoFactorToken->getProviderKey();

        if ($this->preparationRecorder->isProviderPrepared($firewallName, $providerName)) {
            if ($this->logger) {
                $this->logger->info(sprintf('Two-factor provider "%s" was already prepared.', $providerName));
            }
            return;
        }

        $user = $this->twoFactorToken->getUser();
        $this->providerRegistry->getProvider($providerName)->prepareAuthentication($user);
        $this->preparationRecorder->recordProviderIsPrepared($firewallName, $providerName);

        if ($this->logger) {
            $this->logger->info(sprintf('Two-factor provider "%s" prepared.', $providerName));
        }
    }
}
