<?php
namespace App;

use Ajax\Middleware\AjaxMiddleware;
use App\Authentication\ActAsIdentity;
use App\Core\UserCache;
use App\Event\FlashTrait;
use App\Exception\ForbiddenRedirectException;
use App\Exception\LockedIdentityException;
use App\Http\Middleware\ActAsMiddleware;
use App\Middleware\AffiliateConfigurationLoader;
use App\Middleware\ConfigurationLoader;
use App\Http\Middleware\CookiePathMiddleware;
use App\Http\Middleware\CsrfProtectionMiddleware;
use App\Policy\TypeResolver;
use Authentication\AuthenticationService;
use Authentication\AuthenticationServiceProviderInterface;
use Authentication\Authenticator\UnauthenticatedException;
use Authentication\Identifier\IdentifierInterface;
use Authentication\Middleware\AuthenticationMiddleware;
use Authorization\AuthorizationService;
use Authorization\AuthorizationServiceProviderInterface;
use Authorization\Exception\ForbiddenException;
use Authorization\Exception\MissingIdentityException;
use Authorization\Middleware\AuthorizationMiddleware;
use Authorization\Policy\OrmResolver;
use Authorization\Policy\ResolverCollection;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Exception\MissingPluginException;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\EncryptedCookieMiddleware;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\Middleware\LocaleSelectorMiddleware;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use Cake\Utility\Security;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Application setup class.
 *
 * This defines the bootstrapping logic and middleware layers you
 * want to use in your application.
 */
class Application extends BaseApplication implements AuthenticationServiceProviderInterface, AuthorizationServiceProviderInterface {

	use FlashTrait;

	/**
	 * {@inheritDoc}
	 */
	public function bootstrap() {
		// Call parent to load bootstrap from files.
		parent::bootstrap();

		if (PHP_SAPI === 'cli') {
			try {
				$this->addPlugin('Bake');
			} catch (MissingPluginException $e) {
				// Do not halt if the plugin is missing
			}

			$this->addPlugin('Scheduler', ['autoload' => true]);
		} else {
			Configure::write('Installer.config', ['installer']);
			$this->addPlugin('Installer', ['bootstrap' => true, 'routes' => true]);
		}

		/*
		 * Only try to load DebugKit in development mode
		 * Debug Kit should not be installed on a production system
		 */
		if (Configure::read('debug')) {
			$this->addPlugin('DebugKit', ['bootstrap' => true]);
		}

		$this->addPlugin('Authentication');
		$this->addPlugin('Authorization');
		$this->addPlugin('Ajax');
		$this->addPlugin('Bootstrap', ['bootstrap' => true]);
		$this->addPlugin('Josegonzalez/Upload');
		$this->addPlugin('Muffin/Footprint');
		$this->addPlugin('Cors', ['bootstrap' => true, 'routes' => false]);
		$this->addPlugin('Migrations');

		$this->addPlugin('ZuluruBootstrap');
		$this->addPlugin('ZuluruJquery');
	}

