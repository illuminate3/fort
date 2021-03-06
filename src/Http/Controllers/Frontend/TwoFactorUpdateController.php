<?php

/*
 * NOTICE OF LICENSE
 *
 * Part of the Rinvex Fort Package.
 *
 * This source file is subject to The MIT License (MIT)
 * that is bundled with this package in the LICENSE file.
 *
 * Package: Rinvex Fort Package
 * License: The MIT License (MIT)
 * Link:    https://rinvex.com
 */

namespace Rinvex\Fort\Http\Controllers\Frontend;

use Carbon\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Rinvex\Fort\Http\Requests\TwoFactorTotp;
use Rinvex\Fort\Http\Requests\TwoFactorPhone;
use Rinvex\Fort\Services\TwoFactorTotpProvider;
use Rinvex\Fort\Contracts\UserRepositoryContract;
use Rinvex\Fort\Http\Controllers\AuthorizedController;

class TwoFactorUpdateController extends AuthorizedController
{
    /**
     * The user repository instance.
     *
     * @var \Rinvex\Fort\Contracts\UserRepositoryContract
     */
    protected $userRepository;

    /**
     * Create a new Two-Factor update controller instance.
     *
     * @param \Rinvex\Fort\Contracts\UserRepositoryContract $userRepository
     *
     * @return void
     */
    public function __construct(UserRepositoryContract $userRepository)
    {
        parent::__construct();

        $this->userRepository = $userRepository;
    }

    /**
     * Show the Two-Factor TOTP enable form.
     *
     * @param \Rinvex\Fort\Http\Requests\TwoFactorTotp    $request
     * @param \Rinvex\Fort\Services\TwoFactorTotpProvider $totpProvider
     *
     * @return \Illuminate\Http\Response
     */
    public function showTwoFactorTotpEnable(TwoFactorTotp $request, TwoFactorTotpProvider $totpProvider)
    {
        $currentUser = $this->currentUser();
        $settings    = $currentUser->getTwoFactor();

        if (array_get($settings, 'totp.enabled') && ! session()->get('rinvex.fort.alert.success') && ! session()->get('errors')) {
            $messageBag = new MessageBag([trans('rinvex.fort::frontend/messages.verification.twofactor.totp.already')]);
            $errors     = (new ViewErrorBag())->put('default', $messageBag);
        }

        if (! $secret = array_get($settings, 'totp.secret')) {
            array_set($settings, 'totp.enabled', false);
            array_set($settings, 'totp.secret', $secret = $totpProvider->generateSecretKey());

            $this->userRepository->update($currentUser, [
                'two_factor' => $settings,
            ]);
        }

        $qrCode = $totpProvider->getQRCodeInline(config('rinvex.fort.twofactor.issuer'), $currentUser->email, $secret);

        return view('rinvex.fort::frontend.profile.twofactor', compact('secret', 'qrCode', 'settings', 'errors'));
    }

