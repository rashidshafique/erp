<?php

namespace App\Domains\Cameras\Http\Controllers;

use App\Domains\Cameras\Models\Camera;
use App\Domains\Cameras\Services\DahuaClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The live camera page and the two ways a picture reaches it: a still that is asked for again and again, or one continuous stream. */
class CameraViewController extends Controller
{
    public function index(): View
    {
        $cameras = Camera::shown()->with('dvr')->get();

        return view('cameras::index', ['cameras' => $cameras, 'areas' => $cameras->pluck('area')->filter()->unique()->sort()->values()]);
    }

    /** One still picture. A recorder that cannot be reached gives a "no picture" image, so the page shows what is wrong in place. */
    public function snapshot(Camera $camera, DahuaClient $client): Response
    {
        abort_unless($camera->active && $camera->dvr?->active, 404);

        try {
            return response($client->snapshot($camera), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store, max-age=0']);
        } catch (\RuntimeException $e) {
            return response($this->placeholder($camera, $e->getMessage()), 502, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store', 'X-Camera-Error' => rawurlencode($e->getMessage())]);
        }
    }

    /**
     * The camera as one continuous MJPEG stream, passed on from the recorder as it arrives. It holds a connection for as long as the
     * picture is open, so the page only uses it for the one camera shown large.
     */
    public function stream(Request $request, Camera $camera, DahuaClient $client): StreamedResponse|Response
    {
        abort_unless($camera->active && $camera->dvr?->active, 404);

        try {
            $upstream = $client->mjpeg($camera, mainStream: $request->boolean('main'));
        } catch (\RuntimeException $e) {
            return response($this->placeholder($camera, $e->getMessage()), 502, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'no-store']);
        }
        // Other pages of this user must not wait for this one to end.
        $request->hasSession() && $request->session()->save();
        $type = $upstream->header('Content-Type') ?: 'multipart/x-mixed-replace';

        return response()->stream(function () use ($upstream) {
            @set_time_limit(0);
            $body = $upstream->toPsrResponse()->getBody();
            while (! $body->eof() && ! connection_aborted()) {
                echo $body->read(16384);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            $body->close();
        }, 200, ['Content-Type' => $type, 'Cache-Control' => 'no-store', 'X-Accel-Buffering' => 'no']);
    }

    private function placeholder(Camera $camera, string $reason): string
    {
        $name = e($camera->name);
        $lines = array_slice(explode("\n", wordwrap(e($reason), 44, "\n", true)), 0, 3);
        $text = collect($lines)->map(fn ($l, $i) => '<text x="320" y="'.(235 + $i * 20).'" text-anchor="middle" font-size="14" fill="#b9c1cc">'.$l.'</text>')->implode('');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 360"><rect width="640" height="360" fill="#1f2630"/>'
            .'<text x="320" y="175" text-anchor="middle" font-family="sans-serif" font-size="20" font-weight="700" fill="#fff">No picture from '.$name.'</text>'
            .'<g font-family="sans-serif">'.$text.'</g></svg>';
    }
}
