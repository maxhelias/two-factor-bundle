<?php

declare(strict_types=1);

namespace Scheb\TwoFactorBundle\Tests\DependencyInjection;

use Scheb\TwoFactorBundle\DependencyInjection\SchebTwoFactorExtension;
use Scheb\TwoFactorBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Parser;

class SchebTwoFactorExtensionTest extends TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $container;

    /**
     * @var SchebTwoFactorExtension
     */
    private $extension;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->extension = new SchebTwoFactorExtension();

        //Stub services
        $this->container->setDefinition('acme_test.persister', new Definition());
        $this->container->setDefinition('acme_test.mailer', new Definition());
    }

    /**
     * @test
     */
    public function load_emptyConfig_setDefaultValues(): void
    {
        $config = $this->getEmptyConfig();
        $this->extension->load([$config], $this->container);

        $this->assertParameter(null, 'scheb_two_factor.model_manager_name');
        $this->assertParameter('no-reply@example.com', 'scheb_two_factor.email.sender_email');
        $this->assertParameter(null, 'scheb_two_factor.email.sender_name');
        $this->assertParameter('@SchebTwoFactor/Authentication/form.html.twig', 'scheb_two_factor.email.template');
        $this->assertParameter(4, 'scheb_two_factor.email.digits');
        $this->assertParameter(null, 'scheb_two_factor.google.server_name');
        $this->assertParameter(null, 'scheb_two_factor.google.issuer');
        $this->assertParameter('@SchebTwoFactor/Authentication/form.html.twig', 'scheb_two_factor.google.template');
        $this->assertParameter(6, 'scheb_two_factor.google.digits');
        $this->assertParameter(1, 'scheb_two_factor.google.window');
        $this->assertParameter(null, 'scheb_two_factor.totp.issuer');
        $this->assertParameter(null, 'scheb_two_factor.totp.server_name');
        $this->assertParameter(1, 'scheb_two_factor.totp.window');
        $this->assertParameter([], 'scheb_two_factor.totp.parameters');
        $this->assertParameter('@SchebTwoFactor/Authentication/form.html.twig', 'scheb_two_factor.totp.template');
        $this->assertParameter(false, 'scheb_two_factor.trusted_device.enabled');
        $this->assertParameter(5184000, 'scheb_two_factor.trusted_device.lifetime');
        $this->assertParameter(false, 'scheb_two_factor.trusted_device.extend_lifetime');
        $this->assertParameter('trusted_device', 'scheb_two_factor.trusted_device.cookie_name');
        $this->assertParameter(false, 'scheb_two_factor.trusted_device.cookie_secure');
        $this->assertParameter('lax', 'scheb_two_factor.trusted_device.cookie_same_site');
        $this->assertParameter(null, 'scheb_two_factor.trusted_device.cookie_domain');
        $this->assertParameter(['Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken'], 'scheb_two_factor.security_tokens');
        $this->assertParameter([], 'scheb_two_factor.ip_whitelist');
    }

    /**
     * @test
     */
    public function load_fullConfig_setConfigValues(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertParameter('alternative', 'scheb_two_factor.model_manager_name');
        $this->assertParameter('me@example.com', 'scheb_two_factor.email.sender_email');
        $this->assertParameter('Sender Name', 'scheb_two_factor.email.sender_name');
        $this->assertParameter('AcmeTestBundle:Authentication:emailForm.html.twig', 'scheb_two_factor.email.template');
        $this->assertParameter(6, 'scheb_two_factor.email.digits');
        $this->assertParameter('Server Name Google', 'scheb_two_factor.google.server_name');
        $this->assertParameter('Issuer Google', 'scheb_two_factor.google.issuer');
        $this->assertParameter('AcmeTestBundle:Authentication:googleForm.html.twig', 'scheb_two_factor.google.template');
        $this->assertParameter(8, 'scheb_two_factor.google.digits');
        $this->assertParameter(2, 'scheb_two_factor.google.window');
        $this->assertParameter('Issuer TOTP', 'scheb_two_factor.totp.issuer');
        $this->assertParameter('Server Name TOTP', 'scheb_two_factor.totp.server_name');
        $this->assertParameter(2, 'scheb_two_factor.totp.window');
        $this->assertParameter(['image' => 'http://foo/bar.png'], 'scheb_two_factor.totp.parameters');
        $this->assertParameter('AcmeTestBundle:Authentication:totpForm.html.twig', 'scheb_two_factor.totp.template');
        $this->assertParameter(true, 'scheb_two_factor.trusted_device.enabled');
        $this->assertParameter(2592000, 'scheb_two_factor.trusted_device.lifetime');
        $this->assertParameter(true, 'scheb_two_factor.trusted_device.extend_lifetime');
        $this->assertParameter('trusted_cookie', 'scheb_two_factor.trusted_device.cookie_name');
        $this->assertParameter(true, 'scheb_two_factor.trusted_device.cookie_secure');
        $this->assertParameter(null, 'scheb_two_factor.trusted_device.cookie_same_site');
        $this->assertParameter('cookie.example.org', 'scheb_two_factor.trusted_device.cookie_domain');
        $this->assertParameter(['Symfony\Component\Security\Core\Authentication\Token\SomeToken'], 'scheb_two_factor.security_tokens');
        $this->assertParameter(['127.0.0.1'], 'scheb_two_factor.ip_whitelist');
    }

    /**
     * @test
     */
    public function load_noAuthEnabled_notLoadServices(): void
    {
        $config = $this->getEmptyConfig();
        $this->extension->load([$config], $this->container);

        //Google
        $this->assertNotHasDefinition('scheb_two_factor.security.google_authenticator');
        $this->assertNotHasDefinition('scheb_two_factor.security.google.provider');

        //TOTP
        $this->assertNotHasDefinition('scheb_two_factor.security.totp_authenticator');
        $this->assertNotHasDefinition('scheb_two_factor.security.totp.provider');

        //Email
        $this->assertNotHasDefinition('scheb_two_factor.security.email.default_auth_code_mailer');
        $this->assertNotHasDefinition('scheb_two_factor.security.email.code_generator');
        $this->assertNotHasDefinition('scheb_two_factor.security.email.provider');
    }

    /**
     * @test
     */
    public function load_googleAuthEnabled_loadGoogleServices(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertHasDefinition('scheb_two_factor.security.google_totp_factory');
        $this->assertHasDefinition('scheb_two_factor.security.google_authenticator');
        $this->assertHasDefinition('scheb_two_factor.security.google.form_renderer');
        $this->assertHasDefinition('scheb_two_factor.security.google.provider');
    }

    /**
     * @test
     */
    public function load_totpAuthEnabled_loadTotpFactoryServices(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertHasDefinition('scheb_two_factor.security.totp_factory');
        $this->assertHasDefinition('scheb_two_factor.security.totp_authenticator');
        $this->assertHasDefinition('scheb_two_factor.security.totp.form_renderer');
        $this->assertHasDefinition('scheb_two_factor.security.totp.provider');
    }

    /**
     * @test
     */
    public function load_emailAuthEnabled_loadEmailServices(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertHasDefinition('scheb_two_factor.security.email.default_auth_code_mailer');
        $this->assertHasDefinition('scheb_two_factor.security.email.default_code_generator');
        $this->assertHasDefinition('scheb_two_factor.security.email.provider');
    }

    /**
     * @test
     */
    public function load_defaultMailer_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $config['email']['enabled'] = true; // Enable email provider
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.security.email.auth_code_mailer', 'scheb_two_factor.security.email.default_auth_code_mailer');
    }

    /**
     * @test
     */
    public function load_alternativeMailer_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.security.email.auth_code_mailer', 'acme_test.mailer');
    }

    /**
     * @test
     */
    public function load_defaultCodeGenerator_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $config['email']['enabled'] = true; // Enable email provider
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.security.email.code_generator', 'scheb_two_factor.security.email.default_code_generator');
    }

    /**
     * @test
     */
    public function load_alternativeCodeGenerator_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.security.email.code_generator', 'acme_test.code_generator');
    }

    /**
     * @test
     */
    public function load_defaultPersister_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.persister', 'scheb_two_factor.persister.doctrine');
    }

    /**
     * @test
     */
    public function load_alternativePersister_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.persister', 'acme_test.persister');
    }

    /**
     * @test
     */
    public function load_disabledTrustedDeviceManager_nullAlias(): void
    {
        $config = $this->getEmptyConfig();
        $config['trusted_device']['enabled'] = false;
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.trusted_device_manager', 'scheb_two_factor.null_trusted_device_manager');
    }

    /**
     * @test
     */
    public function load_enabledTrustedDeviceManager_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $config['trusted_device']['enabled'] = true;
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.trusted_device_manager', 'scheb_two_factor.default_trusted_device_manager');
    }

    /**
     * @test
     */
    public function load_alternativeTrustedDeviceManager_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $config['trusted_device']['enabled'] = true;
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.trusted_device_manager', 'acme_test.trusted_device_manager');
    }

    /**
     * @test
     */
    public function load_disabledBackupCodeManager_nullAlias(): void
    {
        $config = $this->getEmptyConfig();
        $config['backup_codes']['enabled'] = false;
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.backup_code_manager', 'scheb_two_factor.null_backup_code_manager');
    }

    /**
     * @test
     */
    public function load_enabledBackupCodeManager_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $config['backup_codes']['enabled'] = true;
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.backup_code_manager', 'scheb_two_factor.default_backup_code_manager');
    }

    /**
     * @test
     */
    public function load_alternativeBackupCodeManager_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $config['backup_codes']['enabled'] = true;
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.backup_code_manager', 'acme_test.backup_code_manager');
    }

    /**
     * @test
     */
    public function load_defaultIpWhitelistProvider_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.ip_whitelist_provider', 'scheb_two_factor.default_ip_whitelist_provider');
    }

    /**
     * @test
     */
    public function load_alternativeIpWhitelistProvider_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.ip_whitelist_provider', 'acme_test.ip_whitelist_provider');
    }

    /**
     * @test
     */
    public function load_defaultTokenFactory_defaultAlias(): void
    {
        $config = $this->getEmptyConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.token_factory', 'scheb_two_factor.default_token_factory');
    }

    /**
     * @test
     */
    public function load_alternativeTokenFactory_replaceAlias(): void
    {
        $config = $this->getFullConfig();
        $this->extension->load([$config], $this->container);

        $this->assertAlias('scheb_two_factor.token_factory', 'acme_test.two_factor_token_factory');
    }

    private function getEmptyConfig(): ?array
    {
        $yaml = '';
        $parser = new Parser();

        return $parser->parse($yaml);
    }

    private function getFullConfig(): array
    {
        $yaml = <<<EOF
persister: acme_test.persister
model_manager_name: "alternative"
security_tokens:
    - Symfony\Component\Security\Core\Authentication\Token\SomeToken
ip_whitelist:
    - 127.0.0.1
ip_whitelist_provider: acme_test.ip_whitelist_provider
two_factor_token_factory: acme_test.two_factor_token_factory
trusted_device:
    enabled: true
    manager: acme_test.trusted_device_manager
    lifetime: 2592000
    extend_lifetime: true
    cookie_name: trusted_cookie
    cookie_secure: true
    cookie_same_site: null
    cookie_domain: cookie.example.org
backup_codes:
    enabled: true
    manager: acme_test.backup_code_manager
email:
    enabled: true
    mailer: acme_test.mailer
    code_generator: acme_test.code_generator
    sender_email: me@example.com
    sender_name: Sender Name
    template: AcmeTestBundle:Authentication:emailForm.html.twig
    digits: 6
google:
    enabled: true
    issuer: Issuer Google
    server_name: Server Name Google
    template: AcmeTestBundle:Authentication:googleForm.html.twig
    digits: 8
    window: 2
totp:
    enabled: true
    issuer: Issuer TOTP
    server_name: Server Name TOTP
    window: 2
    parameters:
        image: http://foo/bar.png
    template: AcmeTestBundle:Authentication:totpForm.html.twig
EOF;
        $parser = new Parser();

        return $parser->parse($yaml);
    }

    private function assertParameter($value, $key): void
    {
        $this->assertEquals($value, $this->container->getParameter($key), sprintf('%s parameter is correct', $key));
    }

    private function assertHasDefinition($id): void
    {
        $this->assertTrue($this->container->hasDefinition($id), 'Service "'.$id.'" must be defined.');
    }

    private function assertNotHasDefinition($id): void
    {
        $this->assertFalse($this->container->hasDefinition($id), 'Service "'.$id.'" must NOT be defined.');
    }

    private function assertAlias($id, $aliasId): void
    {
        $this->assertTrue($this->container->hasAlias($id), 'Alias "'.$id.'" must be defined.');
        $alias = $this->container->getAlias($id);
        $this->assertEquals($aliasId, (string) $alias, 'Alias "'.$id.'" must be alias for "'.$aliasId.'".');
    }
}