    /**
     * Process the Two-Factor TOTP enable form.
     *
     * @param \Rinvex\Fort\Http\Requests\TwoFactorTotp    $request
     * @param \Rinvex\Fort\Services\TwoFactorTotpProvider $totpProvider
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function processTwoFactorTotpEnable(TwoFactorTotp $request, TwoFactorTotpProvider $totpProvider)
    {
        $currentUser = $this->currentUser();
        $settings    = $currentUser->getTwoFactor();
        $secret      = array_get($settings, 'totp.secret');
        $backup      = array_get($settings, 'totp.backup');
        $backupAt    = array_get($settings, 'totp.backup_at');

        if ($totpProvider->verifyKey($secret, $request->get('token'))) {
            array_set($settings, 'totp.enabled', true);
            array_set($settings, 'totp.secret', $secret);
            array_set($settings, 'totp.backup', $backup ?: $this->generateTwoFactorTotpBackups());
            array_set($settings, 'totp.backup_at', $backupAt ?: (new Carbon())->toDateTimeString());

            // Update Two-Factor settings
            $this->userRepository->update($currentUser, [
                'two_factor' => $settings,
            ]);

            return intend([
                'back' => true,
                'with' => ['rinvex.fort.alert.success' => trans('rinvex.fort::frontend/messages.verification.twofactor.totp.enabled')],
            ]);
        }

        return intend([
            'back'       => true,
            'withErrors' => ['token' => trans('rinvex.fort::frontend/messages.verification.twofactor.totp.invalid_token')],
        ]);
    }

    /**
     * Process the Two-Factor TOTP disable.
     *
     * @param \Rinvex\Fort\Http\Requests\TwoFactorTotp $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function processTwoFactorTotpDisable(TwoFactorTotp $request)
    {
        $currentUser = $this->currentUser();
        $settings    = $currentUser->getTwoFactor();

        array_set($settings, 'totp', []);

        $this->userRepository->update($currentUser, [
            'two_factor' => $settings,
        ]);

        return intend([
            'intended' => route('rinvex.fort.frontend.account.page'),
            'with'     => ['rinvex.fort.alert.success' => trans('rinvex.fort::frontend/messages.verification.twofactor.totp.disabled')],
        ]);
    }

    /**
     * Process the Two-Factor Phone enable.
     *
     * @param \Rinvex\Fort\Http\Requests\TwoFactorPhone $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function processTwoFactorPhoneEnable(TwoFactorPhone $request)
    {
        $currentUser = $this->currentUser();

        if (! $currentUser->phone || ! $currentUser->phone_verified) {
            return intend([
                'intended'   => route('rinvex.fort.frontend.account.page'),
                'withErrors' => ['phone' => trans('rinvex.fort::frontend/messages.account.phone_required')],
            ]);
        }

        $settings = $currentUser->getTwoFactor();

        array_set($settings, 'phone.enabled', true);

        $this->userRepository->update($currentUser, [
            'two_factor' => $settings,
        ]);

        return intend([
            'intended' => route('rinvex.fort.frontend.account.page'),
            'with'     => ['rinvex.fort.alert.success' => trans('rinvex.fort::frontend/messages.verification.twofactor.phone.enabled')],
        ]);
    }

    /**
     * Process the Two-Factor Phone disable.
     *
     * @param \Rinvex\Fort\Http\Requests\TwoFactorPhone $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function processTwoFactorPhoneDisable(TwoFactorPhone $request)
    {
        $currentUser = $this->currentUser();
        $settings    = $currentUser->getTwoFactor();

        array_set($settings, 'phone.enabled', false);

        $this->userRepository->update($currentUser, [
            'two_factor' => $settings,
        ]);

        return intend([
            'intended' => route('rinvex.fort.frontend.account.page'),
            'with'     => ['rinvex.fort.alert.success' => trans('rinvex.fort::frontend/messages.verification.twofactor.phone.disabled')],
        ]);
    }

    /**
     * Process the Two-Factor OTP backup.
     *
     * @param \Rinvex\Fort\Http\Requests\TwoFactorTotp $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function processTwoFactorTotpBackup(TwoFactorTotp $request)
    {
        $currentUser = $this->currentUser();
        $settings    = $currentUser->getTwoFactor();

        if (! array_get($settings, 'totp.enabled')) {
            return intend([
                'intended'   => route('rinvex.fort.frontend.account.page'),
                'withErrors' => ['rinvex.fort.verification.twofactor.totp.cant_backup' => trans('rinvex.fort::frontend/messages.verification.twofactor.totp.cant_backup')],
            ]);
        }

        array_set($settings, 'totp.backup', $this->generateTwoFactorTotpBackups());
        array_set($settings, 'totp.backup_at', (new Carbon())->toDateTimeString());

        $this->userRepository->update($currentUser, [
            'two_factor' => $settings,
        ]);

        return intend([
            'back' => true,
            'with' => ['rinvex.fort.alert.success' => trans('rinvex.fort::frontend/messages.verification.twofactor.totp.rebackup')],
        ]);
    }

    /**
     * Generate Two-Factor OTP backup codes.
     *
     * @return array
     */
    protected function generateTwoFactorTotpBackups()
    {
        $backup = [];

        for ($x = 0; $x <= 9; $x++) {
            $backup[] = str_pad(random_int(0, 9999999999), 10, 0, STR_PAD_BOTH);
        }

        return $backup;
    }

    /**
     * Get current user.
     *
     * @return \Rinvex\Fort\Contracts\AuthenticatableContract
     */
    protected function currentUser()
    {
        return Auth::guard($this->getGuard())->user();
    }
}
