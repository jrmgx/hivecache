<?php

namespace App\Admin\Controller;

use App\Admin\Form\ChangePasswordFormType;
use App\Admin\Form\RegistrationFormType;
use App\Admin\Form\ResetPasswordRequestFormType;
use App\Entity\Admin;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route(path: '/admin', name: 'admin_')]
class SecurityController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly AdminRepository $adminRepository,
    ) {
    }

    /** @return array<mixed> */
    #[Route(path: '/login', name: 'login')]
    #[Template('admin/login.html.twig')]
    public function login(): array
    {
        return [
            'last_username' => $this->authenticationUtils->getLastUsername(),
            'error' => $this->authenticationUtils->getLastAuthenticationError(),
        ];
    }

    /** @return Response|array<mixed> */
    #[Route(path: '/register', name: 'register')]
    #[Template('admin/register.html.twig')]
    public function register(Request $request): Response|array
    {
        $admin = new Admin();
        $form = $this->createForm(RegistrationFormType::class, $admin);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $admin->setPassword($this->userPasswordHasher->hashPassword($admin, $plainPassword));

            $this->entityManager->persist($admin);
            $this->entityManager->flush();

            $this->addFlash('success', 'Registration succeeded! Now an Admin needs to activate your account.');

            return $this->redirectToRoute('admin_register');
        }

        return compact('form');
    }

    /** @return Response|array<mixed> */
    #[Route('/reset-password', name: 'forgot_password_request')]
    #[Template('admin/reset_password/request.html.twig')]
    public function request(Request $request): Response|array
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail($email);
        }

        return compact('form');
    }

    /** @return array<mixed> */
    #[Route('/reset-password/check-email', name: 'check_email')]
    #[Template('admin/reset_password/check_email.html.twig')]
    public function checkEmail(): array
    {
        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether a user was found with the given email address or not
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return compact('resetToken');
    }

    /** @return Response|array<mixed> */
    #[Route('/reset-password/reset/{token}', name: 'reset_password')]
    #[Template('admin/reset_password/reset.html.twig')]
    public function reset(Request $request, ?string $token = null): Response|array
    {
        if ($token) {
            // We store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('admin_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            /** @var Admin $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', \sprintf(
                '%s - %s',
                $this->translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $this->translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('admin_forgot_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Encode(hash) the plain password, and set it.
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            /* @noinspection PhpRouteMissingInspection This route is defined in EasyAdmin */
            return $this->redirectToRoute('admin');
        }

        return compact('form');
    }

    #[Route(path: '/logout', name: 'logout')]
    public function logout(): never
    {
        throw new \RuntimeException();
    }

    private function processSendingPasswordResetEmail(string $emailFormData): RedirectResponse
    {
        $user = $this->adminRepository->loadUserByIdentifier($emailFormData);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('admin_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return $this->redirectToRoute('admin_check_email');
        }

        $email = new TemplatedEmail()
            ->from(new Address('security@hivecache.test', 'HiveCache Security'))
            ->to($user->getUserIdentifier())
            ->subject('Your password reset request')
            ->htmlTemplate('admin/reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ])
        ;

        $this->mailer->send($email);

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('admin_check_email');
    }
}