	/**
	 * Returns a service provider instance.
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface $request Request
	 * @param \Psr\Http\Message\ResponseInterface $response Response
	 * @return \Authentication\AuthenticationServiceInterface
	 */
	public function getAuthenticationService(ServerRequestInterface $request, ResponseInterface $response) {
		$service = new AuthenticationService();
		$authenticators = Configure::read('Security.authenticators');

		// The fields to use for identification
		$users_table = TableRegistry::get(Configure::read('Security.authModel'));
		$fields = [
			IdentifierInterface::CREDENTIAL_USERNAME => $users_table->userField,
			IdentifierInterface::CREDENTIAL_PASSWORD => $users_table->pwdField,
		];

		if (empty($authenticators)) {
			// If Zuluru is managing authentication alone, handle old passwords and migrate them
			$hashMethod = Configure::read('Security.hashMethod', 'sha256');
			$hasher = [
				'className' => 'Authentication.Fallback',
				'hashers' => [
					[
						'className' => 'Authentication.Default',
					],
					[
						'className' => 'Authentication.Legacy',
						'hashType' => $hashMethod,
					],
					[
						'className' => 'LegacyNoSalt',
						'hashType' => $hashMethod,
					],
				],
			];

			// Load the session-based authenticator
			$service->loadAuthenticator('Authentication.Session');

			// Add the cookie-based "remember me" authenticator
			$service->loadAuthenticator('Authentication.Cookie', [
				'loginUrl' => Router::url(Configure::read('App.urls.login')),
				'fields' => $fields,
				'cookie' => [
					'name' => 'ZuluruAuth',
					'expire' => new Time('+1 year'),
					'path' => '/' . trim($request->getAttribute('webroot'), '/'),
				],
			]);
		} else {
			// Load third-party authenticators. We don't load the session authenticator
			// directly here, but it may be used by these internally.
			foreach ($authenticators as $authenticator => $authenticator_config) {
				if (is_numeric($authenticator)) {
					$authenticator = $authenticator_config;
					$authenticator_config = [];
				}
				$authenticator_obj = $service->loadAuthenticator($authenticator, array_merge($authenticator_config, ['service' => $service]));
				if (property_exists($authenticator_obj, 'hasher')) {
					$hasher = $authenticator_obj->hasher;
				}
			}
		}

		// Add the password-based identifier, using configuration from above
		$service->loadIdentifier('Authentication.Password', [
			'fields' => $fields,
			'resolver' => [
				'className' => 'Authentication.Orm',
				'userModel' => Configure::read('Security.authModel'),
			],
			'passwordHasher' => $hasher,
		]);

		if ($request->is('json')) {
			// For JSON requests, we allow JWT authentication, as well as form-based login through the token URL
			$service->loadIdentifier('Authentication.JwtSubject', [
				'tokenField' => $users_table->getPrimaryKey(),
				'resolver' => [
					'className' => 'Authentication.Orm',
					'userModel' => Configure::read('Security.authModel'),
				],
			]);

			$service->loadAuthenticator('Authentication.Form', [
				'fields' => $fields,
				'loginUrl' => '/users/token.json',
			]);

			$service->loadAuthenticator('Authentication.Jwt', [
				'returnPayload' => false
			]);
		} else if (empty($authenticators)) {
			// For non-JSON requests, we allow form-based login through the standard login URL
			$service->loadAuthenticator('Authentication.Form', [
				'fields' => $fields,
				'loginUrl' => Router::url(Configure::read('App.urls.login')),
			]);
		}

		return $service;
	}

	public function getAuthorizationService(ServerRequestInterface $request, ResponseInterface $response) {
		$resolver = new ResolverCollection([
			new OrmResolver(),
			new TypeResolver([
				'Controller' => function ($name) {
					if (substr($name, -10) != 'Controller') {
						return false;
					}
					return Inflector::singularize(substr($name, 0, -10));
				},
				'Model\\Table' => function ($name) {
					if (substr($name, -5) != 'Table') {
						return false;
					}
					return Inflector::singularize(substr($name, 0, -5));
				},
				'Model\\Entity' => false,
				'Authorization' => function ($name, $resource, $resolver) {
					return $resource->getResolver($resolver);
				},
			]),
		]);

		return new AuthorizationService($resolver);
	}

	public function getLocales() {
		$translations = Cache::read('available_translations');
		$translation_strings = Cache::read('available_translation_strings');
		if (!$translations || !$translation_strings) {
			$translations = ['en' => 'English'];
			$translation_strings = ["en: 'English'"];
			$dir = opendir(APP . 'Locale');
			if ($dir) {
				while (false !== ($entry = readdir($dir))) {
					if (file_exists(APP . 'Locale' . DS . $entry . DS . 'default.po')) {
						$name = \Locale::getDisplayName($entry, $entry);
						if ($name != $entry) {
							$translations[$entry] = $name;
							$translation_strings[] = "$entry: '$name'";
						}
					}
				}
			}
			Cache::write('available_translations', $translations);
			Cache::write('available_translation_strings', $translation_strings);
		}
		Configure::write('available_translations', $translations);
		Configure::write('available_translation_strings', implode(', ', $translation_strings));

		return array_keys($translations);
	}

