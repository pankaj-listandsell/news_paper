<?php

namespace App\Http\Controllers;

use App\Support\MetalPrices;
use App\Support\PostalCodes;
use Illuminate\Http\Request;

/**
 * The small utilities readers come to a news site for: what a postal code
 * covers, how old someone is, what gold costs today.
 */
class ToolController extends Controller
{
    /**
     * Worked out in the browser, so there is nothing to submit and no page
     * reload between typing a date and seeing the answer.
     */
    public function age()
    {
        return view('tools.age');
    }

    public function postalCodes(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        // Nothing typed yet: show the empty form rather than "no results".
        if ($query === '') {
            return view('tools.postal-codes', ['query' => '', 'results' => null, 'failed' => false]);
        }

        $results = PostalCodes::search($query);

        return view('tools.postal-codes', [
            'query'   => $query,
            'results' => $results ?? [],
            'failed'  => $results === null,
        ]);
    }

    public function goldPrice()
    {
        return view('tools.gold-price', ['prices' => MetalPrices::current()]);
    }
}
