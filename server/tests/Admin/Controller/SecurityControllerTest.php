<?php

namespace App\Tests\Admin\Controller;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use App\Tests\BaseAdminTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

class SecurityControllerTest extends BaseAdminTestCase
{
    use MailerAssertionsTrait;

    private const array HTML_HEADERS = ['HTTP_ACCEPT' => 'text/html'];

    public function testAdminAccessFlow(): void
    {
        $this->client->request('GET', '/admin', server: self::HTML_HEADERS);
        $this->assertResponseRedirects('/admin/login', 302, 'Unauthenticated access to /admin should be redirected to /admin/login');

        $this->client->request('GET', '/admin/login', server: self::HTML_HEADERS);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Login');

        $this->client->request('GET', '/admin/register', server: self::HTML_HEADERS);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Register');

        $crawler = $this->client->getCrawler();
        $form = $crawler->selectButton('Register')->form([
            'registration_form[email]' => 'admin@test.com',
            'registration_form[plainPassword]' => 'password123',
        ]);

        $this->client->submit($form);
        $this->assertResponseStatusCodeSame(302);
        while ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
        $this->assertResponseStatusCodeSame(200);
        $this->assertSelectorTextContains('body', 'Registration succeeded!');

        $this->client->request('GET', '/admin/login', server: self::HTML_HEADERS);
        $this->client->submit($this->client->getCrawler()->selectButton('Login')->form([
            '_username' => 'admin@test.com',
            '_password' => 'password123',
        ]));
        while ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
        $this->client->request('GET', '/admin', server: self::HTML_HEADERS);
        $this->assertResponseStatusCodeSame(403, 'Non-activated admin should not access admin area');

        /** @var AdminRepository $adminRepository */
        $adminRepository = $this->entityManager->getRepository(Admin::class);
        $admin = $adminRepository->findOneBy(['email' => 'admin@test.com']);
        $this->assertNotNull($admin);
        $admin->setRoles(['ROLE_ADMIN']);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/login', server: self::HTML_HEADERS);
        $this->assertResponseIsSuccessful();
        $loginForm = $this->client->getCrawler()->selectButton('Login')->form([
            '_username' => 'admin@test.com',
            '_password' => 'password123',
        ]);
        $this->client->submit($loginForm);
        $this->assertResponseStatusCodeSame(302);
        while ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/admin', $this->client->getRequest()->getUri());
    }

    public function testResetPasswordFlow(): void
    {
        $admin = new Admin();
        $admin->email = 'admin@test.com';
        $admin->setPassword($this->container->get('security.password_hasher')->hashPassword($admin, 'password123'));
        $admin->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->request('GET', '/admin/reset-password', server: self::HTML_HEADERS);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Reset your password');

        $this->client->submit($this->client->getCrawler()->selectButton('Send password reset email')->form([
            'reset_password_request_form[email]' => 'admin@test.com',
        ]));
        $this->assertResponseRedirects();
        $this->assertQueuedEmailCount(1);
        $email = $this->getMailerMessage(0);
        $this->assertNotNull($email);
        $htmlBody = $email->getHtmlBody();
        $this->assertIsString($htmlBody);
        $this->assertMatchesRegularExpression('#href="([^"]*admin/reset-password/reset/[^"]+)"#', $htmlBody);
        preg_match('#href="([^"]+)"#', $htmlBody, $matches);
        $resetUrl = $matches[1];

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Password Reset Email Sent');

        $this->client->request('GET', $resetUrl);
        while ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Reset your password');

        $this->client->submit($this->client->getCrawler()->selectButton('Reset password')->form([
            'change_password_form[plainPassword][first]' => 'NewPassword123!',
            'change_password_form[plainPassword][second]' => 'NewPassword123!',
        ]));
        $this->assertResponseRedirects();
        while ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/admin', $this->client->getRequest()->getUri());
    }
}
