<?php

namespace App\Domains\Cameras\Services;

use App\Domains\Cameras\Models\Camera;
use App\Domains\Cameras\Models\Dvr;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Reads pictures from a Dahua recorder over its HTTP interface. The recorder asks for Digest login, which a browser cannot do for
 * an image, so the app logs in with the stored password and hands the picture on; the password never reaches the browser.
 */
class DahuaClient
{
    private const TIMEOUT_SECONDS = 6;

    /** One JPEG of a channel. @throws \RuntimeException when the recorder cannot be reached or refuses the login. */
    public function snapshot(Camera $camera): string
    {
        $camera->loadMissing('dvr');
        $response = $this->get($camera->dvr, '/cgi-bin/snapshot.cgi', ['channel' => $camera->channel]);
        $this->assertPicture($response->status(), (string) $response->header('Content-Type'), $camera->dvr);

        return $response->body();
    }

    /**
     * A channel as a continuous MJPEG stream, as the recorder sends it (multipart/x-mixed-replace). Returns the open response, to be
     * read in chunks. @throws \RuntimeException when the recorder cannot be reached or refuses the login.
     */
    public function mjpeg(Camera $camera, bool $mainStream = false): \Illuminate\Http\Client\Response
    {
        $camera->loadMissing('dvr');
        $response = $this->get($camera->dvr, '/cgi-bin/mjpg/video.cgi', ['channel' => $camera->channel, 'subtype' => $mainStream ? 0 : 1], stream: true);
        $this->assertPicture($response->status(), (string) $response->header('Content-Type'), $camera->dvr, 'multipart');

        return $response;
    }

    /** Whether the recorder answers with these details, and a sentence saying what happened. @return array{ok: bool, message: string} */
    public function test(Dvr $dvr, int $channel = 1): array
    {
        try {
            $response = $this->get($dvr, '/cgi-bin/snapshot.cgi', ['channel' => $channel]);
            $this->assertPicture($response->status(), (string) $response->header('Content-Type'), $dvr);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => "The recorder answered and sent a picture from channel {$channel}."];
    }

    /** @param  array<string, scalar>  $query */
    private function get(Dvr $dvr, string $path, array $query, bool $stream = false): \Illuminate\Http\Client\Response
    {
        $request = Http::withDigestAuth($dvr->username, $dvr->password)->connectTimeout(4)->withOptions(['verify' => false])
            ->timeout($stream ? 0 : self::TIMEOUT_SECONDS);
        if ($stream) {
            $request = $request->withOptions(['stream' => true]);
        }

        try {
            return $request->get($dvr->baseUrl().$path, $query);
        } catch (ConnectionException) {
            throw new \RuntimeException("{$dvr->name} could not be reached at {$dvr->host}:{$dvr->port}. Check the address, the port and the network.");
        }
    }

    private function assertPicture(int $status, string $contentType, Dvr $dvr, string $expect = 'image'): void
    {
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException("{$dvr->name} refused the user name or password.");
        }
        if ($status !== 200) {
            throw new \RuntimeException("{$dvr->name} answered with an error ({$status}). Check the channel number.");
        }
        if (! str_contains(strtolower($contentType), $expect)) {
            throw new \RuntimeException("{$dvr->name} did not send a picture. Check the address and port.");
        }
    }
}
