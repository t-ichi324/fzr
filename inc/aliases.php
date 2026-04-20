<?php

/**
 * Fzr 後方互換エイリアス
 * require 'vendor/fzr/aliases.php'; で既存のグローバルクラス名が使用可能になる
 *
 * 使用例:
 *   // エイリアスなし
 *   \Fzr\Request::get('id');
 *
 *   // エイリアスあり（min-web互換）
 *   Request::get('id');
 */

// Core
class_alias(\Fzr\Engine::class,        'Engine');
class_alias(\Fzr\Context::class,       'Context');
class_alias(\Fzr\Loader::class,        'Loader');
class_alias(\Fzr\Config::class,        'Config');

// HTTP
class_alias(\Fzr\Request::class,       'Request');
class_alias(\Fzr\Response::class,      'Response');
class_alias(\Fzr\Route::class,         'Route');
class_alias(\Fzr\Render::class,        'Render');
class_alias(\Fzr\Cookie::class,        'Cookie');
class_alias(\Fzr\Session::class,       'Session');

// Framework
class_alias(\Fzr\Env::class,           'Env');
class_alias(\Fzr\Logger::class,        'Logger');
class_alias(\Fzr\Security::class,      'Security');
class_alias(\Fzr\Auth::class,          'Auth');
class_alias(\Fzr\Path::class,          'Path');
class_alias(\Fzr\Url::class,           'Url');
class_alias(\Fzr\Cache::class,         'Cache');
class_alias(\Fzr\Message::class,       'Message');
class_alias(\Fzr\Breadcrumb::class,    'Breadcrumb');

// Data
class_alias(\Fzr\Model::class,         'Model');
class_alias(\Fzr\Collection::class,    'Collection');
class_alias(\Fzr\Form::class,          'Form');
class_alias(\Fzr\FormValidator::class, 'FormValidator');
class_alias(\Fzr\FormRender::class,    'FormRender');
class_alias(\Fzr\FileInfo::class,      'FileInfo');
class_alias(\Fzr\DirectoryInfo::class, 'DirectoryInfo');
class_alias(\Fzr\Storage::class,       'Storage');

// Controller
class_alias(\Fzr\Controller::class,    'Controller');
class_alias(\Fzr\AuthController::class, 'AuthController');
class_alias(\Fzr\HttpException::class,  'HttpException');

// DB
class_alias(\Fzr\Db\Db::class,         'Db');
class_alias(\Fzr\Db\Connection::class, 'DbConnection');
class_alias(\Fzr\Db\Query::class,      'DbQuery');
class_alias(\Fzr\Db\Result::class,     'DbResult');
class_alias(\Fzr\Db\Entity::class,     'Entity');
class_alias(\Fzr\Db\Migration::class,  'DbMigration');
class_alias(\Fzr\Db\LiteDb::class,     'LiteDb');
