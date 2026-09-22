<?php

use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn() => ['ok' => true]);

Route::post(
    '/referrals/attach',
    function (
        Request $request,
        ReferralService $referrals,
    ) {
        $master = $request->attributes->get('current_master');

        abort_if(empty($master), 401, 'Мастер не найден');

        $data = $request->validate([
            'code' => ['required', 'string']
        ]);

        $referral = $referrals->registerReferral(
            $master,
            $data['code'],
        );

        return [
            'attached' => $referral !== null,
            'referral_id' => $referral?->id,
        ];
    }
);

Route::get(
    '/referrals/my',
    function (Request $request) {

        $master = $request->attributes->get('current_master');

        abort_if(empty($master), 401, 'Мастер не найден');

        $earnings = $master->referralEarnings()
            ->selectRaw('referral_id, SUM(amount) as total')
            ->groupBy('referral_id')
            ->pluck('total', 'referral_id');

        return $master->referrals()
            ->with('referredMaster')
            ->get()
            ->map(fn(Referral $referral) => [
                'name' => $referral->referredMaster->name,
                'attached_at' => $referral->created_at->toISOString(),
                'rewarded' => $referral->status === Referral::STATUS_REWARDED,
                'earned' => (int) $earnings->get($referral->id, 0),
            ]);
    }
);

Route::get(
    '/referrals/earnings',
    function (Request $request) {

        $master = $request->attributes->get('current_master');

        abort_if(empty($master), 401, 'Мастер не найден');

        $earnings = $master->referralEarnings()->get();

        return [
            'total' => (int) $earnings->sum('amount'),

            'pending' => (int) $earnings
                ->where('status', ReferralEarning::STATUS_PENDING)
                ->sum('amount'),

            'paid' => (int) $earnings
                ->where('status', ReferralEarning::STATUS_PAID)
                ->sum('amount'),

            'rewarded_referrals' => $master
                ->referrals()
                ->active()
                ->count(),
        ];
    }
);
