<?php
/**
 * Copyright (c) 2013 Christopher Schäpers <christopher@schaepers.it>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

class OC_Core_Avatar_Controller {
	public static function getAvatar($args) {
		if (!\OC_User::isLoggedIn()) {
			$l = new \OC_L10n('core');
			header("HTTP/1.0 403 Forbidden");
			\OC_Template::printErrorPage($l->t("Permission denied"));
			return;
		}

		$user = stripslashes($args['user']);
		$size = (int)$args['size'];
		if ($size > 2048) {
			$size = 2048;
		}
		// Undefined size
		elseif ($size === 0) {
			$size = 64;
		}

		$ava = new \OC_Avatar();
		$image = $ava->get($user, $size);

		if ($image instanceof \OC_Image) {
			$image->show();
		} elseif ($image === false) {
			\OC_JSON::success(array('user' => \OC_User::getDisplayName($user), 'size' => $size));
		}
	}

	public static function postAvatar($args) {
		$user = \OC_User::getUser();

		if (isset($_POST['path'])) {
			$path = stripslashes($_POST['path']);
			$view = new \OC\Files\View('/'.$user.'/files');
			$avatar = $view->file_get_contents($path);
		}

		if (!empty($_FILES)) {
			$files = $_FILES['files'];
			if ($files['error'][0] === 0) {
				$avatar = file_get_contents($files['tmp_name'][0]);
				unlink($files['tmp_name'][0]);
			}
		}

		try {
			$ava = new \OC_Avatar();
			$ava->set($user, $avatar);
			\OC_JSON::success();
		} catch (\OC\NotSquareException $e) {
			$image = new \OC_Image($avatar);
			$ext = substr($image->mimeType(), -3);
			if ($ext === 'peg') {
				$ext = 'jpg';
			} elseif ($ext !== 'png') {
				\OC_JSON::error();
			}

			$view = new \OC\Files\View('/'.$user);
			$view->unlink('tmpavatar.png');
			$view->unlink('tmpavatar.jpg');
			$view->file_put_contents('tmpavatar.'.$ext, $image->data());
			\OC_JSON::error(array("data" => array("message" => "notsquare") ));
		} catch (\Exception $e) {
			\OC_JSON::error(array("data" => array("message" => $e->getMessage()) ));
		}
	}

	public static function deleteAvatar($args) {
		$user = OC_User::getUser();

		try {
			$avatar = new \OC_Avatar();
			$avatar->remove($user);
			\OC_JSON::success();
		} catch (\Exception $e) {
			\OC_JSON::error(array("data" => array ("message" => $e->getMessage()) ));
		}
	}

	public static function getTmpAvatar($args) {
		// TODO deliver actual size here as well, so Jcrop can do its magic and we have the actual coordinates here again
		// TODO or don't have a size parameter and only resize client sided (looks promising)
		//
		// TODO move the tmpavatar to the cache instead, so it's cleaned up after some time
		$user = OC_User::getUser();

		$view = new \OC\Files\View('/'.$user);
		if ($view->file_exists('tmpavatar.png')) {
			$ext = 'png';
		} elseif ($view->file_exists('tmpavatar.jpg')) {
			$ext = 'jpg';
		} else {
			\OC_JSON::error();
			return;
		}

		$image = new \OC_Image($view->file_get_contents('tmpavatar.'.$ext));
		$image->resize($args['size']);
		$image->show();
	}

	public static function postCroppedAvatar($args) {
		$user = OC_User::getUser();
		$view = new \OC\Files\View('/'.$user);
		$crop = $_POST['crop'];

		if ($view->file_exists('tmpavatar.png')) {
			$ext = 'png';
		} elseif ($view->file_exists('tmpavatar.jpg')) {
			$ext = 'jpg';
		} else {
			\OC_JSON::error();
			return;
		}

		$image = new \OC_Image($view->file_get_contents('tmpavatar.'.$ext));
		$image->crop($crop['x'], $crop['y'], $crop['w'], $crop['h']);
		try {
			$avatar = new \OC_Avatar();
			$avatar->set($user, $image->data());
			// Clean up
			$view->unlink('tmpavatar.png');
			$view->unlink('tmpavatar.jpg');
			\OC_JSON::success();
                } catch (\Exception $e) {
                        \OC_JSON::error(array("data" => array("message" => $e->getMessage()) ));
                }
	}
}