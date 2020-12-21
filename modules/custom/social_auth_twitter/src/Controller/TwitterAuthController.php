<?php

namespace Drupal\social_auth_twitter\Controller;

use Drupal\social_auth_extra\Controller\AuthControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Manages requests to Twitter API.
 */
class TwitterAuthController extends AuthControllerBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $request;

  /**
   * Contains access token to work with API.
   *
   * @var \Abraham\TwitterOAuth\TwitterOAuth
   */
  protected $accessToken;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $controller */
    $controller = parent::create($container);

    $controller->request = $container->get('request_stack')->getCurrentRequest();

    return $controller;
  }

  /**
   * Response for path 'user/login/twitter'.
   *
   * Redirects the user to Twitter for authentication.
   */
  public function userLogin() {
    return $this->getRedirectResponse('login');
  }

  /**
   * Authorizes the user after redirect from Twitter.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a RedirectResponse.
   */
  public function userLoginCallback() {
    $sdk = $this->getSdk('login');

    if ($sdk instanceof RedirectResponse) {
      return $sdk;
    }

    $this->authManager->setSdk($sdk);
    $profile = $this->getProfile('register');

    if ($profile instanceof RedirectResponse) {
      return $profile;
    }

    // Check whether user account exists. If account already exists,
    // authorize the user and redirect him to the account page.
    $account = $this->entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'twitter_id' => $profile->id,
      ]);

    if (!$account) {
      return $this->redirect('social_auth_twitter.user_login_notice');
    }

    $account = current($account);

    if (!$account->get('status')->value) {
      drupal_set_message($this->t('Your account is blocked. Contact the site administrator.'), 'error');
      return $this->redirect('user.login');
    }

    // Authorize the user and redirect him to the front page.
    user_login_finalize($account);

    return $this->redirect('<front>');
  }

  /**
   * Registers the new account after redirect from Twitter.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a RedirectResponse.
   */
  public function userRegisterCallback() {
    $sdk = $this->getSdk('register');

    if ($sdk instanceof RedirectResponse) {
      return $sdk;
    }

    $this->authManager->setSdk($sdk);
    $profile = $this->getProfile('register');

    if ($profile instanceof RedirectResponse) {
      return $profile;
    }

    // Check whether user account exists. If account already exists,
    // authorize the user and redirect him to the account page.
    $account = $this->entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'twitter_id' => $profile->id,
      ]);

    if ($account) {
      $account = current($account);

      if (!$account->get('status')->value) {
        drupal_set_message($this->t('You already have account on this site, but your account is blocked. Contact the site administrator.'), 'error');
        return $this->redirect('user.register');
      }

      user_login_finalize($account);

      return $this->redirect('entity.user.canonical', [
        'user' => $account->id(),
      ]);
    }

    // Save email and name to storage to use for auto fill the registration
    // form.
    $data_handler = $this->networkManager->createInstance('social_auth_twitter')->getDataHandler();
    $data_handler->set('access_token', $this->accessToken);
    $data_handler->set('mail', NULL);
    $data_handler->set('name', $this->authManager->getUsername());

    drupal_set_message($this->t('You are now connected with @network, please continue registration', [
      '@network' => $this->t('Twitter'),
    ]));

    return $this->redirect('user.register', [
      'provider' => 'twitter',
    ]);
  }

  /**
   * Loads access token, then loads profile.
   *
   * @param string $type
   *   The type.
   *
   * @return object
   *   Returns an object.
   */
  public function getProfile($type) {
    $sdk = $this->getSdk($type);
    $data_handler = $this->networkManager->createInstance('social_auth_twitter')->getDataHandler();

    // Get the OAuth token from Twitter.
    if (!($oauth_token = $data_handler->get('oauth_token')) || !($oauth_token_secret = $data_handler->get('oauth_token_secret'))) {
      drupal_set_message($this->t('@network login failed. Token is not valid.', [
        '@network' => $this->t('Twitter'),
      ]), 'error');
      return $this->redirect('user.' . $type);
    }

    $this->authManager->setAccessToken([
      'oauth_token' => $oauth_token,
      'oauth_token_secret' => $oauth_token_secret,
    ]);

    // Gets the permanent access token.
    $this->accessToken = $sdk->oauth('oauth/access_token', [
      'oauth_verifier' => $this->request->get('oauth_verifier'),
    ]);

    $this->authManager->setAccessToken([
      'oauth_token' => $this->accessToken['oauth_token'],
      'oauth_token_secret' => $this->accessToken['oauth_token_secret'],
    ]);

    // Get user's profile from Twitter API.
    if (!($profile = $this->authManager->getProfile()) || !$this->authManager->getAccountId()) {
      drupal_set_message($this->t('@network login failed, could not load @network profile. Contact the site administrator.', [
        '@network' => $this->t('Twitter'),
      ]), 'error');
      return $this->redirect('user.' . $type);
    }

    return $profile;
  }

}