	/**
	 * Setup the middleware queue your application will use.
	 *
	 * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
	 * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
	 */
	public function middleware($middlewareQueue) {
		$middlewareQueue
			// Catch any exceptions in the lower layers,
			// and make an error page/response
			->add(ErrorHandlerMiddleware::class)

			// Handle plugin/theme assets like CakePHP normally does.
			->add(new AssetMiddleware([
				'cacheTime' => Configure::read('Asset.cacheTime')
			]))

			->add(ConfigurationLoader::class)

			// Set the valid locales
			->add(new LocaleSelectorMiddleware($this->getLocales()))

			// Add routing middleware.
			// Routes collection cache enabled by default, to disable route caching
			// pass null as cacheConfig, example: `new RoutingMiddleware($this)`
			// you might want to disable this cache in case your routing is extremely simple
			->add(RoutingMiddleware::class)

			// Parse request bodies, allowing for JSON data in authentication
			->add(BodyParserMiddleware::class)

			// Add CSRF protection middleware.
			->add(function (
				ServerRequest $request,
				Response $response,
				callable $next
			) {
				$payment = ($request->getParam('controller') == 'Registrations' && $request->getParam('action') == 'payment');
				if (!$payment && !$request->is('json')) {
					$csrf = new CsrfProtectionMiddleware();

					// This will invoke the CSRF middleware's `__invoke()` handler,
					// just like it would when being registered via `add()`.
					return $csrf($request, $response, $next);
				}

				return $next($request, $response);
			})

			// Add encrypted cookie middleware.
			->add(new EncryptedCookieMiddleware(['ZuluruAuth'], Security::getSalt()))

			// Adjust cookie paths
			->add(CookiePathMiddleware::class)

			// Handle redirects and error messages for Ajax requests
			->add(new AjaxMiddleware(['viewClass' => 'Ajax']))

			// Add authentication
			->add(function (
				ServerRequest $request,
				Response $response,
				callable $next
			) {
				// TODO: Read these from site configuration
				if (Configure::read('feature.authenticate_through') == 'Zuluru') {
					$loginAction = Router::url(Configure::read('App.urls.login'), true);
				} else {
					$loginAction = Router::url(['controller' => 'Leagues', 'action' => 'index'], true);
				}

				$authentication = new AuthenticationMiddleware($this, [
					'unauthenticatedRedirect' => $loginAction,
					'queryParam' => 'redirect',
				]);

				return $authentication($request, $response, $next);
			})

			// Add unauthorized flash message
			->add(function($request, $response, $next) {
				try {
					return $next($request, $response);
				} catch (UnauthenticatedException $ex) {
					$this->Flash('error', __('You must login to access full site functionality.'));
					throw $ex;
				}
			})

			->add(new AuthorizationMiddleware($this, [
				'identityDecorator' => ActAsIdentity::class,
				'requireAuthorizationCheck' => Configure::read('debug'),
				'unauthorizedHandler' => [
					'className' => 'RedirectFlash',
					'unauthenticatedUrl' => Router::url(Configure::read('App.urls.login'), true),
					'unauthorizedUrl' => Router::url('/', true),
					'exceptions' => [
						MissingIdentityException::class => function($subject, $request, $response, $exception, $options) {
							$subject->Flash('error', __('You must login to access full site functionality.'));
							$url = $subject->getUrl($request, array_merge($options, ['referrer' => true, 'unauthenticated' => true]));

							return $response
								->withHeader('Location', $url)
								->withStatus($options['statusCode']);
						},
						ForbiddenRedirectException::class => function($subject, $request, $response, $exception, $options) {
							$url = $exception->getUrl();
							if (empty($url)) {
								$url = $subject->getUrl($request, array_merge($options, ['referrer' => false]));
							} else if (is_array($url)) {
								$url = Router::url($url, true);
							}
							if ($exception->getMessage()) {
								$subject->Flash($exception->getClass(), $exception->getMessage(), $exception->getOptions());
							}

							return $response
								->withHeader('Location', $url)
								->withStatus($options['statusCode']);
						},
						LockedIdentityException::class => function($subject, $request, $response, $exception, $options) {
							$subject->Flash('error', __('Your profile is currently {0}, so you can continue to use the site, but may be limited in some areas. To reactivate, {1}.',
								__(UserCache::getInstance()->read('Person.status')),
								__('contact {0}', Configure::read('email.admin_name'))
							));
							$url = $subject->getUrl($request, array_merge($options, ['referrer' => false]));

							return $response
								->withHeader('Location', $url)
								->withStatus($options['statusCode']);
						},
						ForbiddenException::class => function($subject, $request, $response, $exception, $options) {
							$subject->Flash('error', __('You do not have permission to access that page.'));
							$url = $subject->getUrl($request, array_merge($options, ['referrer' => false]));

							return $response
								->withHeader('Location', $url)
								->withStatus($options['statusCode']);
						},
					],
				],
			]))

			// Handle "act as" parameters in the URL
			->add(ActAsMiddleware::class)

			->add(AffiliateConfigurationLoader::class)
		;

		return $middlewareQueue;
	}
}
