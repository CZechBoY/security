<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Bridges\SecurityDI;

use Nette;


/**
 * Security extension for Nette DI.
 */
class SecurityExtension extends Nette\DI\CompilerExtension
{
	public $defaults = [
		'debugger' => TRUE,
		'users' => [], // of [user => password] or [user => ['password' => password, 'roles' => [role]]]
		'roles' => [], // of [role => parents]
		'resources' => [], // of [resource => parents]
	];

	/** @var bool */
	private $debugMode;


	public function __construct($debugMode = FALSE)
	{
		$this->debugMode = $debugMode;
	}


	public function loadConfiguration()
	{
		$config = $this->validateConfig($this->defaults);
		$container = $this->getContainerBuilder();

		$container->addDefinition($this->prefix('userStorage'))
			->setClass(Nette\Security\IUserStorage::class)
			->setFactory(Nette\Http\UserStorage::class);

		$user = $container->addDefinition($this->prefix('user'))
			->setClass(Nette\Security\User::class);

		if ($this->debugMode && $config['debugger']) {
			$user->addSetup('@Tracy\Bar::addPanel', [
				new Nette\DI\Statement(Nette\Bridges\SecurityTracy\UserPanel::class),
			]);
		}

		if ($config['users']) {
			$usersList = $usersRoles = [];
			foreach ($config['users'] as $username => $data) {
				$data = is_array($data) ? $data : ['password' => $data];
				$this->validateConfig(['password' => NULL, 'roles' => NULL], $data, $this->prefix("security.users.$username"));
				$usersList[$username] = $data['password'];
				$usersRoles[$username] = isset($data['roles']) ? $data['roles'] : NULL;
			}

			$container->addDefinition($this->prefix('authenticator'))
				->setClass(Nette\Security\IAuthenticator::class)
				->setFactory(Nette\Security\SimpleAuthenticator::class, [$usersList, $usersRoles]);

			if ($this->name === 'security') {
				$container->addAlias('nette.authenticator', $this->prefix('authenticator'));
			}
		}

		if ($config['roles'] || $config['resources']) {
			$authorizator = $container->addDefinition($this->prefix('authorizator'))
				->setClass(Nette\Security\IAuthorizator::class)
				->setFactory(Nette\Security\Permission::class);

			foreach ($config['roles'] as $role => $parents) {
				$authorizator->addSetup('addRole', [$role, $parents]);
			}
			foreach ($config['resources'] as $resource => $parents) {
				$authorizator->addSetup('addResource', [$resource, $parents]);
			}

			if ($this->name === 'security') {
				$container->addAlias('nette.authorizator', $this->prefix('authorizator'));
			}
		}

		if ($this->name === 'security') {
			$container->addAlias('user', $this->prefix('user'));
			$container->addAlias('nette.userStorage', $this->prefix('userStorage'));
		}
	}

}
