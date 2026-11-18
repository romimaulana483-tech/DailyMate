<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Switch the interface language. Only Indonesian and English are supported.
     */
    public function switch(Request $request, string $locale)
    {
        abort_unless(in_array($locale, ['id', 'en'], true), 404);

        $request->session()->put('locale', $locale);

        return redirect()->back(fallback: route('home'));
    }
}
