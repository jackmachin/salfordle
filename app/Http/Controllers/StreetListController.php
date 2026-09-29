<?php

namespace App\Http\Controllers;

use App\Models\Street;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreetListController extends Controller
{
    /**
     * Every guessable street, for client-side autocomplete: names, postcode district and full
     * postcodes (so a postcode read off a sign or a letterbox finds the street).
     *
     * Browsers must revalidate (ETag) rather than trust a cached copy: a stale list from
     * before a data or shape change breaks the search, and a 304 costs next to nothing.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $streets = Street::orderBy('display_name')
            ->get(['id', 'display_name', 'postcode_district', 'postcodes'])
            ->map(fn (Street $s) => [
                'id' => $s->id,
                'name' => $s->display_name,
                'district' => $s->postcode_district,
                'postcodes' => $s->postcodes ?? [],
            ]);

        $response = response()->json($streets)->setEtag(md5($streets->toJson()));
        $response->headers->set('Cache-Control', 'no-cache, public');
        $response->isNotModified($request);

        return $response;
    }
}
