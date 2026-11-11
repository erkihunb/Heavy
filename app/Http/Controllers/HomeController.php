<?php

namespace App\Http\Controllers;

use App\Support\EthiopianCalendar;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    /**
     * The public landing page.
     */
    public function __invoke(): Response
    {
        $locale = app()->getLocale();
        $today = now(config('localization.display_timezone'));

        return Inertia::render('welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            // Dates for the illustrative permit card only; no permit exists behind it.
            'sample' => [
                'validFrom' => EthiopianCalendar::present($today, $locale),
                'validTo' => EthiopianCalendar::present($today->addDays(89), $locale),
            ],
        ]);
    }
}
