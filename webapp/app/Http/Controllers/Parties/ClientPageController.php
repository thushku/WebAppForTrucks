<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Modules\Parties\Models\Party;
use App\Modules\Parties\Services\ClientPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/** A client's own page (M47): the public /c/{token}, and the party page's make / stop of the link. */
class ClientPageController extends Controller
{
    public function __construct(private ClientPage $pages) {}

    public function show(string $token): Response
    {
        $view = $this->pages->view($token);
        if (! $view) {
            return response()->view('parties.client-page-gone', [], 410)->header('X-Robots-Tag', 'noindex');
        }

        return response()->view('parties.client-page', $view)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    public function store(Party $party): RedirectResponse
    {
        $url = $this->pages->link($party);

        return redirect()->route('parties.show', [$party, 'tab' => 'billing'])->with('client_page', ['url' => $url, 'message' => $this->pages->message($party, $url)]);
    }

    public function destroy(Party $party): RedirectResponse
    {
        $this->pages->stop($party);

        return redirect()->route('parties.show', [$party, 'tab' => 'billing'])->with('status', __('The client page link is stopped; it no longer opens.'));
    }
}
